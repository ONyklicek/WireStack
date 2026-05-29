# ADR 0011: Form Config/Runtime Separation

## Status
Accepted

## Context
The `Form` class handles both configuration (schema, hooks, model) and runtime operations (validate, save, state management). Mixing these concerns makes testing harder and increases cognitive load.

## Decision
Internally, `Form` delegates to two layers:

### Config layer
- **`ConfigBuilder`** — accumulates fluent method calls (`schema()`, `model()`, `statePath()`, hooks)
- **`FormConfig`** — immutable value object built from `ConfigBuilder`. Once created, config values don't change.

### Runtime layer
- **`FormRuntime`** — orchestrates runtime operations using a `FormConfig` instance
- **`StateManager`** — manages form state, `fill()`, `getState()`, wire:model path resolution
- **`SaveHandler`** — executes save lifecycle (validate → mutate → beforeSave → persist → afterSave → notify)

### Public API unchanged
Users only interact with `Form`. Internal classes are never exposed in public signatures:

```php
// User code — unchanged
$form->schema([...])->model(User::class)->save();

// Internally:
// 1. schema() and model() → ConfigBuilder
// 2. save() → FormRuntime → SaveHandler
```

### Testing per layer
- `FormConfig` tests: verify immutability, builder produces correct config
- `StateManager` tests: fill/getState roundtrip, statePath prefix
- `SaveHandler` tests: lifecycle hook order, mutation, model create/update
- `FormValidationResolver` tests: rule collection from fields, merge with form-level rules
- `Form` integration tests: end-to-end fluent API

## Consequences
- **Good:** Each class has a single responsibility.
- **Good:** Unit tests are focused and fast.
- **Good:** Runtime logic is testable without fluent API setup.
- **Trade-off:** More files (6 classes instead of 1). Acceptable for maintainability.
