---
order: 10
---

# Fieldset

HTML fieldset s legendou pro seskupení souvisejících dětských komponent. Sdílený
schema slovník — stejný layout se vykreslí ve formulářích i infolistech.

```php
use NyonCode\WireCore\Foundation\Schema\Fieldset;
```

## Použití

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

## Metody

| Metoda | Popis |
|--------|-------------|
| `columns(int)` | Sloupce gridu uvnitř fieldsetu |

> Ve formulářích můžete také importovat tenký alias `NyonCode\WireForms\Components\Layout\Fieldset`
> (deprecated ve v2.0). Jen vymění form-specifický markup; preferujte kanonický
> schema `Fieldset` výše.

## Související dokumentace

- [Section](section.md)
- [Grid](grid.md)
