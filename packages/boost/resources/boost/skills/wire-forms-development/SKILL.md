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

Fluent conditioning shortcuts replace the Closure for the common "equals value" case (array = "is one of"):

```php
TextInput::make('vat_id')
    ->visibleWhen('type', 'business')       // also hiddenWhen / disabledWhen (shared with columns/filters/actions)
    ->requiredIf('type', 'business')        // also requiredUnless / requiredWith
    ->validateLive();                       // validate this field on change (or ->validateOnBlur())
```

`validateLive()` / `validateOnBlur()` validate just that field during the reactive roundtrip and
refresh only its error bag; `requiredIf()` is honoured live. Cross-field string rules
(`required_if:other,value`) validate on submit — prefer `requiredIf()` for the reactive version.

Prefill an action modal form from the record with `fillFormUsing()` (callback gets the record,
`null` for header actions):

```php
EditAction::make()
    ->form([TextInput::make('name'), Select::make('role')->options(Role::class)])
    ->fillFormUsing(fn ($record) => ['name' => $record->name, 'role' => $record->role->value]);
```

## Standalone actions outside a table

To run modal/slide-over/wizard/confirmation actions with forms in a plain Livewire component (no
table), use the `WithActions` trait instead of hand-rolling modal state:

```php
use NyonCode\WireForms\Concerns\WithActions;

class EditPanel extends Component
{
    use WithActions;

    protected function actions(): array
    {
        return [
            Action::make('editOffer')->slideOver()
                ->form([TextInput::make('name')->required()])
                ->action(fn (array $data) => $this->offer->update($data)),
        ];
    }
}
```

```blade
<x-wire-actions::button :action="$this->offerAction()" />  {{-- auto wire:click=mountAction --}}
<x-wire-actions::modal-host :component="$this" />           {{-- render once --}}
```

Livewire methods: `mountAction($name, ['record' => $model])`, `callMountedAction()`,
`unmountAction()`, `nextActionModalStep()`/`prevActionModalStep()`, `callModalFooterAction($name)`.
The modal form binds to the public `actionModalFormData` property; `fillFormUsing`, field actions and
`createOptionForm` behave exactly as in a table modal. Same engine as `WithTable`.

## Rules

- A field's `make($name)` argument is the key under the form `statePath`.
- Put validation on the field (`->required()`, `->rules([...])`); `->rules()` also accepts a Closure for state-dependent rules.
- Prefer conditioning shortcuts over hand-written closures: `requiredIf`/`requiredUnless`/`requiredWith`, `visibleWhen`/`hiddenWhen`/`disabledWhen`.
- Opt into live validation per field with `->validateLive()` / `->validateOnBlur()`.
- Use an enum class with `->options(Enum::class)` rather than re-listing values.
- `Radio` renders as a list by default, or `->cards()` / `->segmented()` / `->buttons()`. All take `->icons([...])` / `->colors([...])` per option (auto-derived from a `HasIcon`/`HasColor` enum via `->options(Enum::class)`), a group `->color(...)`, and — for `segmented`/`buttons` — `->sm()`/`->md()`/`->lg()`.
- Reactivity is opt-in via `->live()`; `afterStateUpdated()` enables it for you.
- Read/write sibling state inside closures with `$get`/`$set`; do not reach for `Livewire::current()`.
