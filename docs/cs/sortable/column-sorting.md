---
title: Řazení sloupců
order: 40
---

# Řazení sloupců

Drag & drop řazení hlaviček sloupců s databázovou perzistencí per uživatel, per tabulka.

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
            ->columnReorderable()
            ->columns([
                TextColumn::make('name', 'Name'),
                TextColumn::make('status', 'Status'),
                TextColumn::make('priority', 'Priority'),
            ]);
    }
}
```

Uživatelé mohou táhnout hlavičky sloupců pro jejich přeuspořádání. Buňky těla se přeřadí automaticky, aby odpovídaly.

## Databázová perzistence

Pořadí sloupců je uloženo v tabulce `reorderable_column_orders` s unikátním omezením na `(user_id, model_type, table_identifier)`. To znamená:

- Každý uživatel má své vlastní uspořádání sloupců
- Uspořádání je vázáno na třídu Eloquent modelu i třídu Livewire komponenty
- Více table komponent zobrazujících stejný model dostane **nezávislá** pořadí sloupců
- Když je uživatel smazán, jeho pořadí sloupců se kaskádově smaže

### Struktura úložiště

```
reorderable_column_orders
├── user_id: 1
│   ├── model_type: "App\Models\Task", table_identifier: "App\Livewire\TaskListTable"
│   │   → ["status", "name", "priority"]
│   ├── model_type: "App\Models\Task", table_identifier: "App\Livewire\TaskBoardTable"
│   │   → ["priority", "name", "status"]
│   └── model_type: "App\Models\User", table_identifier: "App\Livewire\UserTable"
│       → ["email", "name", "role"]
├── user_id: 2
│   └── model_type: "App\Models\Task", table_identifier: "App\Livewire\TaskListTable"
│       → ["priority", "status", "name"]
```

### Jak se načítá

Při mountu komponenty (`mountWithSortable`) trait:

1. Získá ID aktuálního uživatele přes `getReorderableUserId()` (výchozí `auth()->id()`)
2. Získá typ modelu přes `getReorderableModelType()` (třída Eloquent modelu)
3. Získá identifikátor tabulky přes `getReorderableTableIdentifier()` (třída Livewire komponenty)
4. Dotáže se `ReorderableColumnOrder::getOrder($userId, $modelType, $tableIdentifier)`
5. Když najde, nastaví `$reorderableColumnOrder` uloženými názvy sloupců

### Jak se ukládá

Když uživatel táhne hlavičku sloupce:

1. Alpine.js přečte nové pořadí hlaviček z atributů `th[data-sortable-column]`
2. Zavolá `$wire.reorderColumns(['status', 'name', 'priority'])`
3. Trait zvaliduje názvy sloupců proti definici tabulky (ignoruje neznámé sloupce)
4. Uloží přes `ReorderableColumnOrder::saveOrder($userId, $modelType, $tableIdentifier, $columnOrder)`

### Validace názvů sloupců

Trait filtruje příchozí názvy sloupců proti definici tabulky. Perzistují se jen názvy, které odpovídají definovanému sloupci. To předchází:

- Injektování libovolných názvů sloupců z frontendu
- Zastaralým názvům sloupců, které by rozbily tabulku poté, co je sloupec odebrán z definice

Při načítání uloženého pořadí se sloupce, které už v definici neexistují, tiše přeskočí. Nově přidané sloupce (nepřítomné v uloženém pořadí) se připojí na konec.

## Získání seřazených sloupců

Použijte `getReorderableColumns()` pro získání sloupců v uživatelem preferovaném pořadí:

```php
$columns = $this->getReorderableColumns();
```

To vrátí všechny definované sloupce seřazené podle uloženého pořadí. Sloupce nepřítomné v uloženém pořadí se připojí na konec.

## Resetování pořadí sloupců

Zavolejte `resetColumnOrder()` pro obnovení výchozího pořadí:

```blade
<button wire:click="resetColumnOrder">
    Reset Column Order
</button>
```

To vyčistí jak vlastnost komponenty, tak databázový záznam.

## Kombinace s řazením řádků

Řazení řádků a sloupců funguje nezávisle a lze je použít společně:

```php
return $table
    ->model(Task::class)
    ->reorderable('position')
    ->columnReorderable()
    ->columns([...]);
```

## Více tabulek nad stejným modelem

Ve výchozím stavu je identifikátor tabulky třída Livewire komponenty (`static::class`). To znamená, že dvě komponenty jako `TaskListTable` a `TaskBoardTable`, které obě dotazují `App\Models\Task`, budou mít nezávislá pořadí sloupců bez jakékoli extra konfigurace.

Pokud potřebujete vlastní identifikátor (např. jedna komponenta, která vykresluje různé konfigurace tabulky), přepište `getReorderableTableIdentifier()`:

```php
protected function getReorderableTableIdentifier(): string
{
    return static::class . ':' . $this->tableVariant;
}
```

## Vlastní resolvování uživatele

Ve výchozím stavu trait používá `auth()->id()` k identifikaci uživatele. Přepište `getReorderableUserId()` pro vlastní logiku:

```php
// Multi-guard autentizace
protected function getReorderableUserId(): ?int
{
    return auth('admin')->id();
}
```

## Vlastní typ modelu

Ve výchozím stavu se typ modelu resolvuje z `$table->getQuery()->getModel()`. Přepište `getReorderableModelType()`, pokud potřebujete vlastní klíč:

```php
protected function getReorderableModelType(): ?string
{
    return 'custom-key';
}
```

## Hosté (neautentizovaní uživatelé)

Když `getReorderableUserId()` vrátí `null`, řazení sloupců se tiše vypne:

- `mountWithSortable()` přeskočí načítání uloženého pořadí
- `reorderColumns()` je no-op
- `resetColumnOrder()` vyčistí jen lokální vlastnost

Drag & drop UI stále funguje v prohlížeči (přes Alpine.js), ale změny se neperzistují.

## Tok řazení sloupců

1. Alpine komponenta zavolá `initColumnSortable()` na prvním `<thead tr>`
2. Hlavičkové buňky se identifikují podle `wire:click="sortTable('column')"` nebo atributů `data-column`
3. Každá identifikovaná buňka dostane atribut `data-sortable-column` a kurzor `grab`
4. SortableJS zapne vodorovné tažení elementů `th[data-sortable-column]`
5. Při konci tažení se `<td>` buňky těla přeřadí, aby odpovídaly novému pořadí hlaviček
6. Nová sekvence názvů sloupců se pošle do `reorderColumns()` přes Livewire
7. Trait zvaliduje názvy sloupců, uloží do `reorderable_column_orders` a aktualizuje lokální vlastnost
8. Ne-datové sloupce (výběr, akce, drag handle) jsou z tažení vyloučeny
