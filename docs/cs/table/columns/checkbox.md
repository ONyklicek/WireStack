---
order: 23
nav: false
---

# CheckboxColumn

Inline zaškrtávátko, které zapisuje boolean přímo do záznamu — stejná optimistická
cesta zápisu jako [ToggleColumn](toggle.md), pro případy, kdy checkbox působí
přirozeněji než přepínač nebo je tabulka příliš hustá na celý přepínač.

```php
use NyonCode\WireTable\Columns\CheckboxColumn;
```

## Základní použití

```php
CheckboxColumn::make('is_active')
```

Kliknutí se ukládá okamžitě a při odmítnutí zápisu se vrátí zpět s inline chybou
(včetně konfliktu optimistického zámku — viz [Editace](editing.md)).

## Barva zaškrtnutí

```php
CheckboxColumn::make('is_active')
    ->accentColor('success')
```

## Zakázání pro konkrétní záznam

```php
CheckboxColumn::make('is_active')
    ->disabled(fn ($record) => $record->is_locked)
```

Zakázaný stav se vynucuje i na serveru, nejen v prohlížeči: podvržený požadavek
na `updateTableCell()` u zakázaného řádku je odmítnut.

## CheckboxColumn API

```php
->accentColor(string|Color|null $color)   // barva zaškrtnutí, výchozí: 'primary'
->disabled(bool|Closure $condition = true)
->getAccentColorClass(): string
```
