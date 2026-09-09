---
summary: "A callout inside a form: a message with a colour, an icon and an optional dismiss, rendered where a field would be."
---

# Alert

A coloured notice that sits in the schema where a field would. Reach for `Alert`
when a form needs to say something in place — "this account is suspended",
"changes here affect every user" — rather than after an action, which is what a
[notification](../../core/notifications/index.md) is for.

```php
use NyonCode\WireForms\Components\Display\Alert;
```

## How It Works

An alert is a **display component**: it renders output and never binds to form
state. It has no state path, takes no part in validation, and can be added or
removed without touching your data.

**It is the field-style alias of [`Callout`](../../core/schema/callout.md)**, and
that is not a figure of speech — both render `wire-core::partials.callout`, so
the box, the icon slot, the `role="alert"` and the dismiss button are literally
the same markup. Use `Alert` inside a form schema, `Callout` inside the shared
schema vocabulary; whatever you learn about one holds for the other.

**`message()` is `content()`.** It calls straight through, so the two are
interchangeable and setting both means the second one wins. `message()` reads
better in a form.

**The body is escaped.** The view emits `e($field->getContent())`, so
`message('<strong>Careful</strong>')` renders the tags as visible text. An alert
is for a sentence; markup belongs in [`Html`](html.md) or a
[`ViewField`](view-field.md).

**The colour vocabulary is shorter than it looks**, and this is the trap worth
knowing. Colours resolve through the shared alert palette, a closed `match` over
`success`/`emerald`, `green`, `warning`/`amber`, `yellow`, `danger`/`red`,
`black` and `white`. **Everything else falls through to informational blue** —
`->color('primary')` and `->color('purple')` both render blue, with no error. Use
the four shortcuts and treat anything else as a request that may not be honoured.

**Dismissing is Alpine and is not remembered.** The dismiss hides the box
client-side with no round trip, and the alert is back on the next render — after
a `wire:navigate`, or after any round trip the form makes. A notice that must
stay dismissed is application state, not an alert setting.

## Basic Usage

```php
Alert::make('irreversible')
    ->warning()
    ->icon('outline:exclamation-triangle')
    ->title('This cannot be undone')
    ->message('Deleting the project removes every task in it.')
```

## Colours

```php
Alert::make('a')->info();      // the default
Alert::make('a')->success();
Alert::make('a')->warning();
Alert::make('a')->danger();
```

`->color()` also takes `emerald`, `green`, `yellow`, `red`, `amber`, `black` and
`white`. Anything outside that list is info blue.

## Dismissible

```php
Alert::make('tip')
    ->info()
    ->dismissible()          // [tl! focus]
    ->message('You can drag rows to reorder them.')
```

## Showing It Only When It Applies

`visible()` is the shared surface every component carries, so an alert appears
and disappears with the form's state rather than being rendered empty:

```php
Alert::make('suspended')
    ->danger()
    ->title('Account suspended')
    ->message('This user cannot sign in until an administrator restores them.')
    ->visible(fn (): bool => $this->record?->is_suspended ?? false)   // [tl! focus]
```

## Extended Example

An edit form in a real Livewire host, with one alert that is always there and one
that appears only for suspended accounts:

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireForms\Components\Display\Alert;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditUser extends Component
{
    use WithForms;

    public User $record;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(User::class)
            ->schema([
                Alert::make('suspended')                            // [tl! focus:start]
                    ->danger()
                    ->icon('outline:no-symbol')
                    ->title('Account suspended')
                    ->message('This user cannot sign in until you restore them.')
                    ->visible(fn (): bool => $this->record->is_suspended),

                Alert::make('scope')
                    ->info()
                    ->dismissible()
                    ->message('Changes here apply the next time the user signs in.'),
                                                                     // [tl! focus:end]
                Section::make('profile')
                    ->label('Profile')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required(),
                        TextInput::make('email')->email()->required(),
                        Toggle::make('is_suspended')->label('Suspended')->live(),
                    ]),
            ])
            ->successMessage('User updated');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

The toggle is `live()` so the alert above appears the moment the account is
suspended, rather than on the next round trip.

## Alert API

```php
->message(string|Closure|null $message)   // the body — an alias of content(), and escaped
->content(string|Closure|null $content)   // the same thing, under the shared name
->title(string|Closure|null $title)       // bold line above the body
->color(string|Color $color)              // 'info' (default)|'success'|'warning'|'danger'|'emerald'|'green'|'amber'|'yellow'|'red'|'black'|'white' — anything else is info blue
->info()                                  // shortcut for color('info')
->success()                               // shortcut for color('success')
->warning()                               // shortcut for color('warning')
->danger()                                // shortcut for color('danger')
->icon(string|Icon|null $icon)            // leading icon
->dismissible(bool $condition = true)     // client-side dismiss, not remembered — default false
->getTitle(): ?string
->getContent(): ?string
->getColor(): string
->getColorClasses(): string
->getIcon(): ?string
->isDismissible(): bool
```

Labels, helper text, visibility and `columnSpan()` are the shared component
surface — see [Common Field API](index.md#common-field-api). Validation, `live()`
and defaults do not apply: an alert holds no state.

## Related

- [Callout](../../core/schema/callout.md) — the same box in the shared schema vocabulary
- [Placeholder](placeholder.md) — a line of text with no box around it
- [Html](html.md) — when the message needs markup
- [Notifications](../../core/notifications/index.md) — for a message that answers an action
