---
summary: A currency amount typed the way it is written, stored as a number.
---

# MoneyInput

An amount of money. The field shows the figure grouped as a person writes it —
`1 234,50` — while the column behind it keeps a number. Reach for it whenever a
price, a total or a balance is edited; a `TextInput::make()->numeric()` stores
the same number but makes the user read an ungrouped one.

```php
use NyonCode\WireForms\Components\MoneyInput;
```

## How It Works

**The currency is never part of the value.** It renders in the field's affix —
trailing by default, leading with [`currencyBefore()`](#currency) — so the input
holds a figure and only a figure. That is what makes reading it back
unambiguous.

**State is the written amount, and the conversion happens at the state
boundary.** `hydrateState()` writes a stored number out in this field's format;
`dehydrateState()` reads the typed text back into a number, rounded at the
field's precision. Nothing downstream of the form ever sees a separator.

**Grouping is applied while typing** by Alpine's `$money` mask, configured from
the same format. There is no roundtrip: the mask runs in the browser, and the
value it produces is exactly what the parser expects.

**Precision comes from the currency, keyed on how it is spelled.** `'Kč'` is the
colloquial Czech form and is written in whole crowns; the ISO code `'CZK'` keeps
hellers, as does every other currency. `decimals()` overrides both. The same
rule is the one `MoneyColumn` displays with — both read
`Foundation\ValueObjects\MoneyFormat`, so an amount is written the same way in a
form and in a table.

**Reading a number back is deliberately lenient.** Everything that is not a
digit or the decimal separator is discarded, so grouping written either way
(`1 234,50`, `1.234,50`) parses the same. One special case: in a comma format a
lone `.` with no comma anywhere is read as the decimal point — that is what a
numeric keypad emits, and discarding it would turn `1234.50` into an amount a
hundred times larger, silently.

**Validation goes through the amount, not the text.** Laravel's `numeric` would
reject the grouping this field writes on purpose, so the field contributes a
`MoneyAmount` rule instead: it parses first, then compares. That is also what
makes `minValue()` and `maxValue()` mean something here — on a text input they
would be HTML attributes the browser ignores.

**An application sets its money once.** The currency and both separators fall back to
`config('wire-forms.money.*')`, so a field states only what differs from the rest of the
application. `currency(null)` is still a choice, not an omission: it means a bare figure, and does
not read the default.

## Basic Usage

```php
MoneyInput::make('price')
```

Czech crowns with hellers, the currency trailing: `1 234,50 CZK`.

## Currency

```php
MoneyInput::make('price')
    ->currency('EUR')          // 1 234,50 EUR
```

```php
MoneyInput::make('price')
    ->currency('$')
    ->currencyBefore()         // $ 1 234,50
```

Placement is stated rather than guessed — a three-letter code says nothing about
the convention its country writes in. An explicit `prefix()` or `suffix()` wins
over the currency, which is how you put a unit on the other side:

```php
MoneyInput::make('rate')
    ->currency('EUR')
    ->suffix('/ hour')         // the currency then leads: EUR 1 234,50 / hour
    ->currencyBefore()
```

## Precision And Separators

```php
MoneyInput::make('price')
    ->currency('Kč')           // whole crowns, by convention
    ->decimals(2)              // …unless you say otherwise
```

```php
MoneyInput::make('price')
    ->currency('USD')
    ->separators('.', ',')     // 1,234.50 USD
```

## Minor Units

```php
MoneyInput::make('price_in_cents')
    ->currency('EUR')
    ->storeAsMinorUnits()      // the user types 12,34 — the column gets 1234
```

The user still reads and writes an amount; only the column changes. Rounding
happens at the field's precision, so a pasted third decimal cannot reach an
integer column as a truncated value.

## Bounds

```php
MoneyInput::make('price')
    ->minValue(0)              // no negative prices
    ->maxValue(1_000_000)
```

Both are validated against the parsed amount, and an error reports the bound in
the format it was set in: *"The price must be at least 0,00 CZK."*

## Extended Example

```php
use Livewire\Component;
use NyonCode\WireForms\Components\MoneyInput;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditProduct extends Component
{
    use WithForms;

    public array $data = [];

    public function mount(Product $product): void
    {
        $this->form->fill($product->attributesToArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(Product::class)
            ->statePath('data')
            ->schema([
                TextInput::make('name')->required(),
                MoneyInput::make('price')          // [tl! focus:start]
                    ->currency('CZK')
                    ->minValue(0)
                    ->required(),
                MoneyInput::make('cost_in_cents')
                    ->label('Purchase cost')
                    ->currency('EUR')
                    ->currencyBefore()
                    ->storeAsMinorUnits(),         // [tl! focus:end]
            ]);
    }
}
```

## MoneyInput API

The money surface. Everything else is `TextInput`'s — `->minValue()`,
`->maxValue()`, `->prefix()`, `->suffixAction()`, `->placeholder()` — documented
in [TextInput](text-input.md), and the shared field API in
[Form Fields](index.md).

```php
->currency(?string $currency, ?int $decimals = null)  // 'CZK'|'Kč'|'EUR'|'$'|null — default: config('wire-forms.money.currency')
->decimals(int $decimals)                             // overrides the currency's convention
->separators(string $decimal, string $thousands)      // default: config('wire-forms.money.*_separator')
->currencyBefore(bool $before = true)                 // '$ 1 234,50' rather than '1 234,50 $'
->storeAsMinorUnits(bool $condition = true)           // the column holds hellers/cents
->getCurrency(): ?string
->getDecimals(): int
->storesMinorUnits(): bool
->getMoneyFormat(): MoneyFormat                       // the vocabulary this field writes and reads with
```

## Related

- [TextInput](text-input.md) — the field this one extends, including `dynamicMask()`
- [Form Fields](index.md) — the shared field API
- [MoneyColumn](../../table/columns/money.md) — the same amount, displayed
- [Validation](../validation.md) — how implicit rules join the ones you write
