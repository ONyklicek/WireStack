# ADR 0012: Form::make() Standalone Usage

## Status
Accepted

## Context
`Form` must work outside Livewire — in unit tests, console commands, jobs, and API controllers. `Form::make()` is the factory for standalone usage.

## Decision

### Factory behavior
```php
$form = Form::make()
    ->schema([TextInput::make('name')->required()])
    ->state(['name' => 'John']);

$data = $form->validate(); // works without Livewire
```

`Form::make()` creates a new `Form` instance with default dependencies. If the Laravel service container is available, it resolves dependencies from it. Otherwise, it uses sensible fallbacks.

### Dependency resolution

| Dependency | Container available | No container |
|------------|-------------------|-------------|
| `NotificationManager` | Resolved from container | Skipped (no notifications) |
| `FormRenderer` | Resolved from container | Basic HTML fallback |
| Validation | Laravel `Validator` facade | Requires Laravel (minimum dependency) |

### What works standalone
- `schema()`, `statePath()`, `model()` — all config methods
- `fill()`, `state()`, `getState()` — state management
- `validate()` — full validation with `ValidationException`
- `save()` — Eloquent persistence (requires database connection)
- `isCreating()`, `isEditing()`, `getModel()` — introspection
- `getFlatComponents()`, `getValidationRules()` — schema inspection

### What requires Livewire
- `toHtml()` / rendering — needs Blade engine and component context
- `wire:model` bindings — Livewire-specific
- Real-time validation — Livewire-specific

### Testing patterns
```php
// Validation test
it('validates required fields', function () {
    $form = Form::make()
        ->schema([TextInput::make('email')->email()->required()])
        ->state([]);

    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class);
});

// State roundtrip test
it('fills and returns state', function () {
    $form = Form::make()
        ->schema([TextInput::make('name')])
        ->fill(['name' => 'Jane']);

    expect($form->getState())->toBe(['name' => 'Jane']);
});
```

## Consequences
- **Good:** Forms are fully testable without HTTP requests or Livewire booting.
- **Good:** Reusable in non-Livewire contexts (Artisan commands, queue jobs, API controllers).
- **Trade-off:** Rendering requires Livewire. Standalone forms can validate and save but not render HTML.
