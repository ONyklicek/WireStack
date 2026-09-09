---
summary: "Static text in the schema's flow: it reads like a field and holds no data."
---

# Placeholder

A line of text that sits in the schema and looks like a field — a label above,
the value below — but holds nothing. Reach for `Placeholder` to show a computed
or read-only value beside the inputs that can be edited: an invoice total, a
generated reference, "last signed in three days ago".

```php
use NyonCode\WireForms\Components\Display\Placeholder;
```

## How It Works

A placeholder is a **display component**: it renders output and never binds to
form state. No state path, no validation, no `live()` — and nothing it shows is
submitted with the form.

**It borrows a field's shape, not a field's behaviour.** The rendered markup is a
`label` when one is set, the content below it, and `helperText()` under that. So
a placeholder lines up with the inputs around it without being one.

**Content is escaped by default.** `content('<strong>Careful</strong>')` renders
the tags as visible text. Two ways to opt out, and they are the same switch:

- `->allowHtml()` turns raw output on for whatever `content()` holds.
- `->html($content)` sets the content **and** turns it on, in one call.

Neither sanitises anything. The escape is the only thing standing between a
value and the page, so anything reaching `allowHtml()` must already be trusted —
a string you built, not one a user typed.

**Content may be a closure**, resolved on every read, which is what makes a
placeholder useful for a value that depends on the rest of the form rather than
on a stored field.

## Basic Usage

```php
Placeholder::make('reference')
    ->label('Reference')
    ->content('INV-2026-0184')
```

## A Value Computed From The Form

Because the content is resolved on read, it can look at whatever the host knows:

```php
Placeholder::make('total')
    ->label('Total')
    ->content(fn (): string => number_format($this->lineTotal(), 2).' CZK')   // [tl! focus]
```

For the value to refresh as the user types, the fields it depends on need
`live()` — the placeholder itself has nothing to react with.

## Letting Markup Through

```php
Placeholder::make('status')
    ->label('Status')
    ->html('<span class="text-red-600 font-medium">Overdue</span>')   // [tl! focus]
```

`html()` is `content()` plus `allowHtml()`. Use it only with a string you built
yourself.

## Extended Example

An invoice form in a real Livewire host, where two placeholders show values the
user cannot edit but needs to see:

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Grid;
use NyonCode\WireForms\Components\Display\Placeholder;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditInvoice extends Component
{
    use WithForms;

    public Invoice $record;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(Invoice::class)
            ->schema([
                Grid::make()->columns(2)->schema([
                    Placeholder::make('number')                       // [tl! focus:start]
                        ->label('Invoice number')
                        ->content(fn (): string => $this->record->number)
                        ->helperText('Assigned when the invoice was issued.'),

                    Placeholder::make('issued_at')
                        ->label('Issued')
                        ->content(fn (): string => $this->record->issued_at->format('j F Y')),
                                                                       // [tl! focus:end]
                    TextInput::make('customer_reference')
                        ->label('Customer reference')
                        ->maxLength(60),

                    TextInput::make('due_days')
                        ->label('Due in (days)')
                        ->numeric(),
                ]),
            ])
            ->successMessage('Invoice updated');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

Neither placeholder appears in the saved data — they are not fields, so there is
no key for them in `$data` and nothing for validation to reach.

## Placeholder API

```php
->content(string|Closure|null $content)   // the text; escaped unless allowHtml() is on
->allowHtml(bool $condition = true)       // render the content as raw HTML — default false
->html(string|Closure $content)           // content() and allowHtml() in one call
->getContent(): ?string
->isHtmlContent(): bool
```

Labels, helper text, visibility and `columnSpan()` are the shared component
surface — see [Common Field API](index.md#common-field-api). Validation, `live()`
and defaults do not apply: a placeholder holds no state.

## Related

- [Html](html.md) — markup with no label and no field shape around it
- [ViewField](view-field.md) — when the thing to show is a whole Blade partial
- [Alert](alert.md) — the same idea inside a coloured box
- [Hidden](hidden.md) — the opposite: a value carried but never shown
