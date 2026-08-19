---
title: Reference API
order: 70
---

# Reference API

## SortableTable

`NyonCode\WireSortable\SortableTable`

Rozšiřuje `NyonCode\WireTable\Table`. Všechny základní metody Table zůstávají dostupné.

### `reorderable(?string $orderColumn = null, bool $condition = true): static`

Zapnout drag & drop řazení řádků.

| Parametr | Typ | Výchozí | Popis |
|---|---|---|---|
| `$orderColumn` | `?string` | `'sort_order'` | Databázový sloupec pro sort pozici |
| `$condition` | `bool` | `true` | Podmíněně zapnout řazení |

```php
// Výchozí sloupec
$table->reorderable();

// Vlastní sloupec
$table->reorderable('position');

// Podmíněné
$table->reorderable('position', $user->can('reorder'));
```

### `alwaysReorderable(?string $orderColumn = null): static`

Udržet řazení řádků aktivní trvale — žádné toggle tlačítko se nevykreslí. Implikuje `reorderable()`.

| Parametr | Typ | Výchozí | Popis |
|---|---|---|---|
| `$orderColumn` | `?string` | výchozí z configu | Databázový sloupec pro sort pozici |

```php
$table->alwaysReorderable();
$table->alwaysReorderable('position');
```

### `isReorderable(): bool`

Vrací, zda je řazení řádků zapnuté.

### `isAlwaysReorderable(): bool`

Vrací, zda je řazení vždy aktivní (toggle tlačítko skryté).

### `getOrderColumn(): string`

Vrací název sloupce pořadí.

### `paginatedWhileReordering(bool $enabled = true): static`

Udržet stránkování zapnuté v reorder režimu. Ve výchozím stavu je stránkování během řazení vypnuté.

### `isPaginatedWhileReordering(): bool`

Vrací, zda je stránkování zachováno během reorder režimu.

### `columnReorderable(bool $enabled = true): static`

Zapnout nebo vypnout uživatelsky specifické řazení sloupců. Pořadí sloupců se perzistuje per uživatel + model v databázi.

### `isColumnReorderable(): bool`

Vrací, zda je řazení sloupců zapnuté.

---

## WithSortable

`NyonCode\WireSortable\Concerns\WithSortable`

Livewire trait. Použijte vedle `WithTable`:

```php
use WithTable, WithSortable;
```

### Vlastnosti

| Vlastnost | Typ | Výchozí | Popis |
|---|---|---|---|
| `$isReordering` | `bool` | `false` | Zda je tabulka v reorder režimu řádků |
| `$reorderableColumnOrder` | `array` | `[]` | Aktuální pořadí sloupců (načtené z DB při mountu) |

### Veřejné metody

#### `toggleReordering(): void`

Přepnout reorder režim řádků on/off. Vyčistí cachované záznamy pro vynucení re-query.

No-op, pokud tabulka není reorderable.

#### `reorderRows(array $items): void`

Zpracovat drag & drop řádků. Voláno Alpine.js po dokončení drag operace. Aktualizuje sloupec pořadí v databázové transakci.

Každá položka: `['value' => string|int, 'order' => int]`

Posílají se jen řádky, které se pohnuly — rozsah mezi první a poslední změněnou pozicí, ne celá stránka. Je to bezpečné ze stejného důvodu jako přerozdělení níže: řádky, o kterých se zápis nedozví, si drží slot, který měly. Tažení, které posune jeden řádek o tři místa, stojí čtyři zápisy, ať tabulka ukazuje dvacet řádků nebo dvacet tisíc.

`order` je nová pozice řádku na obrazovce, nikoli zapisovaná hodnota. Tažené řádky si ponechají sadu hodnot pořadí, které už měly, rozdanou v novém vizuálním pořadí -- tažení nad prohledanou, vyfiltrovanou nebo stránkovanou podmnožinou tedy nemůže pohnout řádky, které nezobrazuje; viz [Přeřazování zúženého seznamu](row-sorting.md#prerazovani-zuzeneho-seznamu). Pozice se zapíšou doslova jen tehdy, když je sloupec pořadí prázdný nebo konstantní a nemá co rozdávat.

No-op, pokud:
- Tabulka není reorderable
- Tabulka není v reorder režimu (`$isReordering === false`)
- Klíč řádku leží mimo základní dotaz tabulky (ze zápisu vypadne)

#### `reorderColumns(array $columnOrder): void`

Zpracovat drag & drop sloupců. Zvaliduje názvy sloupců proti definici tabulky a perzistuje do tabulky `reorderable_column_orders`.

No-op, pokud:
- Tabulka není column-reorderable
- Uživatel není autentizován (`getReorderableUserId()` vrací `null`)
- Nejsou poskytnuty platné názvy sloupců

#### `resetColumnOrder(): void`

Resetuje pořadí sloupců na výchozí (jak je definováno v `table()`) a smaže databázový záznam.

#### `getReorderableColumns(): array`

Vrací sloupce v uživatelem uloženém pořadí. Nově přidané sloupce (nepřítomné v uloženém pořadí) se připojí na konec. Odebrané sloupce (v uloženém pořadí, ale už ne v definici tabulky) se tiše přeskočí.

#### `isTableReordering(): bool`

Vrací, zda je tabulka aktuálně v reorder režimu řádků. Alias pro `$this->isReordering`.

### Protected přepisy

Tyto metody přepisují protected factory/hook metody `WithTable`. Klauzule `insteadof` není potřeba — PHP je resolvuje automaticky, protože `WithSortable` je uveden za `WithTable`.

#### `getTableView(): string`

Vrací `'wire-sortable::tables.index'`, když je zapnuté řazení řádků nebo sloupců. Jinak propadne na `'wire-table::tables.index'`.

#### `interceptTableRecords(): LengthAwarePaginator|Paginator|CursorPaginator|Collection|null`

V reorder režimu (bez `paginatedWhileReordering`): obejde stránkování a řazení podle sloupce, ale hledání a filtry ponechá v platnosti. Vrátí všechny odpovídající záznamy seřazené podle sort sloupce vzestupně.

Jinak: vrátí `null`, aby `WithTable` zpracoval načítání záznamů normálně.

### Protected hooky

#### `beforeReorder(array $items): void`

Voláno před databázovým updatem. Přepište pro autorizaci nebo pre-processing.

```php
protected function beforeReorder(array $items): void
{
    $this->authorize('reorder', Task::class);
}
```

#### `afterReorder(array $items): void`

Voláno po databázovém updatu. Přepište pro invalidaci cache nebo události.

```php
protected function afterReorder(array $items): void
{
    Cache::forget('tasks.ordered');
    $this->dispatch('tasks-reordered');
}
```

#### `getReorderableUserId(): ?int`

Vrací ID uživatele pro perzistenci pořadí sloupců. Výchozí `auth()->id()`.

Přepište pro vlastní auth guardy:

```php
protected function getReorderableUserId(): ?int
{
    return auth('admin')->id();
}
```

#### `getReorderableModelType(): ?string`

Vrací klíč typu modelu pro perzistenci pořadí sloupců. Výchozí je název třídy Eloquent modelu (např. `App\Models\Task`).

Přepište pro použití vlastního klíče:

```php
protected function getReorderableModelType(): ?string
{
    return static::class; // třída komponenty místo modelu
}
```

---

## ReorderableColumnOrder

`NyonCode\WireSortable\Models\ReorderableColumnOrder`

Eloquent model pro tabulku `reorderable_column_orders`.

### Vlastnosti

| Vlastnost | Typ | Popis |
|---|---|---|
| `$user_id` | `int` | Cizí klíč do tabulky users |
| `$model_type` | `string` | Název třídy Eloquent modelu |
| `$column_order` | `array` | JSON-cast pole názvů sloupců |

### Relace

#### `user(): BelongsTo`

Patří modelu uživatele nakonfigurovanému ve `wire-sortable.user_model`.

### Statické metody

#### `getOrder(int $userId, string $modelType, string $tableIdentifier): ?array`

Získat uložené pořadí sloupců pro kombinaci uživatel + model + tabulka. Vrací `null`, pokud záznam neexistuje.

#### `saveOrder(int $userId, string $modelType, string $tableIdentifier, array $columnOrder): void`

Vytvořit nebo aktualizovat pořadí sloupců pro kombinaci uživatel + model + tabulka (upsert).

#### `deleteOrder(int $userId, string $modelType, string $tableIdentifier): void`

Smazat záznam pořadí sloupců pro kombinaci uživatel + model + tabulka.

## Konfigurace

`config/wire-sortable.php`

| Klíč | Typ | Výchozí | Popis |
|---|---|---|---|
| `order_column` | `string` | `'sort_order'` | Výchozí název sloupce pořadí |
| `sortablejs_cdn` | `?string` | `null` | Volitelný `<script>` SortableJS navíc. SortableJS je zkompilovaný v bundlu balíčku, takže tohle na řazení nikdy nemá vliv — nastavte jen tehdy, když váš vlastní kód potřebuje globální `window.Sortable` |
| `animation` | `int` | `150` | Doba drag animace v milisekundách |
| `user_model` | `string` | `'App\\Models\\User'` | Třída modelu uživatele pro relace pořadí sloupců |
| `user_key_type` | `string` | `'id'` | Typ primárního klíče modelu uživatele, použitý migrací k otypování sloupce `user_id`. Použijte `'uuid'` nebo `'ulid'` pro neceločíselné auth klíče |

---

## Alpine.js komponenta

`wireSortable(config)` je registrována globálně přes `Alpine.data()` z bundlu balíčku
(`dist/wire-sortable.js`, se zkompilovaným SortableJS). Registruje se hned, jak bundle
proběhne, ne až z `alpine:init` — funguje tedy i tehdy, když bundle dorazí až po startu
Alpine: při návštěvě přes `wire:navigate`, u lazy vykreslené tabulky, v modalu.

### Config volby

| Volba | Typ | Výchozí | Popis |
|---|---|---|---|
| `rowReorderable` | `bool` | `false` | Zapnout řazení řádků |
| `columnReorderable` | `bool` | `false` | Zapnout řazení sloupců |
| `isReordering` | `bool` (entangled) | `false` | Livewire-synced stav reorder režimu |
| `orderColumn` | `string` | `'sort_order'` | Název sloupce pořadí |
| `animation` | `int` | `150` | Doba SortableJS animace (ms) |

### Chování

- `isReordering` je entanglováno s Livewire vlastností `$isReordering` přes `@entangle`
- Když se `isReordering` změní, komponenta automaticky inicializuje nebo zničí SortableJS na `<tbody>`
- Drag handly se dynamicky přidávají/odebírají z DOM
- Řazení sloupců je vždy aktivní, když je `columnReorderable` `true` (nezávisle na reorder režimu)
- Po aktualizaci tabulky Livewire se SortableJS znovu inicializuje

---

## Překlady

`lang/{locale}/messages.php`

| Klíč | EN | CS | Popis |
|---|---|---|---|
| `reorder` | Reorder | Přeuspořádat | Label toggle tlačítka (neaktivní) |
| `done_reordering` | Done reordering | Hotovo | Label toggle tlačítka (aktivní) |
