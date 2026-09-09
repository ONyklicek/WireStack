---
order: 10
summary: An HTML fieldset with a legend, for grouping related components — the same layout in a form and in an infolist.
---

# Fieldset

A real HTML `<fieldset>` with a `<legend>`. Reach for it when a handful of fields
belong together and the grouping is part of the *meaning* — an address, a billing
block — rather than only part of the look. A screen reader announces the legend
before each field inside it, which is the whole reason to prefer this over a
[`Grid`](grid.md) with a heading above it.

```php
use NyonCode\WireCore\Foundation\Schema\Fieldset;
```

## How It Works

A fieldset is a **layout component**: it holds no value and no state path, so it
can be added or removed without touching your data. It renders a bordered box,
puts `label()` in the legend, and lays its children out in a grid inside.

**No label, no legend.** The `<legend>` element is only emitted when a label is
set, so `Fieldset::make('address')` with nothing else renders a bordered box with
no heading — which is a valid thing to want, and a surprise if you expected the
name to become the legend. Unlike a field, a fieldset does *not* headline its
name into a label for you: write `->label('Address')`.

**The column count defaults to 1**, not 2 — a fieldset is a grouping first and a
grid second. It resolves differently depending on the shape you pass, and this is
the part worth knowing:

- **An int** goes through a small local map that understands **1, 2, 3 and 4**,
  reflowing at `sm`, then `md`, then `lg`. `columns(6)` matches nothing and the
  children render in a single column with no grid class at all.
- **A breakpoint map** goes through the same `ResponsiveGrid` a [`Grid`](grid.md)
  uses — `['default' => 1, 'md' => 3]` — with the full 1–12 range.

So the two shapes are not just two spellings here: **if you want more than four
columns, or control over which breakpoint reflows, pass the map.**

Children are filtered by their own `visible()` condition before rendering, and
the fieldset closes up around anything hidden.

## Basic Usage

```php
Fieldset::make('address')
    ->label('Address')
    ->schema([
        TextInput::make('street'),
        TextInput::make('city'),
        TextInput::make('zip'),
    ])
```

## Laying The Group Out

```php
Fieldset::make('address')
    ->label('Address')
    ->columns(3)                       // [tl! focus]
    ->schema([
        TextInput::make('street')->columnSpanFull(),
        TextInput::make('city'),
        TextInput::make('zip'),
    ])
```

Street takes the whole row; city and zip share the next one. For anything past
four columns, or a different reflow point, use the map form:

```php
Fieldset::make('address')
    ->label('Address')
    ->columns(['default' => 1, 'lg' => 6])   // [tl! focus]
    ->schema([...])
```

## Disabling A Whole Group

`disabled()` comes from the shared layout surface and cascades to every field
inside, which is one condition instead of the same one written on each field:

```php
Fieldset::make('billing')
    ->label('Billing address')
    ->disabledWhen('same_as_shipping')   // [tl! focus]
    ->schema([
        TextInput::make('billing_street'),
        TextInput::make('billing_city'),
    ])
```

## Extended Example

Two fieldsets in a real Livewire host, where the second one switches itself off
when a toggle above it is on:

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Fieldset;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditCustomer extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(Customer::class)
            ->schema([
                Fieldset::make('shipping')                     // [tl! focus:start]
                    ->label('Shipping address')
                    ->columns(2)
                    ->schema([
                        TextInput::make('ship_street')->columnSpanFull(),
                        TextInput::make('ship_city'),
                        TextInput::make('ship_zip'),
                    ]),

                Toggle::make('same_as_shipping')->live(),

                Fieldset::make('billing')
                    ->label('Billing address')
                    ->columns(2)
                    ->disabledWhen('same_as_shipping')
                    ->schema([
                        TextInput::make('bill_street')->columnSpanFull(),
                        TextInput::make('bill_city'),
                        TextInput::make('bill_zip'),
                    ]),                                         // [tl! focus:end]
            ]);
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

The toggle is `live()` because `disabledWhen` is re-evaluated on render — without
it the billing block would not switch off until the next round trip.

## Fieldset API

```php
->columns(int|array $columns)     // 1 (default). int understands 1–4 only;
                                  // ['default' => 1, 'lg' => 6] for anything else
->getColumns(): int|array
```

Everything else — `label()`, `schema()`, `visible()`, `disabled()`,
`columnSpan()` — is the shared layout surface. See
[Common Layout API](../overview.md#common-layout-api).

## Related

- [Schema](../overview.md) — the vocabulary this belongs to, and the shared surface
- [Grid](grid.md) — the same columns with no box and no legend
- [Section](section.md) — a heading, a description and a fold instead of a legend
- [Flex](flex.md) — one shared row rather than a column grid
