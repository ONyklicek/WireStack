---
order: 33
nav: false
---

# Filtry na úrovni sloupce

Kromě dedikovaných komponent filtrů může mít jakýkoli sloupec inline filtr přímo ve své hlavičkové buňce. Viz [Sloupce — Filtrování na úrovni sloupce](../columns/editing.md#column-level-filtering).

```php
TextColumn::make('status')
    ->filterable()
    ->filterAsSelect(['active' => 'Active', 'inactive' => 'Inactive'])

TextColumn::make('price')
    ->filterable()
    ->filterAsNumberRange(0, 10000)

TextColumn::make('created_at')
    ->filterable()
    ->filterAsDateRange()
```

Filtry sloupců používají Livewire vlastnost `$columnFilters` (oddělenou od `$tableFilters`).
