---
title: Pokročilé použití
order: 60
---

# Pokročilé použití

## Kompletní příklad

```php
// app/Livewire/TaskTable.php

use Livewire\Component;
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireSortable\Concerns\WithSortable;

class TaskTable extends Component
{
    use WithTable, WithSortable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Task::class)
            ->reorderable('position')
            ->columnReorderable()
            ->selectable()
            ->defaultSort('position')
            ->columns([
                TextColumn::make('name', 'Task')
                    ->searchable()
                    ->sortable(),
                BadgeColumn::make('status', 'Status')
                    ->sortable(),
                TextColumn::make('priority', 'Priority')
                    ->sortable(),
            ]);
    }

    protected function beforeReorder(array $items): void
    {
        $this->authorize('reorder', Task::class);
    }

    protected function afterReorder(array $items): void
    {
        cache()->forget('tasks.ordered');
        $this->dispatch('tasks-reordered');
    }

    public function render()
    {
        return view('livewire.task-table');
    }
}
```

```blade
{{-- resources/views/livewire/task-table.blade.php --}}

<div>
    <div class="mb-4 flex gap-2">
        <button wire:click="resetColumnOrder" class="btn btn-secondary">
            Reset Columns
        </button>
    </div>

    {!! $this->table !!}
</div>
```

> **Poznámka:** Toggle tlačítko „Reorder" / „Done reordering" se vykreslí automaticky sortable pohledem. Nemusíte ho přidávat manuálně.

## Jen řazení řádků

```php
return $table
    ->model(Task::class)
    ->reorderable('sort_order')
    ->columns([...]);
```

## Jen řazení sloupců

```php
return $table
    ->model(Task::class)
    ->columnReorderable()
    ->columns([...]);
```

Žádné toggle tlačítko se neobjeví. Hlavičky sloupců jsou vždy táhnutelné.

## Multi-guard autentizace

```php
class AdminTaskTable extends Component
{
    use WithTable, WithSortable;

    protected function getReorderableUserId(): ?int
    {
        return auth('admin')->id();
    }

    // ...
}
```

Aktualizujte `config/wire-sortable.php`, aby odpovídal:

```php
'user_model' => 'App\\Models\\Admin',
```

## Pořadí sloupců per komponenta

Ve výchozím stavu je pořadí sloupců klíčované třídou Eloquent modelu. Pokud máte více komponent zobrazujících stejný model, ale chcete nezávislá pořadí sloupců:

```php
protected function getReorderableModelType(): ?string
{
    return static::class;
}
```

Nyní mohou `TaskTable` a `TaskKanban` mít různá uspořádání sloupců, i když obě zobrazují `Task` záznamy.

## Programové reorder operace

Model `ReorderableColumnOrder` můžete použít přímo:

```php
use NyonCode\WireSortable\Models\ReorderableColumnOrder;

// Získat pořadí sloupců uživatele pro model + tabulku
$order = ReorderableColumnOrder::getOrder(
    userId: $user->id,
    modelType: Task::class,
    tableIdentifier: TaskTable::class,
);
// Vrací: ['status', 'name', 'priority'] nebo null

// Uložit pořadí sloupců
ReorderableColumnOrder::saveOrder(
    userId: $user->id,
    modelType: Task::class,
    tableIdentifier: TaskTable::class,
    columnOrder: ['priority', 'status', 'name'],
);

// Smazat pořadí sloupců (reset na výchozí)
ReorderableColumnOrder::deleteOrder(
    userId: $user->id,
    modelType: Task::class,
    tableIdentifier: TaskTable::class,
);
```

## Řazení během reorder režimu

Když je zapnutý reorder režim řádků, tabulka je seřazená podle nakonfigurovaného sloupce pořadí, aby uživatelé mohli předvídatelně přesouvat záznamy. Když je reorder režim vypnutý, tabulka se vrátí k normálnímu chování hledání, filtrů a řazení.

```php
SortableTable::make()
    ->model(Task::class)
    ->reorderable('sort_order');
```

Použijte viditelný sortable handle nebo reorder tlačítko ve svém UI tabulky, aby uživatelé věděli, kdy mění trvalé pořadí, spíš než řadí aktuální pohled.

## Migrace z v0.x

Pokud upgradujete z předchozí verze wire-sortable:

| Před | Po |
|---|---|
| Vlastnost `$sortableEnabled` | Vlastnost `$isReordering` |
| `toggleSortable()` | `toggleReordering()` |
| `$sortableColumnOrder` | `$reorderableColumnOrder` |
| `getSortableColumns()` | `getReorderableColumns()` |
| `dragHandleColumn()` | Odstraněno (handly jsou v reorder režimu automatické) |
| `dragHandleBeforeSelect()` | Odstraněno |
| Session-based perzistence sloupců | DB-based přes tabulku `reorderable_column_orders` |
| Vždy zapnuté drag handly | Toggle režim (klik „Reorder" pro vstup) |
| Vždy vynucuje pořadí řazení | Vynucuje řazení jen v reorder režimu |

### Kroky migrace

1. Spusťte `php artisan wire-sortable:install` pro publikování nové migrace
2. Spusťte `php artisan migrate` pro vytvoření tabulky `reorderable_column_orders`
3. Aktualizujte své Livewire komponenty:
   - Nahraďte `use WithTable, WithSortable { ... insteadof ... }` za `use WithTable, WithSortable;`
   - Nahraďte volání `toggleSortable()` za `toggleReordering()`
   - Nahraďte volání `getSortableColumns()` za `getReorderableColumns()`
   - Odstraňte volání `dragHandleColumn()` a `dragHandleBeforeSelect()`
4. Odstraňte jakákoli manuální toggle tlačítka -- balíček teď jedno vykresluje automaticky
