# ADR 0006: Modular Core Extraction Strategy

## Status
Accepted

## Context
`wire-core` contains four logical modules: Foundation, Actions, Notifications, Modals. Actions, Notifications, and Modals are candidates for future extraction into standalone packages (`wire-actions`, `wire-notifications`, `wire-modals`) when a real use case arises (e.g., `wire-infolist` needing Actions without Table).

This ADR documents **how** to perform the extraction so it's a mechanical operation, not a refactor.

## Decision
The extraction follows these steps:

### Step 1: Create the new package
```bash
mkdir -p packages/actions/src packages/actions/tests
```

### Step 2: Move the module directory
```bash
mv packages/core/src/Actions/ packages/actions/src/
mv packages/core/tests/Actions/ packages/actions/tests/
mv packages/core/resources/views/actions/ packages/actions/resources/views/
```

### Step 3: Rename namespace
Find and replace in the moved files:
```
NyonCode\WireCore\Actions\ → NyonCode\WireActions\
```

### Step 4: Create composer.json
The new package depends on `wire-core` (for Foundation traits).

### Step 5: Create ServiceProvider
Move the `bootActions()` content from `WireCoreServiceProvider` into `WireActionsServiceProvider`.

### Step 6: Update consumers
- `wire-forms` ActionMacros: update `use` statement for `BaseAction`
- `wire-table`: add `nyoncode/wire-actions` dependency, update imports

### Step 7: Blade components
Blade tags (`<x-wire-actions::button />`) remain unchanged — the new package registers the same component namespace.

### What stays in core
`Foundation/` stays in `wire-core` forever. It's the shared base that all packages depend on.

### Pre-extraction checklist
Before extracting a module, verify:
- [ ] No direct imports between the module and other modules (only via Foundation contracts)
- [ ] Module has its own `boot*()` method in the service provider
- [ ] Module's Blade components use a distinct prefix (`wire-actions::`, `wire-notifications::`, `wire-modals::`)
- [ ] Module's tests don't import from other modules directly

## Consequences
- **Good:** Extraction is a 30-minute mechanical task, not a multi-day refactor.
- **Good:** Blade tags don't change — zero impact on end users.
- **Trade-off:** Until extraction, the module lives inside `wire-core`, inflating its size. Acceptable since core is rarely installed alone.
