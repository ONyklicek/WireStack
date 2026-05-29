# Plán: V2 zbývající položky — #2.4, #2.5, #2.3, #5

## Status: ✅ kompletně hotové

| Fáze | Status | Pokrytí testů |
|------|--------|---------------|
| Fáze 1 — #2.4 relation chain | ✅ | `BuildPlannerColumnsTest` (5 testů) |
| Fáze 2 — #2.5 accessor inference | ✅ (bundled ve Fázi 1) | tamtéž |
| Fáze 3 — #2.3 enum auto-options | ✅ | `EnrichSelectFiltersTest` (5 testů) |
| Fáze 4A — multi-field filter views | ✅ | `FormFieldRenderTest` (4 testy) |
| Fáze 4B — scalar state schema migrace | ✅ (původně odloženo na v2.5, dokončeno na vyžádání) | `FilterStateSchemaTest` (10 testů) |

**Výsledek:** 1818 testů napříč monorepo (table 517, core 942, forms 327, sortable 32), 0 failures, 0 nových PHPStan chyb.

**Breaking change:** Fáze 4B mění tvar persistovaného filter state z `{name: 'x'}` na `{name: {value: 'x'}}` pro scalar filtry. URL formáty filter persistence musí být v changelogu vyznačené jako BC break v2 alfa.

---

## Context

Z `architecture/plans/v2-deferred-items.md` zůstávají dvě částečně implementované oblasti:

- **#2 Metadata + Capabilities** — implementováno 2.2 (flat DB column auto-resolve), chybí: 2.4 relation chain, 2.5 accessor inference, 2.3 enum options
- **#5 Filter views → form field komponenty** — implementováno `getFormFields()` na všech filtrech, chybí: delegace `render()`, nová Blade šablona, řešení wire:model path problému

---

## Fáze 1 — #2.4: Relation chain resolution v `buildPlannerColumns()` ✅

### Problém

`buildPlannerColumns()` volá `$this->registry->getColumn($this->currentModelClass, $column->getName())`.
Pro `Column::make('company.name')` hledá column `'company.name'` v User metadata → vrátí `null` → capabilities se neresolují.

### Co změnit

**Soubor:** `packages/table/src/Concerns/TableQueryService.php` — metoda `buildPlannerColumns()`

Přidat import:
```php
use NyonCode\WireCore\Core\Relations\RelationPath;
```

Refaktorovat `buildPlannerColumns()` a přidat `resolveColumnMeta()`:

```php
private function buildPlannerColumns(array $columns): array
{
    if ($this->registry === null || $this->currentModelClass === null) {
        return $columns;
    }

    $resolver = new CapabilityResolver;

    foreach ($columns as $column) {
        [$columnMeta, $accessorMeta] = $this->resolveColumnMeta($column->getName());

        if ($columnMeta !== null) {
            $resolved = $resolver->resolve($columnMeta, null, $column->getCapabilities()->all());
            $column->capabilities($resolved);
        } elseif ($accessorMeta !== null) {
            $resolved = $resolver->resolve(null, $accessorMeta, $column->getCapabilities()->all());
            $column->capabilities($resolved);
        }
    }

    return $columns;
}

/**
 * Resolve ColumnMetadata or AccessorMetadata for a given column name.
 * Handles dot-notation by walking the relation chain through the registry.
 *
 * @return array{0: ?ColumnMetadata, 1: ?AccessorMetadata}
 */
private function resolveColumnMeta(string $name): array
{
    $parsed = RelationPath::parse($name);

    if ($parsed->isSimple()) {
        return [
            $this->registry->getColumn($this->currentModelClass, $name),
            $this->registry->getAccessor($this->currentModelClass, $name),
        ];
    }

    // Aggregate columns (orders->count()) — skip capability resolution
    if ($parsed->isAggregate()) {
        return [null, null];
    }

    // Walk relation chain to find the terminal model
    $currentModel = $this->currentModelClass;

    foreach ($parsed->getRelationSegments() as $segment) {
        $relation = $this->registry->getRelation($currentModel, $segment->name);

        if ($relation === null || $relation->relatedModel === null) {
            return [null, null];
        }

        $currentModel = $relation->relatedModel;

        if (! $this->registry->hasModel($currentModel)) {
            $this->registry->registerModel($currentModel);
        }
    }

    $terminalColumn = $parsed->getColumnName();

    return [
        $this->registry->getColumn($currentModel, $terminalColumn),
        $this->registry->getAccessor($currentModel, $terminalColumn),
    ];
}
```

### Ověřit před implementací

Přečíst `packages/core/src/Core/Relations/Segments/RelationSegment.php` a ověřit:
- property name pro segment jméno (pravděpodobně `$name` nebo `$segment`)
- zda `getRelationSegments()` vrací objekty s property `->name`

---

## Fáze 2 — #2.5: Accessor capability inference ✅

**Plně zahrnut ve Fázi 1** — `resolveColumnMeta()` vrací `[null, $accessorMeta]` kdy je column accessor. `buildPlannerColumns()` pak volá `$resolver->resolve(null, $accessorMeta, $explicit)` které:
- Runtime-only accessor → přidá `Capability::RuntimeOnly`
- Accessor s SQL expression → přidá `Capability::SqlExpression + Searchable + Sortable`

Žádný samostatný soubor navíc.

---

## Fáze 3 — #2.3: SelectFilter auto-enum options ✅

### Problém

`SelectFilter::make('status')` bez options renderuje prázdný select, přestože model má `'status' => StatusEnum::class` v `$casts`.

### Co změnit

**Soubor:** `packages/table/src/Concerns/TableQueryService.php` — metoda `buildPlannerFilters()`

Přidat import:
```php
use NyonCode\WireTable\Filters\SelectFilter;
```

Na začátek `buildPlannerFilters()` přidat volání:
```php
$this->enrichSelectFiltersWithEnumOptions($filters);
```

Nová private metoda:
```php
/**
 * Auto-populate SelectFilter options from Eloquent enum casts when
 * the filter has no explicit options set.
 *
 * @param  array<int, Filter>  $filters
 */
private function enrichSelectFiltersWithEnumOptions(array $filters): void
{
    if ($this->registry === null || $this->currentModelClass === null) {
        return;
    }

    if (! $this->registry->hasModel($this->currentModelClass)) {
        return;
    }

    $modelMeta = $this->registry->getModelMetadata($this->currentModelClass);

    foreach ($filters as $filter) {
        if (! ($filter instanceof SelectFilter) || $filter->getOptions() !== []) {
            continue;
        }

        $cast = $modelMeta->getCast($filter->getColumn());

        if ($cast === null || ! enum_exists($cast)) {
            continue;
        }

        $options = [];

        foreach ($cast::cases() as $case) {
            // BackedEnum → use typed value as key; UnitEnum → use name
            $key = $case instanceof \BackedEnum ? (string) $case->value : $case->name;
            $options[$key] = $case->name;
        }

        $filter->options($options);
    }
}
```

### Omezení

- Funguje pouze pro enum casts — string/int backed i unit
- Label je `$case->name` — uživatel může kdykoli přepsat přes `->options([...])`
- Options se nastavují per-request (filtr je mutable přes setter)

---

## Fáze 4 — #5: Filter views → form field komponenty ✅

### Situační analýza wire:model paths

| Filtr | State struktura | Field names z getFormFields() | Kompatibilní? |
|-------|----------------|-------------------------------|---------------|
| `NumberRangeFilter` | `{name: {min: …, max: …}}` | `min`, `max` | ✅ |
| `DateFilter` (range) | `{name: {from: …, to: …}}` | `from`, `to` | ✅ |
| `DateFilter` (single) | `{name: 'YYYY-MM-DD'}` | `value` | ❌ |
| `SelectFilter` | `{name: 'option'}` | `value` | ❌ |
| `TernaryFilter` | `{name: '1'}` | `value` | ❌ |
| `TextFilter` (base Filter) | `{name: 'text'}` | ❌ (getFormFields = []) | ❌ |

### Strategie: jen kompatibilní filtry v Fázi 4A

Scalar filtry (3 ❌) zůstávají na starých views — breaking change state schématu je odložen na v2.5.

#### Fáze 4A — NumberRangeFilter + DateFilter range (neblokující) ✅

**1. Vytvořit `packages/table/resources/views/tables/filters/form-field.blade.php`:**

```blade
{{-- Generic filter wrapper for filters with multi-field getFormFields().
     Wire:model paths are built as: tableState.filters.{filterName}.{fieldName} --}}
@php
    $name = $filter->getName();
    $label = $filter->getLabel();
@endphp

<div class="flex flex-col gap-1">
    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
    <div class="flex gap-2">
        @foreach ($filter->getFormFields() as $field)
            <div class="flex-1">
                <input
                    type="{{ method_exists($field, 'getNativeInputType') ? $field->getNativeInputType() : 'text' }}"
                    wire:model.live="tableState.filters.{{ $name }}.{{ $field->getName() }}"
                    placeholder="{{ $field->getPlaceholder() ?? '' }}"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
            </div>
        @endforeach
    </div>
</div>
```

Šablona záměrně nepoužívá `{!! $field->toHtml() !!}` — vyhne se kolizi s `HasState::getStatePath()` logikou, která by přidala nesprávný prefix. Wire:model path se sestaví přímo.

**2. `NumberRangeFilter.render()` — delegovat:**

```php
public function render(mixed $value = null): string
{
    if (! $this->canView()) {
        return '';
    }

    return view($this->resolveFilterView('tables.filters.form-field'), [
        'filter' => $this,
        'value'  => $value,
    ])->render();
}
```

**3. `DateFilter.render()` — delegovat pro range mode:**

```php
public function render(mixed $value = null): string
{
    if (! $this->canView()) {
        return '';
    }

    $view = $this->range
        ? 'tables.filters.form-field'
        : 'tables.filters.date';

    return view($this->resolveFilterView($view), [
        'filter' => $this,
        'value'  => $value,
    ])->render();
}
```

**Ověřit před implementací:** Zda `DateTimePicker` má `getPlaceholder()` (z `HasPlaceholder` traitu — pravděpodobně ano). Zda `getNativeInputType()` existuje — z `DateTimePicker.php` existuje (vrací `'date'`, `'time'`, `'datetime-local'`). Pro `TextInput` (NumberRange) nevolá se `getNativeInputType()` — `method_exists` to bezpečně ošetří.

**Odchylky od plánu při implementaci:**
- `TextInput` má `getInputType()` (ne `getNativeInputType()`). Template detekuje obě metody přes `match` s fallbackem na `'text'`.
- `wire:model` použit s modifikátorem `.debounce.500ms` (ne plain `.live`) — zachovává UX původního `number-range.blade.php` a brání requestu na každý keystroke.

#### Fáze 4B — Scalar filtry ✅ (původně odloženo na v2.5)

State migrace `{name: 'x'}` → `{name: {value: 'x'}}` pro scalar filtry (Select, Ternary, base Filter, DateFilter single). Breaking change v persistovaném state, ale runtime má tolerantní fallback pro programmatické scalar vstupy.

**Implementace:**

1. **`Filter` base** — `extractValue(mixed $raw): mixed` (unwrap `{value: 'x'}` nebo passthrough), `wrapValue(mixed $value): mixed` (inverze pro defaults), default `getFormFields()` vrací `[TextInput::make('value')]`, `render()` deleguje na `tables.filters.form-field`.
2. **`NumberRangeFilter::wrapValue()`** — passthrough override (state je `{min, max}`).
3. **`DateFilter`** — single mode taky deleguje na `form-field`; `wrapValue()` rozlišuje range / single (range = passthrough, single = parent::wrapValue).
4. **`WithTable::initFilters()`** — defaults zalí přes `$filter->wrapValue($default)`.
5. **`TableQueryService`** — `extractValue` v custom-callback smyčce i v `buildPlannerFilters`. Multi-field detekce (`is_array($value) && ! $filter->isMultiple()`) routuje přes synthetic callback nad `$filter->apply()`, planner přeskočí (jinak by se `where(col, '=', ['min' => 20, 'max' => 100])` zlomilo).
6. **`select.blade.php` + `ternary.blade.php`** — `wire:model.live` path → `tableState.filters.{name}.value`; templates čtou `$value['value']` pro display při tolerantním fallbacku na raw scalar.

**Důsledek:** Vícepolové filtry (NumberRange, DateFilter range) měly latentní bug — value `['min' => ..., 'max' => ...]` se posílala do QueryPlanner jako `where(col, '=', $array)`, což nevracelo žádné výsledky. Bug byl skryt absencí end-to-end testů. Po 4B se routují přes `apply()`, takže fungují korektně.

**Files added/changed v 4B:**

| Soubor | Změna |
|--------|-------|
| `packages/table/src/Filters/Filter.php` | `extractValue`, `wrapValue`, default `getFormFields`, `render()` deleguje |
| `packages/table/src/Filters/DateFilter.php` | single mode deleguje na `form-field`, `wrapValue` override |
| `packages/table/src/Filters/NumberRangeFilter.php` | `wrapValue` passthrough |
| `packages/table/src/Concerns/WithTable.php` | `initFilters()` přes `wrapValue` |
| `packages/table/src/Concerns/TableQueryService.php` | `extractValue` + multi-field routing přes `apply()` |
| `packages/table/resources/views/tables/filters/select.blade.php` | wire:model path → `.value` |
| `packages/table/resources/views/tables/filters/ternary.blade.php` | totéž |
| `packages/table/tests/Unit/Filters/FilterStateSchemaTest.php` | NOVÝ — 10 testů |
| `packages/table/tests/Unit/Filters/FormFieldRenderTest.php` | DateFilter single mode test přepsán |
| `packages/table/tests/Unit/Concerns/TableQueryServiceTest.php` | 3× `filterValues:` přepsány na `['value' => …]` shape |

---

## Soubory k úpravě

| Soubor | Fáze | Změna |
|--------|------|-------|
| `packages/table/src/Concerns/TableQueryService.php` | 1, 2, 3, 4B | `buildPlannerColumns()` refaktor + `resolveColumnMeta()`; `enrichSelectFiltersWithEnumOptions()`; `extractValue` + multi-field routing |
| `packages/table/resources/views/tables/filters/form-field.blade.php` | 4A | NOVÝ |
| `packages/table/src/Filters/NumberRangeFilter.php` | 4A, 4B | `render()` delegace; `wrapValue` passthrough |
| `packages/table/src/Filters/DateFilter.php` | 4A, 4B | `render()` deleguje pro obě módy; `wrapValue` override |
| `packages/table/src/Filters/Filter.php` | 4B | `extractValue`, `wrapValue`, default `getFormFields`, `render()` deleguje |
| `packages/table/src/Concerns/WithTable.php` | 4B | `initFilters()` zalí defaults přes `wrapValue` |
| `packages/table/resources/views/tables/filters/select.blade.php` | 4B | wire:model path → `.value` |
| `packages/table/resources/views/tables/filters/ternary.blade.php` | 4B | wire:model path → `.value` |

---

## Ověření

```bash
composer test:table
php -d memory_limit=512M vendor/bin/phpstan analyse
```

### Nové testy ✅ přidané

**`packages/table/tests/Unit/Concerns/BuildPlannerColumnsTest.php`** (Fáze 1+2) — 5 testů:
- Relation column `company.name` → Searchable/Sortable z related model metadata
- Neznámá relation → žádná capability change
- Accessor bez SQL expression → `RuntimeOnly`
- Accessor s SQL expression → `SqlExpression + Searchable + Sortable`
- Aggregate column `orders->count()` → žádná capability change

**`packages/table/tests/Unit/Concerns/EnrichSelectFiltersTest.php`** (Fáze 3) — 5 testů:
- SelectFilter bez options + BackedEnum cast → options auto-populated
- SelectFilter bez options + UnitEnum cast → name → name mapping
- SelectFilter s explicitními options → options nezmění
- Non-enum cast → options zůstanou prázdné
- Sloupec bez castu → options zůstanou prázdné

**`packages/table/tests/Unit/Filters/FormFieldRenderTest.php`** (Fáze 4A + 4B update) — 4 testy:
- `NumberRangeFilter.render()` → `wire:model.live.debounce.500ms="tableState.filters.price.min"`
- `DateFilter` s `range=true` → form-field šablona
- `DateFilter` s `range=false` → form-field šablona s `.value` cestou (po 4B)
- Hidden filter → prázdný řetězec

**`packages/table/tests/Unit/Filters/FilterStateSchemaTest.php`** (Fáze 4B) — 10 testů:
- `extractValue` / `wrapValue` kontrakty pro Filter, SelectFilter, TernaryFilter, NumberRangeFilter, DateFilter (range + single)
- End-to-end query přes `TableQueryService::buildQuery` pro každý filter typ s novým state shape
- Custom query callback dostane extrahovaný scalar value

---

## Rizika (řešená v implementaci)

| Riziko | Dopad | Mitigace |
|--------|-------|----------|
| `RelationSegment` má jiný property název než `->name` | Fáze 1 TypeError | Ověřeno před implementací — `public string $name` existuje |
| `registerModel()` volán vícekrát pro stejný related model | Zbytečná reflection | `private array $lazilyRegistered = []` cache v TableQueryService (resetuje se na začátku každého `buildQuery`) |
| `DateTimePicker` nemá `getPlaceholder()` | 4A Blade error | Fallback `$field->getPlaceholder() ?? ''` v šabloně |
| Enum bez `\BackedEnum` → `$case->value` neexistuje | Fáze 3 PHP error | Podmínka `$case instanceof \BackedEnum` |
| `TextInput` nemá `getNativeInputType()` (jen `getInputType()`) | 4A wrong input type | Template detekuje obě metody přes `match` |
| Multi-field filter value `['min', 'max']` se posílá do QueryPlanner jako `where(col, '=', $array)` | 4B (a latentně před ní) — žádné výsledky | Detekce `is_array && !isMultiple` → routing přes `$filter->apply()` jako synthetic callback |

