---
summary: Your own Blade view rendered in the schema's flow, with the field's state handed to it.
---

# ViewField

Your own Blade partial, rendered in the schema where a field would be. Reach for
`ViewField` when what you need to show is more than a line of text — a preview of
the order being edited, a chart, a map, a small component of your own — and you
would rather write it in Blade than build a
[custom field](../custom-fields.md).

```php
use NyonCode\WireForms\Components\Display\ViewField;
```

## How It Works

A view field is a **display component**: it renders output and never binds to
form state. No state path, no validation, nothing submitted.

**A view wins over content.** The rendered markup checks `getView()` first and
falls back to `getContent()` only when no view is set. So a component with both
shows the view and silently ignores the string — set one or the other.

**The partial is `@include`d, not rendered in isolation.** Two things follow, and
the second is the useful one:

- Whatever `viewData()` holds arrives as variables, so `['total' => 120]` is
  `$total` in the partial.
- Blade's `@include` also passes the **including scope**, so `$field` — the
  `ViewField` itself — is available without being handed over. That is how a
  partial reaches `$field->getLabel()` or your own subclass's methods.

`viewData()` may be a closure, resolved on every read, which is what makes the
partial able to show something that depends on the current state rather than on
what was true when the schema was declared.

**`escape()` reads backwards, and it is worth reading twice.** It sets "render as
HTML" to the *opposite* of its argument, and the property starts `false`:

- Content is **escaped by default**. Nothing needs to be called.
- `->escape()` — the argument defaults to `true` — therefore does nothing at all.
- `->escape(false)` is what turns raw HTML **on**.

This only affects the `content()` fallback. A partial rendered through `view()`
is Blade and escapes whatever it escapes itself.

## Basic Usage

```php
ViewField::make('preview')
    ->label('Preview')
    ->view('forms.order-preview')
```

```blade
{{-- resources/views/forms/order-preview.blade.php --}}
<div class="rounded border border-gray-200 p-3 text-sm dark:border-gray-700">
    <p class="font-medium">{{ $field->getLabel() }}</p>
    <p>{{ $lines }} lines · {{ $total }}</p>
</div>
```

## Handing It Data

```php
ViewField::make('preview')
    ->view('forms.order-preview')
    ->viewData(fn (): array => [                        // [tl! focus]
        'lines' => count($this->data['items'] ?? []),
        'total' => number_format($this->orderTotal(), 2),
    ])
```

A closure rather than an array, so the numbers are the ones on screen now. For
them to refresh as the user types, the fields they depend on need `live()`.

## Without A View

A `ViewField` with `content()` and no `view()` is a
[`Placeholder`](placeholder.md) with the escaping switch inverted. Prefer
`Placeholder` for that; use `ViewField` when there is a partial.

## Extended Example

An order form in a real Livewire host, where a partial previews what is being
built as it is built:

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Grid;
use NyonCode\WireForms\Components\Display\ViewField;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class BuildOrder extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(Order::class)
            ->schema([
                Grid::make()->columns(2)->schema([
                    Select::make('product_id')
                        ->options(Product::pluck('name', 'id'))
                        ->live()
                        ->required(),

                    TextInput::make('quantity')
                        ->numeric()
                        ->default(1)
                        ->live()
                        ->required(),

                    ViewField::make('preview')                       // [tl! focus:start]
                        ->label('Order preview')
                        ->view('forms.order-preview')
                        ->viewData(fn (): array => [
                            'product' => Product::find($this->data['product_id'] ?? null),
                            'quantity' => (int) ($this->data['quantity'] ?? 0),
                        ])
                        ->columnSpanFull(),                           // [tl! focus:end]
                ]),
            ])
            ->successMessage('Order placed');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

Both inputs are `live()`, which is what makes the preview follow them: the view
field has nothing to react with on its own — it is redrawn when the form is.

## ViewField API

```php
->view(string $view)                      // the Blade view; wins over content()
->viewData(array|Closure $data)           // variables for the view, resolved on read
->content(string|Closure|null $content)   // fallback when no view is set; escaped by default
->escape(bool $condition = true)          // INVERTED: escape(false) turns raw HTML ON, escape() is a no-op
->getView(): ?string
->getViewData(): array
->getContent(): ?string
->isHtmlContent(): bool
```

Labels, helper text, visibility and `columnSpan()` are the shared component
surface — see [Common Field API](index.md#common-field-api). Validation, `live()`
and defaults do not apply: a view field holds no state.

## Related

- [Placeholder](placeholder.md) — a line of text, escaped by default
- [Html](html.md) — a string of markup with no partial behind it
- [Custom fields](../custom-fields.md) — when the thing does need to hold a value
- [Reactive fields](../reactive-fields.md) — why the inputs it watches need `live()`
