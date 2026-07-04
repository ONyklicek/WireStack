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

## Methods

| Method | On | Description |
|--------|----|-------------|
| `activeStep(int)` | `Wizard` | Zero-based index of the step shown first |
| `skippable()` | `Wizard` | Allow jumping to any step from the indicator |
| `description(string)` | `Step` | Secondary line under the step label |
| `icon(string\|Icon)` | `Step` | Step icon |
| `columns(int)` | `Step` | Column grid for the step's child schema |
| `visible()` / `hidden()` | `Step` | Conditionally include a step (indices re-align automatically) |

## Related Docs

- [Tabs](tabs.md)
- [Modals — Multi-Step Wizard](../../modals.md#multi-step-wizard)
