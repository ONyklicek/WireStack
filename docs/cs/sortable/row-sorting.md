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
   - Řazení podle sloupce se obejde -- řádky jsou seřazené podle sort sloupce vzestupně
   - **Hledání a filtry zůstávají v platnosti**, seznam tedy jde stále zúžit
4. Uživatel táhne řádky na požadovanou pozici
5. Při konci tažení se nové pořadí uloží do databáze
6. Uživatel klikne na **„Done reordering"** pro opuštění reorder režimu
7. Tabulka se vrátí do normálního stavu s obnoveným stránkováním a řazením podle sloupce

Řazení podle sloupce musí ustoupit, protože pořadí na obrazovce je přesně to
pořadí, které se při puštění zapíše zpět: může být jedině pořadím sort sloupce.
Hledání a filtry ustupovat nemusí, protože mění to, *které* řádky lze táhnout,
nikoli význam tažení -- proč je to bezpečné, viz
[Přeřazování zúženého seznamu](#prerazovani-zuzeneho-seznamu).

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

V tomto režimu je tabulka vždy v reorder režimu -- žádné toggle tlačítko se nevykreslí a `$isReordering` je při mountu nastaveno na `true`. Cesta zpět k běžné tabulce neexistuje, a přesně proto reorder režim ponechává hledání a filtry funkční: vyhledávací pole by na vždy přeřaditelné tabulce jinak nikdy nic neudělalo.

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

> **Poznámka:** Se zapnutým stránkováním mohou uživatelé přeřazovat jen v rámci aktuální stránky. Puštění přeuspořádá řádky té stránky mezi sebou a všechny ostatní stránky nechá tam, kde byly.

## Přeřazování zúženého seznamu

Uživatel v reorder režimu může stále hledat, filtrovat a -- s
`paginatedWhileReordering()` -- stránkovat. Tažení se tedy obvykle odehrává nad
*podmnožinou* tabulky a řádky, které tato podmnožina skryla, se pohnout nesmí.

A nepohnou se, protože puštění řádky, které dostane, nepřečísluje. Posbírá
hodnoty pořadí, které tyto řádky už mají, seřadí je vzestupně a rozdá je zpět
v novém vizuálním pořadí:

```php
// Řádky s sort_order 10, 20, 30. Přetáhněte poslední úplně nahoru:
//   před     po
//   A  10    C  10
//   B  20    A  20
//   C  30    B  30
```

Tři důsledky, které stojí za to znát:

- **Řádky mimo tažení se nikdy nepohnou.** Zůstávají na svých pozicích, takže
  hledání `audit` může přeřadit čtyři odpovídající řádky, aniž by rozhodilo čtyři
  sta, které skrylo.
- **Mezery zůstávají zachované.** Sloupec pořadí `10, 20, 30` zůstane
  `10, 20, 30`. Pokud necháváte mezery pro pozdější vkládání, přeřazení je
  nezavře.
- **Prázdný nebo konstantní sloupec pořadí nemá co rozdávat.** Tam, a jen tam, se
  místo toho zapíšou pozice od klienta (`1..n`) -- což je pro sloupec, který
  žádné pořadí nenesl, ta správná odpověď.

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
