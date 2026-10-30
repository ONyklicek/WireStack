---
title: Úvod
order: 10
summary: Přerovnatelné řádky a sloupce pro wire-table — kde se každé pořadí ukládá a komu patří.
---

# Wire Sortable

Balíček [wire-table](../table/overview.md) umožňuje měnit pořadí řádků i sloupců.
Pořadí řádků se ukládá do databázového sloupce, zatímco pořadí sloupců se
ukládá samostatně pro každou kombinaci uživatele, modelu a komponenty tabulky.

Postaveno na [SortableJS](https://sortablejs.github.io/Sortable/) a [Alpine.js](https://alpinejs.dev/).

## Funkce

- **Řazení řádků** — přepínač aktivuje režim změny pořadí; přetažením řádků změníte jejich pozici a nové pořadí se uloží do databázového sloupce
- **Trvale aktivní režim změny pořadí** — přepínač můžete vynechat a úchyty pro přetažení zobrazovat stále
- **Řazení sloupců** — přetažením záhlaví sloupců změníte jejich pořadí; pořadí se v databázi ukládá samostatně pro každého uživatele, model a komponentu tabulky
- **Režim změny pořadí** — stránkování, řazení, vyhledávání a filtry se v tomto režimu vypnou, aby uživatel mohl přesouvat řádky mezi všemi záznamy
- **Stránkování při změně pořadí** — stránkování můžete v tomto režimu ponechat zapnuté
- **Metody životního cyklu** — `beforeReorder()` / `afterReorder()` pro autorizaci, ukládání do mezipaměti a události
- **Podpora více tabulek** — více tabulkových komponent nad stejným modelem může mít nezávislé pořadí sloupců
- **Tmavý režim** — všechny indikátory přetažení podporují světlý i tmavý motiv
- **Kompatibilita s Livewire 4** — zachovává řazení při aktualizaci rozhraní, stránkování i změně filtrů

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
| Laravel | 12.61+ nebo 13.12+ |
| Livewire | ^4.0 |
| wire-core | ^2.0 |
| wire-table | ^2.0 |
| Tailwind CSS | ^3.0 / ^4.0 |

## Stránky

| Stránka | Popis |
|------|-------------|
| [Instalace](installation.md) | Composer, migrace, SortableJS, Tailwind |
| [Řazení řádků](row-sorting.md) | Přepínací režim, drag & drop, hooky životního cyklu |
| [Řazení sloupců](column-sorting.md) | Pořadí sloupců pro každého uživatele, ukládané do databáze |
| [Tabule](board.md) | Záznamy v drahách — kanban, karty přetahované mezi drahami |
| [Přizpůsobení](customization.md) | CSS třídy, dark mode, publikování pohledů |
| [Pokročilé použití](advanced.md) | Kompletní příklad, detaily konfigurace a řešení potíží |
| [Reference API](api-reference.md) | SortableTable, WithSortable, ReorderableColumnOrder, config |
