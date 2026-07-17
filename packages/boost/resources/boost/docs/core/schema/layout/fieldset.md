---
order: 10
---

# Fieldset

HTML fieldset with a legend for grouping related child components. Shared schema
vocabulary — the same layout renders in forms and infolists.

```php
use NyonCode\WireCore\Foundation\Schema\Fieldset;
```

## Usage

```php
Fieldset::make('address')
    ->label('Address')
    ->schema([
        TextInput::make('street'),
        TextInput::make('city'),
        TextInput::make('zip'),
    ])
    ->columns(3)
```

## Methods

| Method | Description |
|--------|-------------|
| `columns(int)` | Grid columns inside the fieldset |

> In forms you may also import the thin `NyonCode\WireForms\Components\Layout\Fieldset`
> alias (deprecated in v2.0). It only swaps in form-specific markup; prefer the
> canonical schema `Fieldset` above.

## Related Docs

- [Section](section.md)
- [Grid](grid.md)
