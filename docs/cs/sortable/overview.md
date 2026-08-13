---
title: Úvod
order: 10
---

# Wire Sortable

Přeřaditelné řádky a sloupce pro [wire-table](../table/overview.md). Řazení řádků se perzistuje do databázového sloupce. Řazení sloupců se perzistuje per uživatel, per model a per table komponenta do databáze.

Postaveno na [SortableJS](https://sortablejs.github.io/Sortable/) a [Alpine.js](https://alpinejs.dev/).

## Funkce

- **Řazení řádků** -- toggle tlačítko přepne tabulku do reorder režimu; táhněte řádky pro změnu jejich pozice, perzistováno do databázového sloupce
- **Vždy zapnutý reorder režim** -- volitelně přeskočte toggle a nechte drag handly viditelné neustále
- **Řazení sloupců** -- táhněte hlavičky sloupců pro přeuspořádání; pořadí je uloženo per uživatel, per model a per table komponenta v databázi
- **Reorder režim** -- v reorder režimu jsou stránkování, řazení, hledání a filtry vypnuté, aby uživatel mohl volně táhnout přes celý dataset
- **Stránkované během reorderingu** -- volitelně nechte stránkování zapnuté během reorder režimu
- **Lifecycle hooky** -- `beforeReorder()` / `afterReorder()` pro autorizaci, cachování, události
- **Podpora více tabulek** -- více table komponent nad stejným modelem dostane nezávislá pořadí sloupců
- **Dark mode** -- všechny drag indikátory podporují světlý a tmavý motiv
- **Livewire 4 kompatibilní** -- přežije morphy, stránkování a změny filtrů

## Typické nastavení

Přidejte `WithSortable` vedle `WithTable`, pak zapněte řazení řádků nebo sloupců na tabulce.

```php
use NyonCode\WireSortable\Concerns\WithSortable;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

class TaskTable extends Component
{
    use WithTable, WithSortable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Task::class)
            ->reorderable('sort_order')
            ->columnReorderable()
            ->columns([
                // ...
            ]);
    }
}
```

## Požadavky

| Závislost | Verze |
|------------|---------|
| PHP | ^8.2 |
| Laravel | ^10.0 / ^11.0 / ^12.0 / ^13.0 |
| Livewire | ^3.0 |
| wire-core | ^0.1 |
| wire-table | ^0.1 |
| Tailwind CSS | ^3.0 / ^4.0 |

## Stránky

| Stránka | Popis |
|------|-------------|
| [Instalace](installation.md) | Composer, migrace, SortableJS, Tailwind |
| [Řazení řádků](row-sorting.md) | Toggle režim, drag & drop, lifecycle hooky |
| [Řazení sloupců](column-sorting.md) | Pořadí sloupců per uživatel, DB perzistence |
| [Přizpůsobení](customization.md) | CSS třídy, dark mode, publikování pohledů |
| [Pokročilé použití](advanced.md) | Kompletní příklad, detaily konfigurace a řešení potíží |
| [Reference API](api-reference.md) | SortableTable, WithSortable, ReorderableColumnOrder, config |
