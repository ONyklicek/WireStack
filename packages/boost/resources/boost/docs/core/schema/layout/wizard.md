---
order: 10
summary: A step indicator over a set of panels with Previous and Next — the standalone counterpart of the action-modal wizard.
---

# Wizard

A sequence. `Wizard` is [`Tabs`](tabs.md) for the case where the panels have an
order and you want the user to move through them one at a time — a sign-up flow,
an import, anything long enough that showing all of it at once would put people
off. It is the standalone counterpart to the
[action-modal wizard](../../modals.md#multi-step-wizard).

```php
use NyonCode\WireCore\Foundation\Schema\Step;
use NyonCode\WireCore\Foundation\Schema\Wizard;
```

## How It Works

`Wizard` is the indicator and the navigation; each `Step` is one panel. Both are
layout components — no value, no state path.

**Every step stays in the DOM**, exactly as a tab does, so nested fields are
submitted and validated together on the final submit regardless of which step is
showing. Nothing has to be visited first for its data to count.

**Only `Step` children are rendered.** `getSteps()` filters the schema to visible
`Step` instances, so a field placed directly inside `Wizard::make()->schema([...])`
is silently dropped. Fields go inside a step.

**Name the wizard when two can be on screen at once.** Steps are addressed by the
wizard's name, so `Wizard::make('signup')` validates independently of another
wizard beside it; an unnamed wizard resolves to the first one in the schema, and
two unnamed ones share an empty scope.

On desktop each indicator circle carries its step's label and description; on
mobile the indicator collapses to numbered circles with the active step's label
and description below it. A step's own children lay out in a grid whose int case
understands **1 to 4** columns — pass a breakpoint map for more.

## Basic Usage

```php
Wizard::make()->schema([
    Step::make('Account')->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email()->required(),
    ]),
    Step::make('Company')->schema([
        TextInput::make('company_name'),
    ]),
])
```

## Labelling The Steps

```php
Wizard::make()->schema([
    Step::make('Account')
        ->description('How we reach you')     // [tl! focus]
        ->icon('outline:user')
        ->columns(2)
        ->schema([
            TextInput::make('first_name')->required(),
            TextInput::make('last_name')->required(),
        ]),
    Step::make('Company')
        ->description('Billing details')      // [tl! focus]
        ->icon('outline:building-office')
        ->schema([
            TextInput::make('vat_number'),
        ]),
])
```

## Per-Step Validation

**Next validates before it advances — on the server.** Inside a Livewire host
(`WithForms`, or a table action modal) pressing Next runs the rules the current
step's fields declare — `rules()`, `required()`, repeater item rules — scoped to
that step. On failure the wizard stays where it is and the errors render in the
active panel; steps the user has not reached yet are never flagged early.

Three behaviours follow from that, each of which you would otherwise have to
build:

- **A failed submit jumps to the first errored step**, so a message from step one
  is never stranded in a panel nobody is looking at.
- **Dynamic steps stay in sync.** When a `visible()` condition adds or removes a
  step mid-form — after a `live()` field's round trip — the indicator and the
  navigation re-align and the active step is clamped into the rendered range.
- **Jumping via a `skippable()` indicator skips validation**, the same as
  Filament. Skippable means "let me look ahead", not "let me submit incomplete".

Rendered **outside** a Livewire host, Next falls back to plain client-side
navigation and the form validates on submit as before.

## Starting Elsewhere, And Letting People Skip

```php
Wizard::make('onboarding')
    ->activeStep(1)     // open on the second step — [tl! focus]
    ->skippable()       // the indicator becomes clickable, without validating — [tl! focus]
    ->schema([...])
```

`activeStep()` is zero-based and counts only visible steps.

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

Both are scoped by `wizard` — the wizard's name, `null` when unnamed.

A [`Select`'s option modal](../../../forms/fields/select.md#a-full-form-not-a-field-list)
does this for you: put a `navigation(false)` wizard in `createOptionForm()` and
the modal footer takes over, showing Back / Next until the last step and the
submit button only there.

## Extended Example

An onboarding flow in a real Livewire host, with a step that only appears for
business accounts:

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Step;
use NyonCode\WireCore\Foundation\Schema\Wizard;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class Onboarding extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(Account::class)
            ->schema([
                Wizard::make('onboarding')->schema([              // [tl! focus:start]
                    Step::make('Account')
                        ->description('How we reach you')
                        ->icon('outline:user')
                        ->columns(2)
                        ->schema([
                            TextInput::make('name')->required(),
                            TextInput::make('email')->email()->required(),
                            Select::make('type')
                                ->options(['personal' => 'Personal', 'business' => 'Business'])
                                ->live()
                                ->columnSpanFull(),
                        ]),

                    Step::make('Company')
                        ->description('Billing details')
                        ->icon('outline:building-office')
                        ->visible(fn (array $get): bool => $get('type') === 'business')
                        ->schema([
                            TextInput::make('company_name')->required(),
                            TextInput::make('vat_number')->required(),
                        ]),

                    Step::make('Done')
                        ->description('Review and finish')
                        ->schema([
                            TextInput::make('referral_code'),
                        ]),
                ]),                                                // [tl! focus:end]
            ])
            ->successMessage('Welcome aboard');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

The type select is `live()` so choosing "Business" adds the company step
immediately; choosing "Personal" removes it and the indicator re-numbers itself.
Next will not leave the account step until name and e-mail are valid.

## Wizard API

```php
->activeStep(int $index)             // zero-based, visible steps only — default 0
->skippable(bool $condition = true)  // the indicator jumps steps without validating — default false
->navigation(bool $condition = true) // false renders no Previous/Next row — default true
->getActiveStep(): int
->isSkippable(): bool
->hasNavigation(): bool
->getSteps(): array                  // the visible Step children, re-indexed
```

## Step API

```php
->description(string|Closure|null $description)  // the secondary line under the step label
->icon(string|Icon|null $icon)                   // the indicator icon
->columns(int|array $columns)                    // 1 (default); int understands 1–4, or a breakpoint map
->getDescription(): ?string
->getIcon(): ?string
->getColumns(): int|array
```

Both carry the shared layout surface — `label()`, `schema()`, `visible()`,
`hidden()`, `disabled()`. See
[Common Layout API](../overview.md#common-layout-api).

## Related

- [Schema](../overview.md) — the vocabulary this belongs to, and the shared surface
- [Tabs](tabs.md) — the same panels when they are alternatives, not a sequence
- [Modals — Multi-Step Wizard](../../modals.md#multi-step-wizard) — the same layout inside an action
- [Section](section.md) — grouping without hiding anything
