---
name: wire-forms-development
description: Build and modify wire-forms forms — fields, validation, layout components, options and the save lifecycle.
---

# wire-forms Development

## When to use this skill

Use when creating or changing a Livewire form built with wire-forms (a component using `WithForms` and a
`form(Form $form): Form` method).

## Workflow

1. Run `list-component-types` with category `fields` to see available inputs, then `describe-component-api`
   for a field's fluent methods (validation, options, reactivity).
2. Inspect an existing form with `describe-form` to match conventions.
3. Build the schema fluently under a `statePath`.

## Patterns

```php
public function form(Form $form): Form
{
    return $form->statePath('data')->schema([
        Section::make('Profile')->schema([
            Grid::make()->columns(2)->schema([
                TextInput::make('first_name')->required(),
                TextInput::make('last_name')->required(),
            ]),
            Select::make('role')->options(Role::class)->searchable(),
            Repeater::make('contacts')->schema([
                TextInput::make('label'),
                TextInput::make('value'),
            ]),
        ]),
    ]);
}
```

## Reactive patterns

Field closures (`visible`, `hidden`, `disabled`, `afterStateUpdated`) receive live state accessors:
`$get('sibling')` reads another field, `$get()` reads this field's own live value, `$set('field', $v)`
writes another field (StateContainer-safe in action modals), and `$state` is this field's snapshot value.

Conditional field + reactive auto-fill:

```php
Select::make('type')->options(['business' => 'Business', 'person' => 'Person'])->live(),

TextInput::make('vat_id')
    // Only shown — and only validated — when type is "business".
    ->visible(fn ($get) => $get('type') === 'business')
    ->required(),

TextInput::make('discount')
    // afterStateUpdated auto-enables live(); $old is the previous value.
    ->afterStateUpdated(fn ($state, $set) => $set('discount_label', "{$state}%")),
```

Prefill an action modal form from the record with `fillFormUsing()` (callback gets the record,
`null` for header actions):

```php
EditAction::make()
    ->form([TextInput::make('name'), Select::make('role')->options(Role::class)])
    ->fillFormUsing(fn ($record) => ['name' => $record->name, 'role' => $record->role->value]);
```

## Rules

- A field's `make($name)` argument is the key under the form `statePath`.
- Put validation on the field (`->required()`, `->rules([...])`).
- Use an enum class with `->options(Enum::class)` rather than re-listing values.
- Reactivity is opt-in via `->live()`; `afterStateUpdated()` enables it for you.
- Read/write sibling state inside closures with `$get`/`$set`; do not reach for `Livewire::current()`.
