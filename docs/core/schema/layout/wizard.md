---
order: 10
---

# Wizard

Multi-step wizard layout: a step indicator over a set of panels with Previous /
Next controls — the standalone counterpart to the
[action-modal wizard](../../modals.md#multi-step-wizard). All steps stay in
the DOM, so nested fields validate together on submit regardless of the active
step.

```php
use NyonCode\WireCore\Foundation\Schema\Step;
use NyonCode\WireCore\Foundation\Schema\Wizard;
```

## Usage

```php
Wizard::make()->schema([
    Step::make('Account')->description('Login details')->icon('user')->schema([
        TextInput::make('name')->required(),
    ]),
    Step::make('Contact')->schema([
        TextInput::make('email')->required(),
    ]),
])
```

On desktop each indicator circle carries the step's label and description; on
mobile the indicator collapses to numbered circles and the active step's label
and description render below it.

## Per-Step Validation

Inside a Livewire host (`WithForms` or a table action modal), **Next validates
the current step on the server before advancing** — the same rules the fields
declare (`rules()`, `required()`, repeater item rules, …), scoped to that step.
On failure the wizard stays put and the errors render in the active panel; later
steps are never flagged early. Jumping via a `skippable()` indicator skips
validation, like Filament.

Two related behaviors come along:

- **Failed submit jumps to the first errored step**, so a message from an
  earlier step is never stranded in a hidden panel.
- **Dynamic steps stay in sync**: when a `visible()` condition adds or removes a
  step mid-form (a `live()` field roundtrip), the indicator and navigation
  re-align and the active step is clamped to the rendered range.

Rendered outside a Livewire host, Next falls back to plain client-side
navigation and the form validates on submit as before.

Multiple wizards on one host are addressed by name — give each a name
(`Wizard::make('signup')`) so its steps validate independently; an unnamed
wizard resolves to the first one in the schema.

## Handing The Navigation Elsewhere

`navigation(false)` renders the wizard without its Previous / Next row, for a
surface that wants those controls in its own chrome — a modal footer, a page
toolbar — so two navigations do not sit on screen at once:

```php
Wizard::make('category')
    ->navigation(false)          // [tl! focus]
    ->schema([
        Step::make('Name')->schema([TextInput::make('label')->required()]),
        Step::make('Detail')->schema([TextInput::make('note')]),
    ])
```

The wizard still owns the step state; the outer surface mirrors and steps it over
two window events, because a driving footer is a *sibling* subtree and a bubbling
event would never reach it:

- `wire-wizard-state` — published by the wizard whenever its step, total or
  validating flag changes: `{ wizard, step, total, validating }`.
- `wire-wizard-navigate` — sent to the wizard to move: `{ wizard, direction }`
  where direction is `'next'` or `'previous'`. `'next'` runs the same per-step
  validation the built-in button does, so an external control gates identically.

Both are scoped by `wizard` — the wizard's name, `null` when unnamed. Name the
wizard whenever two can be on screen at once, or they share an empty scope.

A [`Select`'s option modal](../../../forms/fields/select.md#a-full-form-not-a-field-list)
does this for you: put a `navigation(false)` wizard in `createOptionForm()` and
the modal footer takes over, showing Back / Next until the last step and the
submit button only there.

## Methods

| Method | On | Description |
|--------|----|-------------|
| `activeStep(int)` | `Wizard` | Zero-based index of the step shown first |
| `skippable()` | `Wizard` | Allow jumping to any step from the indicator |
| `navigation(bool)` | `Wizard` | Render without the built-in Previous / Next row, for an outer surface to drive |
| `description(string)` | `Step` | Secondary line under the step label |
| `icon(string\|Icon)` | `Step` | Step icon |
| `columns(int)` | `Step` | Column grid for the step's child schema |
| `visible()` / `hidden()` | `Step` | Conditionally include a step (indices re-align automatically) |

## Related Docs

- [Tabs](tabs.md)
- [Modals — Multi-Step Wizard](../../modals.md#multi-step-wizard)
