---
order: 25
---

# Reactive Fields

Fields can react to each other's values without leaving the schema. Every dynamic field
closure — `visible()`, `hidden()`, `disabled()`, `afterStateUpdated()` — is evaluated with
Filament-style state accessors resolved against the **live** Livewire state bag. The same API
works in a standalone `WithForms` form and inside a table action modal.

## State accessors

| Accessor | Returns |
| --- | --- |
| `$get('field')` | Another field's current value (a sibling under the same `statePath`). |
| `$get()` | This field's own value, read **live** — reflects a `$set` made earlier in the same closure. |
| `$set('field', $value)` | Writes another field. StateContainer-safe, so it also works inside action modals. |
| `$state` | This field's value, snapshotted when the closure is invoked. |
| `$component` | The field instance. |

Reach for `$get` / `$set` instead of `Livewire::current()` + `data_get()` — the accessors resolve
the correct bag prefix for you, including the `tableState.modal.action.formData` bag of an action
modal.

## Conditional fields

Show a field only when another field has a given value. A field hidden this way is also **skipped
during validation**, so a `required()` rule never blocks submit while the field is hidden:

```php
Select::make('type')
    ->options(['business' => 'Business', 'person' => 'Person'])
    ->live(),

TextInput::make('vat_id')
    ->visible(fn ($get) => $get('type') === 'business')
    ->required(),
```

The trigger field must be `->live()` so its change round-trips to the server and re-evaluates the
dependent field's visibility.

Layout components receive the same accessors, so a whole `Grid`, `Section` or `Fieldset` can show or
hide on a sibling's value:

```php
Section::make('Billing')
    ->schema([
        TextInput::make('vat_id'),
        TextInput::make('company'),
    ])
    ->visible(fn ($get) => $get('type') === 'business'),
```

## Field actions and buttons

Attach an interactive `Action` to an input's affix or hint area with `suffixAction()`,
`prefixAction()` or `hintAction()`. The action's callback runs on the server with the same
`$get` / `$set` / `$state` context — ideal for a lookup, a "generate" helper, or an inline verify
button:

```php
use NyonCode\WireCore\Actions\Action;

TextInput::make('title')->suffixAction(
    Action::make('to_upper')
        ->icon('heroicon-o-arrow-up')
        ->action(fn ($get, $set) => $set('title', strtoupper((string) $get('title')))),
);
```

For a standalone, design-system-styled button bound to a closure — instead of a raw
`Html::make()->content('<button …>')` that bypasses the palette — use the `Button` field. Its
presentation (`label` / `icon` / `color` / `size` / `outlined`) mirrors actions:

```php
use NyonCode\WireForms\Components\Button;

Button::make('generate_slug')
    ->label('Generate slug')
    ->icon('heroicon-o-sparkles')
    ->action(fn ($get, $set) => $set('slug', Str::slug((string) $get('title')))),
```

Both work in a standalone `WithForms` form and inside a table action modal — the host's
`callFieldAction()` endpoint re-resolves the field from the live schema and runs the closure.

## `afterStateUpdated()`

Run a callback after a field's value changes. Registering a callback **automatically enables
`live()`** — without a server round-trip the hook could never fire. The callback receives the new
value as `$state`, the previous value as `$old`, plus `$get` / `$set` / `$component`:

```php
TextInput::make('type')
    ->afterStateUpdated(function ($state, $old, $get, $set) {
        // Auto-fill a dependent field from the value just entered.
        $set('vat_id', $state === 'business' ? null : '');
    });
```

Any subset of the parameters can be type-hinted in any order — they are resolved by name:

```php
TextInput::make('quantity')
    ->afterStateUpdated(fn ($state, $set) => $set('total', $state * 10));
```

All of this reactivity — `afterStateUpdated()`, live validation, field actions, remote
select search and conditional visibility (`visibleWhen()` / `visible(fn ($get) => …)`) — also
works for fields inside `Repeater` items: the dispatch resolves the field per item, and
`$get`/`$set` read and write that item's own bag (so `$set('slug', …)` on row 2 touches only
row 2). A conditional field inside a repeater shows or hides based on **its own item's** state,
not its siblings'.

Multi-step forms get the same treatment: inside a Livewire host the standalone
[Wizard](../core/schema/layout/wizard.md#per-step-validation) validates the current step on the server before
"Next" advances, and a failed submit jumps to the first step containing an error.

## Prefill a form from an action

An action modal form reads and writes the `modal.action.formData` bag. Seed its initial values with
`fillFormUsing()`. The callback receives the record for row actions (and `null` for header actions,
which have no record):

```php
use NyonCode\WireCore\Actions\EditAction;

EditAction::make()
    ->form([
        TextInput::make('name')->required(),
        Select::make('role')->options(Role::class),
    ])
    ->fillFormUsing(fn ($record) => [
        'name' => $record->name,
        'role' => $record->role->value,
    ]);
```

Inside that form the reactive accessors above behave exactly as in a standalone form — `$get`,
`$set` and `afterStateUpdated()` all resolve against the live modal bag.

## Modal footer actions

Add extra buttons to an action modal's footer with `modalFooterActions()`. Each
`ModalFooterAction` callback receives the live form-data bag as `$data`, a `$set` writer for it,
`$component`, and the modal's `$record` / `$records` context — so a footer button can read and
write the in-progress form without submitting it:

```php
use NyonCode\WireCore\Actions\ModalFooterAction;

EditAction::make()
    ->form([
        TextInput::make('name')->required(),
        TextInput::make('slug'),
    ])
    ->modalFooterActions([
        ModalFooterAction::make('generate_slug')
            ->label('Generate slug')
            ->icon('sparkles')
            ->action(fn ($data, $set) => $set('slug', Str::slug($data['name'] ?? ''))),

        ModalFooterAction::make('preview')
            ->position('after')      // 'before' (default) or 'after' the Cancel/Submit buttons
            ->submitsForm()          // validate the form before the callback runs
            ->closesModal()          // close the modal afterwards
            ->action(fn ($data, $component) => $component->dispatch('preview', data: $data)),
    ]);
```

- `->submitsForm()` validates the modal form first, so validation errors surface before the callback.
- `->closesModal()` closes the modal once the callback returns.
- `->position('before'|'after')` places the button before or after the built-in Cancel/Submit.
- `->requiresConfirmation()` asks the user before the callback runs (a native `wire:confirm`
  dialog with a translated default message); `->confirm('Really reset?')` sets a custom message.
