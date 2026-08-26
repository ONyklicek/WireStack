# ADR 0007: Internal Module Dependencies

## Status
Accepted. **Amended by [ADR 0025](0025-core-module-layers.md) (2026-08-26).** The
matrix below covered four modules of eleven and named code review as its
enforcement; by 2026-08 it was violated in 23 places, including a bidirectional
`Actions` ↔ `Modals` cycle. ADR 0025 restates it as three layers over all eleven
modules, allows L2 → L1 (`Actions` really does drive `ActionPipeline` and
`PluginManager`), treats `Foundation` and `Exceptions` as one layer, and replaces
"code review + grep" with `packages/core/tests/Unit/Architecture/ModuleLayersTest.php`.

The intent — modules that stay independently extractable — is unchanged. Read
0025 for the rule as it is actually enforced; read this for why it exists.

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
