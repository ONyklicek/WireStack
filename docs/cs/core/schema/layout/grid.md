---
order: 10
---

# Grid

Vícesloupcový grid layout pro uspořádání dětských komponent do sloupců. Sdílený
schema slovník — stejný layout se vykreslí ve formulářích i infolistech.

```php
use NyonCode\WireCore\Foundation\Schema\Grid;
```

## Použití

```php
Grid::make()
    ->columns(2)
    ->schema([
        TextInput::make('first_name')->columnSpan(1),
        TextInput::make('last_name')->columnSpan(1),
        Textarea::make('bio')->columnSpanFull(),
    ])
```

## Responzivní sloupce

Předejte mapu klíčovanou breakpointy pro měnění počtu sloupců podle velikosti obrazovky:

```php
Grid::make()
    ->columns([
        'default' => 1,
        'md' => 2,
        'xl' => 3,
    ])
    ->schema([...])
```

## Metody

| Metoda | Popis |
|--------|-------------|
| `columns(int\|array)` | Počet sloupců gridu nebo mapa klíčovaná breakpointy (výchozí: 2) |

Dětské komponenty používají `columnSpan(int)` a `columnSpanFull()` pro řízení své
šířky.

> Ve formulářích můžete také importovat tenký alias `NyonCode\WireForms\Components\Layout\Grid`
> (deprecated ve v2.0). Jen vymění form-specifický markup; preferujte kanonický
> schema `Grid` výše.

## Související dokumentace

- [Flex](flex.md)
- [Section](section.md)
- [Fieldset](fieldset.md)
