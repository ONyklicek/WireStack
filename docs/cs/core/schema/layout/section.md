---
order: 10
---

# Section

Sbalitelná sekce s nadpisem, popisem a ikonou. Vizuálně seskupuje dětské komponenty.
Sdílený schema slovník — stejný layout se vykreslí ve formulářích i infolistech.

```php
use NyonCode\WireCore\Foundation\Schema\Section;
```

## Použití

```php
Section::make('personal')
    ->label('Personal Information')
    ->description('Basic details about the user.')
    ->icon('user')
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email(),
    ])
    ->columns(2)
```

## Sbalitelné

```php
Section::make('advanced')
    ->label('Advanced Settings')
    ->collapsible()
    ->collapsed()      // začít sbalené
    ->schema([...])
```

## Kompaktní režim

```php
Section::make('info')
    ->compact()        // zmenšený padding
    ->schema([...])
```

## Aside layout

```php
Section::make('info')
    ->aside()          // label vlevo, obsah vpravo
    ->schema([...])
```

## Metody

| Metoda | Popis |
|--------|-------------|
| `description(string)` | Popis pod nadpisem |
| `icon(string)` | Ikona vedle nadpisu |
| `columns(int)` | Sloupce gridu uvnitř sekce |
| `collapsible()` | Povolit sbalování |
| `collapsed()` | Začít sbalené |
| `compact()` | Zmenšený padding |
| `aside()` | Layout vedle sebe |
| `headerActions(array)` | Akce vykreslené v hlavičce sekce (alias pro `actions()`) |

> Ve formulářích můžete také importovat tenký alias `NyonCode\WireForms\Components\Layout\Section`
> (deprecated ve v2.0). Jen vymění form-specifický markup; preferujte kanonický
> schema `Section` výše.

## Související dokumentace

- [Grid](grid.md)
- [Fieldset](fieldset.md)
- [Tabs](tabs.md)
