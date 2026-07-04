---
order: 10
---

# Grid

Multi-column grid layout for arranging child components in columns. Shared
schema vocabulary — the same layout renders in forms and infolists.

```php
use NyonCode\WireCore\Foundation\Schema\Grid;
```

## Usage

```php
Grid::make()
    ->columns(2)
    ->schema([
        TextInput::make('first_name')->columnSpan(1),
        TextInput::make('last_name')->columnSpan(1),
        Textarea::make('bio')->columnSpanFull(),
    ])
```

## Responsive Columns

Pass a breakpoint-keyed map to vary the column count per screen size:

```php
Grid::make()
    ->columns([
        'default' => 1,
        'md' => 2,
        'xl' => 3,
    ])
    ->schema([...])
```

## Methods

| Method | Description |
|--------|-------------|
| `columns(int\|array)` | Number of grid columns, or a breakpoint-keyed map (default: 2) |

Child components use `columnSpan(int)` and `columnSpanFull()` to control their
width.

> In forms you may also import the thin `NyonCode\WireForms\Components\Layout\Grid`
> alias (deprecated in v2.0). It only swaps in form-specific markup; prefer the
> canonical schema `Grid` above.

## Related Docs

- [Flex](flex.md)
- [Section](section.md)
- [Fieldset](fieldset.md)
