---
order: 10
summary: A tab bar over a set of panels, switched in the browser — every panel stays in the DOM, so validation sees them all.
---

# Tabs

A tab bar over a set of panels. Reach for `Tabs` when the groups of a long form
are **alternatives** — Profile / Preferences / Security — rather than a sequence
you expect somebody to walk through in order. When it *is* a sequence, use a
[`Wizard`](wizard.md).

```php
use NyonCode\WireCore\Foundation\Schema\Tab;
use NyonCode\WireCore\Foundation\Schema\Tabs;
```

## How It Works

`Tabs` is the bar; each `Tab` is one panel. Both are layout components — no
value, no state path — so tabs can be added, removed or reordered without
touching data.

**Switching is Alpine, not Livewire.** The active index lives in an `x-data` on
the wrapper and each panel is toggled with `x-show`, so changing tab is instant
and costs no round trip. The server is never told which tab is open.

**Every panel stays in the DOM.** This is the important consequence and the
reason the layout is safe in a form: nested fields are submitted and validated
together regardless of which tab is showing. A required field on the third tab
still blocks the submit, and its error message renders in the panel it belongs
to. Nothing needs to be "activated" first.

**Only `Tab` children are rendered.** `getTabs()` filters the schema down to
`Tab` instances that are visible — anything else you put directly inside
`Tabs::make()->schema([...])`, such as a bare `TextInput`, is **silently
dropped**. Fields go inside a tab, never beside one.

**Hidden tabs are removed and the rest re-indexed**, so `activeTab(1)` always
means "the second tab that is actually rendered". A tab hidden by a condition
never leaves a gap in the bar or shifts the active panel onto the wrong content.

A tab's label falls back to `Str::headline()` of its name, so `Tab::make('Profile')`
needs no `->label()`. On narrow screens the bar scrolls horizontally rather than
wrapping — wrapping intrinsic-width tabs reads as two ragged rows.

`Tab` lays its own children out in a grid whose int case understands **1 to 4**
columns; pass a breakpoint map (`['default' => 1, 'lg' => 6]`) for anything
beyond that.

## Basic Usage

```php
Tabs::make()->schema([
    Tab::make('Profile')->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email()->required(),
    ]),
    Tab::make('Preferences')->schema([
        Toggle::make('newsletter'),
    ]),
])
```

## Icons, Columns And A Starting Tab

```php
Tabs::make()
    ->activeTab(1)                          // second tab open first — [tl! focus]
    ->schema([
        Tab::make('Profile')
            ->icon('outline:user')
            ->columns(2)
            ->schema([
                TextInput::make('first_name'),
                TextInput::make('last_name'),
            ]),
        Tab::make('Security')
            ->icon('outline:lock-closed')
            ->schema([
                TextInput::make('password')->password(),
            ]),
    ])
```

`activeTab()` is zero-based and counts only visible tabs.

## Hiding A Tab

A tab takes the shared `visible()` / `hidden()` conditions, so a whole panel can
belong to one role and not another — one condition instead of the same one on
every field inside it:

```php
Tab::make('Billing')
    ->visible(fn (): bool => auth()->user()->can('manageBilling'))   // [tl! focus]
    ->schema([...])
```

## Extended Example

An account screen in a real Livewire host. Three panels, one of them
conditional, all validating together on submit:

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Tab;
use NyonCode\WireCore\Foundation\Schema\Tabs;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditAccount extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(User::class)
            ->schema([
                Tabs::make()->schema([                              // [tl! focus:start]
                    Tab::make('Profile')
                        ->icon('outline:user')
                        ->columns(2)
                        ->schema([
                            TextInput::make('first_name')->required(),
                            TextInput::make('last_name')->required(),
                            TextInput::make('email')->email()->required()->columnSpanFull(),
                        ]),

                    Tab::make('Preferences')
                        ->icon('outline:adjustments-horizontal')
                        ->schema([
                            Toggle::make('newsletter'),
                            Toggle::make('product_updates'),
                        ]),

                    Tab::make('Billing')
                        ->icon('outline:credit-card')
                        ->visible(fn (): bool => auth()->user()->can('manageBilling'))
                        ->schema([
                            TextInput::make('vat_number'),
                        ]),
                ]),                                                  // [tl! focus:end]
            ])
            ->successMessage('Account updated');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

A user without the billing permission sees two tabs, and the third's fields are
neither rendered nor validated.

## Tabs API

```php
->activeTab(int $index)      // zero-based, counting visible tabs only — default 0
->getActiveTab(): int
->getTabs(): array           // the visible Tab children, re-indexed
```

## Tab API

```php
->icon(string|Icon|null $icon)    // rendered before the label in the bar
->columns(int|array $columns)     // 1 (default); int understands 1–4, or a breakpoint map
->getIcon(): ?string
->getColumns(): int|array
```

Both carry the shared layout surface — `label()`, `schema()`, `visible()`,
`hidden()`, `disabled()`. See
[Common Layout API](../overview.md#common-layout-api).

## Related

- [Schema](../overview.md) — the vocabulary this belongs to, and the shared surface
- [Wizard](wizard.md) — the same panels as a sequence, with per-step validation
- [Section](section.md) — when the groups are all visible at once
- [Grid](grid.md) — the column grid a tab lays its own fields out in
