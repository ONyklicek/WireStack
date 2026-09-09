---
order: 20
summary: A soft, coloured notice box with a heading, an icon and an optional dismiss — its body from a schema or a plain string.
---

# Callout

A coloured notice inside a form or an infolist — "this cannot be undone", "your
trial ends in three days". Reach for it when the message belongs to one part of
a screen and should stay there. For something that appears in response to what
the user just did, and goes away again, you want a
[notification](../notifications/index.md) instead.

```php
use NyonCode\WireCore\Foundation\Schema\Callout;
```

## How It Works

A callout is a **layout component**: no value, no state path, safe to add or
remove without touching data. It renders a soft, bordered box carrying
`role="alert"`, with an optional icon on the left and an optional bold heading
above the body.

**The body has two sources and they are not equal.** Child components win:

1. Every visible child in `schema()` is rendered and concatenated.
2. **Only if that produced nothing** is `content()` used.

So a callout with both a schema and a `content()` string shows the schema and
silently drops the string. And there is a second difference between them:
children are `Htmlable`, so their markup is emitted as-is, while `content()` is
**escaped**. A `content('<strong>Careful</strong>')` renders the tags as visible
text. Markup in a callout goes through a child component, not through `content()`.

**The colour vocabulary is shorter than it looks.** `getAlertColorClasses()` is a
closed `match` over `success`/`emerald`, `green`, `warning`/`amber`, `yellow`,
`danger`/`red`, `black` and `white` — and **everything it does not recognise
falls through to informational blue**. `->color('primary')` and
`->color('purple')` are both blue, without an error. In practice: use the four
shortcuts, and treat anything else as a request that may not be honoured.

**Dismissing is Alpine and is not remembered.** `dismissible()` wraps the box in
`x-data="{ show: true }"` and hides it with `x-show`, so the dismiss costs no
round trip — and the callout is back on the next render, after a `wire:navigate`
or a form round trip. A notice that must stay dismissed is application state.

Heading, icon and body are each optional and each independently omitted from the
markup when absent; a callout with none of them is an empty coloured box.

## Basic Usage

```php
Callout::make()
    ->warning()
    ->icon('outline:exclamation-triangle')
    ->heading('Heads up')
    ->content('This action cannot be undone.')
```

`title()` is an alias for `heading()` if that reads better where you are.

## Colours

```php
Callout::make()->info();      // the default — same as ->color('info')
Callout::make()->success();
Callout::make()->warning();
Callout::make()->danger();
```

The four shortcuts are the whole practical vocabulary. `->color()` also takes
`emerald`, `green`, `yellow`, `red`, `amber`, `black` and `white`; every other
value renders as info blue.

## A Body With Markup In It

Because `content()` is escaped, anything richer than a sentence goes in the
schema:

```php
Callout::make()
    ->info()
    ->heading('Billing')
    ->schema([                                          // [tl! focus:start]
        Placeholder::make('plan')->content('Pro — renews 1 March'),
        Placeholder::make('seats')->content('12 of 20 used'),
    ])                                                   // [tl! focus:end]
```

Remember that a schema, once it renders anything, replaces `content()` entirely.

## Dismissible

```php
Callout::make()
    ->danger()
    ->dismissible()          // [tl! focus]
    ->heading('Payment failed')
    ->content('We could not charge your card.')
```

## Extended Example

A callout at the top of a form, shown only while the account is unverified:

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Callout;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditProfile extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(User::class)
            ->schema([
                Callout::make()                                   // [tl! focus:start]
                    ->warning()
                    ->icon('outline:exclamation-triangle')
                    ->heading('Your e-mail is not verified')
                    ->content('Some features stay locked until you confirm it.')
                    ->visible(fn (): bool => ! auth()->user()->hasVerifiedEmail()),
                                                                   // [tl! focus:end]
                Section::make('profile')
                    ->label('Profile')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required(),
                        TextInput::make('email')->email()->required(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

`visible()` comes from the shared layout surface, so the callout disappears
entirely — markup and all — once the condition stops holding.

## Standalone Tag

The same markup is a Blade tag, for a callout outside any schema:

```blade
<x-wire::callout color="warning" heading="Heads up">
    This action cannot be undone.
</x-wire::callout>
```

In a form, the [Alert](../../forms/fields/alert.md) display field is the
field-style alias of this component, and the
[modal](../actions/modals.md) surface uses the same palette.

## Callout API

```php
->heading(string|Closure|null $heading)   // bold line above the body
->title(string|Closure|null $title)       // alias of heading()
->content(string|Closure|null $content)   // plain-text body, ESCAPED, ignored when a schema renders
->color(string|Color $color)              // 'info' (default)|'success'|'warning'|'danger'|'emerald'|'green'|'amber'|'yellow'|'red'|'black'|'white' — anything else falls through to info blue
->info()                                  // shortcut for color('info')
->success()                               // shortcut for color('success')
->warning()                               // shortcut for color('warning')
->danger()                                // shortcut for color('danger')
->icon(string|Icon|null $icon)            // leading icon
->dismissible(bool $condition = true)     // client-side dismiss, not remembered — default false
->getHeading(): ?string
->getContent(): ?string
->getColor(): string
->getIcon(): ?string
->isDismissible(): bool
```

Everything else — `schema()`, `visible()`, `hidden()`, `columnSpan()` — is the
shared layout surface. See [Common Layout API](overview.md#common-layout-api).

## Related

- [Schema](overview.md) — the vocabulary this belongs to, and the shared surface
- [Empty State](empty-state.md) — the other prime component
- [Alert](../../forms/fields/alert.md) — the same box as a form display field
- [Notifications](../notifications/index.md) — for a message that answers an action
