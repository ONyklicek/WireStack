# BelongsToSelect

`BelongsToSelect` je select pole vědomé si relace pro `belongsTo` asociace.

## Základní použití

```php
use NyonCode\WireForms\Components\BelongsToSelect;

BelongsToSelect::make('company_id')
    ->relationship('company', 'name')
    ->label('Company')
    ->searchable()
```

To resolvuje options ze souvisejícího modelu místo vyžadování manuálního pole `options()`.

`searchable()` select bez `preload()` hledá v související tabulce na serveru, jak uživatel
píše (párováním title atributu, omezeno na 50 výsledků), a resolvuje label vybrané
hodnoty jedním klíčovaným lookupem — plný seznam options se nikdy neposílá klientovi.
Přidejte `preload()` pro načtení celého seznamu předem a filtrování na straně klienta. Explicitní
callback `getSearchResultsUsing()` (zděděný ze `Select`) přepíše vestavěné hledání.

## Běžné volby

```php
BelongsToSelect::make('company_id')
    ->relationship('company', 'name')
    ->searchable()
    ->preload()
    ->required()
```

| Metoda | Účel |
|--------|---------|
| `relationship('company', 'name')` | Resolvovat options z relace a title sloupce |
| `searchable()` | Hledat v související tabulce na serveru, jak uživatel píše |
| `preload()` | Načíst plný seznam options okamžitě a filtrovat na straně klienta |
| `modifyOptionsQueryUsing()` | Zúžit nebo seřadit dotaz souvisejícího modelu |
| `createOptionForm()` | Zobrazit inline create formulář pro nový související záznam |
| `createOptionUsing()` | Přizpůsobit, jak se nová option perzistuje |

## Zúžené options

```php
BelongsToSelect::make('company_id')
    ->relationship('company', 'name')
    ->modifyOptionsQueryUsing(fn ($query) => $query->where('active', true))
```

## Inline create

```php
use NyonCode\WireForms\Components\TextInput;

BelongsToSelect::make('company_id')
    ->relationship('company', 'name')
    ->createOptionForm([
        TextInput::make('name')->required(),
    ])
```

Inline create používá stejný modal flow jako [Select](select.md#create--edit-options): tlačítko „+ Create" v panelu comboboxu otevře modal formulář a při uložení se nový záznam vytvoří na relaci (nebo přes `createOptionUsing()`), vybere a sloučí do dropdownu bez obnovení stránky. `editOptionForm()` ze `Select` zde funguje také.

## Související dokumentace

- [Select](select.md)
- [Přehled formulářů](../overview.md)
