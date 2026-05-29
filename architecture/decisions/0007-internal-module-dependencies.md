# ADR 0007: Internal Module Dependencies

## Status
Accepted

## Context
`wire-core` contains four modules: Foundation, Actions, Notifications, Modals. Strict dependency rules between them prepare for future extraction (ADR 0006).

## Decision

### Dependency matrix

| Module | May depend on | Must NOT depend on |
|--------|---------------|-------------------|
| Foundation | — (no internal deps) | Actions, Notifications, Modals |
| Actions | Foundation | Notifications (direct), Modals |
| Notifications | Foundation | Actions, Modals |
| Modals | Foundation | Actions, Notifications |

### Cross-module communication
When Actions needs Notifications (e.g., `Action::successNotification()`), it uses **service container resolution**, not direct imports:

```php
// In Actions code — NO direct import of Notification classes
if (app()->bound(TableNotificationManager::class)) {
    app(TableNotificationManager::class)->success($message);
}
```

### Enforcement
1. **Code review:** PR reviewers check for cross-module imports.
2. **Namespace convention:** Each module uses `NyonCode\WireCore\{Module}\` — grep for cross-imports is simple.
3. **Test isolation:** Module tests only import from Foundation and their own module.

### Foundation contracts
Shared interfaces that modules need live in `Foundation/Contracts/`. Any module can implement or depend on these contracts.

## Consequences
- **Good:** Modules are independently extractable (ADR 0006).
- **Good:** Removing a module (e.g., Notifications) doesn't break others — they degrade gracefully.
- **Trade-off:** Service container resolution is slightly more verbose than direct imports.
