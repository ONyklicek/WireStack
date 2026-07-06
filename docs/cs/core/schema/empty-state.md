---
order: 21
---

# Empty State

Vycentrovaná ikona, nadpis, popis a volitelná akční tlačítka, zobrazené, když
není co zobrazit.

```php
use NyonCode\WireCore\Foundation\Schema\EmptyState;
```

## Použití

```php
EmptyState::make()
    ->icon('inbox')
    ->heading('No records yet')
    ->description('Create your first record to get started.')
```

## S akcemi

Akční tlačítka se vykreslí pod popisem. Předejte jakýkoli `Htmlable` (například
vykreslené tlačítko akce) nebo raw HTML řetězec:

```php
EmptyState::make()
    ->icon('users')
    ->heading('No users')
    ->description('Invite someone to collaborate.')
    ->actions([
        Action::make('invite')->label('Invite user'),
    ])
```

## Metody

| Metoda | Popis |
|--------|-------------|
| `icon(string\|Icon)` | Ikona zobrazená nad nadpisem |
| `heading(string\|Closure)` | Primární řádek |
| `description(string\|Closure)` | Sekundární řádek pod nadpisem |
| `actions(array)` | Akční tlačítka (`Htmlable` nebo HTML řetězce) vykreslená pod popisem |

## Samostatný tag

Stejná komponenta pohání stav tabulky „žádné záznamy" a je dostupná jako
Blade tag:

```blade
<x-wire::empty-state icon="inbox" heading="No records yet" />
```
