---
order: 10
---

# Flex

Arranges child components side by side on a single horizontal (flexbox) axis,
stacking vertically on small screens. Use it when you want a row of controls or
panels that grow to share the space, rather than a fixed column grid.

> Not to be confused with the table
> [`SplitColumn`](../../../table/columns/split.md), which splits space *within a
> single table cell*.

```php
use NyonCode\WireCore\Foundation\Schema\Flex;
```

## Usage

```php
Flex::make()->schema([
    TextInput::make('first_name'),
    TextInput::make('last_name'),
])
```

By default children grow to share the row evenly and the row turns horizontal at
the `md` breakpoint, stacking vertically below it.

## Controlling the Layout

```php
Flex::make()
    ->from('lg')          // go horizontal at the lg breakpoint instead of md
    ->justify('between')  // distribute children along the main axis
    ->align('center')     // cross-axis alignment
    ->gap(6)              // spacing between children (Tailwind gap scale 0–12)
    ->grow(false)         // keep natural widths instead of filling the row
    ->wrap()              // allow children to wrap onto multiple lines
    ->schema([...])
```

## Methods

| Method | Description |
|--------|-------------|
| `from(string)` | Breakpoint at which children lay out horizontally: `sm`, `md` (default), or `lg` |
| `justify(string)` | Main-axis distribution: `start`, `end`, `center`, `between`, `around`, `evenly` |
| `align(string)` | Cross-axis alignment: `start`, `end`, `center`, `stretch`, `baseline` |
| `gap(int)` | Space between children on the Tailwind gap scale (0–12, default 4) |
| `grow(bool)` | Whether children grow to fill the row evenly (default `true`) |
| `wrap(bool)` | Allow children to wrap onto multiple lines (default `false`) |

## Related Docs

- [Grid](grid.md)
- [Section](section.md)
