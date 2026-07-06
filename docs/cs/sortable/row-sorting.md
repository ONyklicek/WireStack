---
title: Řazení řádků
order: 30
---

# Řazení řádků

Drag & drop řazení řádků s toggle režimem a automatickou databázovou perzistencí.

## Základní použití

```php
use Livewire\Component;
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireSortable\Concerns\WithSortable;

class TaskTable extends Component
{
    use WithTable, WithSortable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Task::class)
            ->reorderable()
            ->columns([
                TextColumn::make('name', 'Name'),
                TextColumn::make('status', 'Status'),
            ]);
    }

    public function render()
    {
        return view('livewire.task-table');
    }
}
```

Trait `WithSortable` registruje Table makra, která přidávají `reorderable()` a další metody do základní třídy `Table`. Řetězíte je přímo na `$table`.

Blade šablona používá computed vlastnost `$table`:

```blade
{{-- resources/views/livewire/task-table.blade.php --}}
<div>
    {!! $this->table !!}
</div>
```

## Jak reorder režim funguje

1. V toolbaru tabulky se objeví **tlačítko „Reorder"**
2. Uživatel klikne na tlačítko pro **vstup do reorder režimu**
3. V reorder režimu:
   - Na každém řádku se objeví drag handly
   - Stránkování je vypnuté (zobrazí se všechny záznamy)
   - Řazení, hledání a filtry se obejdou
   - Řádky jsou seřazené podle sort sloupce vzestupně
4. Uživatel táhne řádky na požadovanou pozici
5. Při konci tažení se nové pořadí uloží do databáze
6. Uživatel klikne na **„Done reordering"** pro opuštění reorder režimu
7. Tabulka se vrátí do normálního stavu s obnoveným stránkováním, řazením a filtry

## Vlastní sloupec pořadí

```php
return $table
    ->model(Task::class)
    ->reorderable('position')
    ->columns([...]);
```

Název sloupce musí existovat ve vaší databázové tabulce. Výchozí `sort_order`.

## Vždy zapnutý reorder režim

Pokud chcete drag handly viditelné neustále bez toggle tlačítka:

```php
return $table
    ->model(Task::class)
    ->alwaysReorderable()
    ->columns([...]);
```

S vlastním sloupcem:

```php
return $table
    ->model(Task::class)
    ->alwaysReorderable('position')
    ->columns([...]);
```

V tomto režimu je tabulka vždy v reorder režimu -- žádné toggle tlačítko se nevykreslí a `$isReordering` je při mountu nastaveno na `true`.

## Podmíněné řazení

Vypněte řazení na základě podmínky (např. oprávnění uživatele):

```php
return $table
    ->model(Task::class)
    ->reorderable('sort_order', auth()->user()->can('reorder', Task::class))
    ->columns([...]);
```

Když je jako druhý argument předáno `false`, reorder tlačítko se neobjeví a metoda `toggleReordering()` je no-op.

## Stránkované během reorderingu

Ve výchozím stavu je stránkování v reorder režimu vypnuté, aby uživatel mohl táhnout přes celý dataset. Pokud máte velký dataset a preferujete zachovat stránkování:

```php
return $table
    ->model(Task::class)
    ->reorderable()
    ->paginatedWhileReordering()
    ->columns([...]);
```

> **Poznámka:** Se zapnutým stránkováním mohou uživatelé přeřazovat jen v rámci aktuální stránky.

## Lifecycle hooky

Přepište tyto metody ve své komponentě pro zapojení do reorder procesu:

```php
protected function beforeReorder(array $items): void
{
    // Autorizovat, validovat nebo odeslat pre-reorder logiku
    $this->authorize('reorder', Task::class);
}

protected function afterReorder(array $items): void
{
    // Vyčistit cache, odeslat události, zalogovat aktivitu
    Cache::forget('tasks.ordered');
    $this->dispatch('tasks-reordered');
}
```

Každá položka `$items` je asociativní pole:

```php
[
    ['value' => '1', 'order' => 1],
    ['value' => '5', 'order' => 2],
    ['value' => '3', 'order' => 3],
]
```

- `value` -- primární klíč záznamu
- `order` -- nová 1-based pozice

## Vlastní primární klíč

Ve výchozím stavu reorder dotazy používají primární klíč tabulky (`id`). Pokud váš model používá jiný klíč:

```php
return $table
    ->model(Task::class)
    ->primaryKey('uuid')
    ->reorderable()
    ->columns([...]);
```

## Tok řazení řádků

1. Toolbar tabulky ukáže reorder toggle, když je řazení řádků zapnuté
2. Uživatel vstoupí do reorder režimu
3. Tabulka zobrazí záznamy seřazené podle nakonfigurovaného sloupce pořadí
4. Uživatel táhne řádky do požadovaného pořadí
5. Wire Sortable přijme nové pořadí a aktualizuje sloupec pořadí v jedné databázové transakci
6. Vaše hooky `beforeReorder()` a `afterReorder()` běží kolem uložení
7. Tabulka se obnoví a opustí nebo zůstane v reorder režimu podle akce uživatele
