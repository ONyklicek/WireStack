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

BadgeColumn::make('role')
    ->filterAsMultiSelect(['admin' => 'Admin', 'editor' => 'Editor']) // vyber více → whereIn

TextColumn::make('price')
    ->filterable()
    ->filterAsNumberRange(0, 10000)

TextColumn::make('created_at')
    ->filterable()
    ->filterAsDateRange()
```

Filtry sloupců používají Livewire vlastnost `$columnFilters` (oddělenou od `$tableFilters`).

## Jeden filtrovací engine

Hlavičkový filtr sloupce je **umístění** téhož kanonického objektu `Filter`, který používá i dedikovaný [panel filtrů](./index.md) — ne samostatný engine. Pomocné metody `filterAs*()` jsou tenké factory nad `TextFilter`, `SelectFilter`, `DateFilter`, `NumberRangeFilter` a `TernaryFilter`; sloupec vlastní *kde* se ovládací prvek vykreslí (hlavičková buňka) a *který atribut* cílí, zatímco `Filter` vlastní *jak* se aplikuje, vykresluje a persistuje. Díky tomu se každý filtr sloupce plánuje stejným `QueryPlanner`em jako panel filtr (joiny + kvalifikace vyřešeny jednou) a zdarma dědí autorizaci (`->can()` / `->visible()`).

Když potřebuješ plnou kontrolu, můžeš předat hotový filtr přes `->filter()`:

```php
use NyonCode\WireTable\Filters\SelectFilter;

TextColumn::make('status')->filter(
    SelectFilter::make('status')
        ->options(Status::class)
        ->searchable()
);
```

Filtry, jejichž SQL planner nedokáže vyjádřit jednou klauzulí — datum (`whereDate`) a boolean (`= false OR IS NULL`) — se transparentně vrátí ke sdílenému `Filter::apply()`, přesně jako v panelu.

## Indikátorové chipy a sdílitelné URL

Protože hlavičkové filtry jsou kanonické objekty `Filter`, sdílí UX aktivních filtrů s panelem:

- **Indikátorové chipy** — aktivní filtr sloupce zobrazí odstranitelný chip v toolbaru vedle chipů panelových filtrů. Popisek chipu pochází z `Filter::getIndicator()` (labely možností, meze rozsahu, formátovaná data); tlačítko × smaže jen daný filtr sloupce a odkaz „resetovat vše" smaže všechny aktivní filtry.
- **Query-string persistence** — s `Table::queryString()` se filtry sloupců zapisují do URL pod parametrem `col_<sloupec>` (rozsahy používají `col_<sloupec>_min` / `_max` atd.), takže sdílená nebo znovunačtená URL reprodukuje stejný pohled. Relační (tečkové) názvy sloupců se přeskakují, stejně jako u panelových filtrů.
