---
order: 10
summary: A single horizontal axis for a row of controls that share the space, stacking vertically on small screens.
---

# Flex

One row, whose children share the space between them. Reach for `Flex` when a
[`Grid`](grid.md) is too rigid — a search box that should take whatever is left
next to a fixed-width button, a toolbar, a pair of panels of unequal weight. A
grid gives every child the same slice; a flex row lets them negotiate.

> Not to be confused with the table
> [`SplitColumn`](../../../table/columns/split.md), which splits space *within a
> single table cell*.

```php
use NyonCode\WireCore\Foundation\Schema\Flex;
```

## How It Works

A flex row is a **layout component**: no value, no state path, free to add or
remove without touching data.

**It is a column first and a row second.** The rendered element is always
`flex flex-col`, and the breakpoint from `from()` is what turns it horizontal —
`md:flex-row` by default. So on a phone the children stack, which is nearly
always what you want and is not something you have to ask for.

**Every child is wrapped**, in a `min-w-0` box, and in `flex-1` as well while
`grow()` is on (the default). Two consequences worth knowing:

- `min-w-0` is why a long, unbreakable string inside a child does not blow the
  row out sideways. Flex items refuse to shrink below their content otherwise;
  this is the fix, applied for you.
- `flex-1` is why children come out **evenly sized regardless of their content**.
  Turn it off with `grow(false)` when you want natural widths — a button that
  should be button-sized next to an input that takes the rest.

**Three of the setters have closed vocabularies, and anything outside them is
silently ignored:**

- `from()` understands `sm`, `md` and `lg`. **Anything else falls back to `md`** —
  `from('xl')` does not fail, it just behaves as if you had not written it.
- `justify()` understands `start`, `end`, `center`, `between`, `around`, `evenly`;
  an unknown value emits no class at all.
- `align()` understands `start`, `end`, `center`, `stretch`, `baseline`, the same
  way.

`gap()` is a step on Tailwind's 0–12 spacing scale, default `4`, and is clamped
into that range rather than rejected.

Children are filtered by their own `visible()` condition before rendering, so a
hidden child gives its space back to the others rather than leaving a hole.

## Basic Usage

```php
Flex::make()->schema([
    TextInput::make('first_name'),
    TextInput::make('last_name'),
])
```

Two equal halves from `md` up, stacked below it. That is the whole default.

## Natural Widths Instead Of Equal Ones

```php
Flex::make()
    ->grow(false)          // [tl! focus]
    ->align('end')
    ->schema([
        TextInput::make('search'),
        Button::make('go')->label('Search'),
    ])
```

With `grow(false)` each child takes the width it actually needs. `align('end')`
lines the button up with the bottom of the input rather than the top, which is
what you want whenever one child has a label and the other does not.

## Controlling The Row

```php
Flex::make()
    ->from('lg')          // go horizontal at lg instead of md
    ->justify('between')  // push the children apart along the row
    ->align('center')     // centre them across the row
    ->gap(6)              // wider spacing (Tailwind scale 0–12)
    ->wrap()              // let children fall onto a second line
    ->schema([...])
```

`wrap()` matters once `grow(false)` is on: without growing, children keep their
natural widths, and on a narrow screen they would otherwise be squeezed rather
than wrapped.

## Extended Example

A filter bar above a table, in a real Livewire host. The inputs share the space;
the button keeps its own width:

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Flex;
use NyonCode\WireForms\Components\DateTimePicker;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class OrderFilters extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Flex::make()                                   // [tl! focus:start]
                    ->from('md')
                    ->align('end')
                    ->gap(3)
                    ->schema([
                        TextInput::make('search')->label('Search orders'),
                        Select::make('status')->options([
                            'open' => 'Open',
                            'shipped' => 'Shipped',
                        ]),
                        DateTimePicker::make('placed_after')->asDate()->label('Placed after'),
                    ]),                                         // [tl! focus:end]
            ]);
    }
}
```

Three controls of equal width from `md` up, stacked on a phone, their bottom
edges lined up because one of them carries a longer label than the others.

## Flex API

```php
->from(string $breakpoint)     // 'sm'|'md'|'lg' — default 'md'; anything else falls back to 'md'
->justify(string $justify)     // 'start'|'end'|'center'|'between'|'around'|'evenly' — unset by default
->align(string $align)         // 'start'|'end'|'center'|'stretch'|'baseline' — unset by default
->gap(int $gap)                // Tailwind spacing step 0–12 — default 4
->wrap(bool $condition = true)  // allow a second line — default false
->grow(bool $condition = true)  // children fill the row evenly — default true
->getFrom(): string
->isWrap(): bool
->isGrow(): bool
```

Everything else — `label()`, `schema()`, `visible()`, `columnSpan()` — is the
shared layout surface. See
[Common Layout API](../overview.md#common-layout-api).

## Related

- [Schema](../overview.md) — the vocabulary this belongs to, and the shared surface
- [Grid](grid.md) — equal columns, when the children really are peers
- [Section](section.md) — a heading and a fold around a group
- [Fieldset](fieldset.md) — a legend, when the grouping carries meaning
