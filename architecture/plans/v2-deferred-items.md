# V2 Plan — Odložené položky z v1

> **⚠️ Konsolidace (2026-07-06):** Autoritativní pořadí/fázování V2 drží
> [`v2-master-plan.md`](v2-master-plan.md). Tento dokument je **detailní
> implementační reference** pro engine refaktory: #1 StateContainer **HOTOVO**,
> #7 plugin runtime wiring **HOTOVO**; zbytek (#2 Metadata/Capabilities,
> #3 Hydration, #4 StateHydrator) → **V2.2**, (#5 Filter views, #6 Modal shell)
> → **V2.1**. Používej pro impl. detaily; sekvenci ber z master plánu.

> **Status (2026-06-07):** Část položek už shipovala — ověřuj vždy proti aktuálnímu kódu, ne jen proti tomuto plánu.
> - #1 StateContainer: `TableStateSynthesizer` existuje a WithTable už nedrží raw `public` state properties (synthesizer-backed).
> - #2.3 / #2.4 / #2.5 (enum auto-options, relation chain, accessor inference): hotové, pokryté testy.
> - #5 Filter views → form field komponenty: hotové.
> Zbývající/nedokončené: plná DataComponent dědičnost (#2), Hydration v SaveHandler (#3), StateHydrator v StateManager (#4), modal shell jako `<x-wire::modal>` (#6), plugin systém vylepšení (#7).

## Kontext

V1 propojila existující Core moduly s runtime (ActionPipeline, ValidationPipeline, Events, Form v action modalech, i18n). Šest oblastí bylo záměrně odloženo, protože vyžadují breaking changes v Livewire serializaci, novou dědičnost hierarchii, nebo změnu state/save lifecycle. Tento dokument je detailní plán jejich implementace ve v2.

---

## Přehled položek

| # | Oblast | Odhad tříd | Breaking change |
|---|--------|-----------|-----------------|
| 1 | StateContainer nahrazující 26 raw Livewire properties | ~5 refaktorovaných | Livewire serializace |
| 2 | Core/Metadata + Capabilities + Components (DataComponent) plná integrace | ~15 refaktorovaných | Column dědičnost |
| 3 | Core/Hydration integrace do SaveHandler | ~3 refaktorované | Save lifecycle |
| 4 | Core/State/StateHydrator v StateManager | ~2 refaktorované | Form state flow |
| 5 | Filter views → form field komponenty | ~9 refaktorovaných | UI šablony |
| 6 | Modal shell jako `<x-wire::modal>` komponenta | ~10 refaktorovaných | Blade layout |
| 7 | Plugin systém — opravy a vylepšení | ~12 nových/refaktorovaných | Plugin contract (minor) |

---

## 1. StateContainer nahrazující 26 raw Livewire properties

### Problém

`WithTable.php` má 20+ `public` properties (`$tableSortColumn`, `$tableSortDirection`, `$tablePerPage`, `$tableFilters`, `$columnFilters`, `$selectedRecords`, `$hiddenColumns`, `$expandedRows`, `$flattenMode`, `$subRowFilters`, `$showActionModal`, `$actionModalFormData`, `$actionModalIsBulk`, `$actionModalIsHeaderAction`, `$showHaltModal`, `$haltModalConfig`, `$haltModalFormData`, `$haltActionConfirmed`, `$haltContext`, `$tableReady`, `$tablePollingActive`). Tyto properties jsou přímo serializovány Livewire a změna struktury je breaking change pro existující komponenty.

### Cíl

Nahradit raw properties jedním `StateContainer` s definovanými cestami. Livewire synchronizace přes custom property type (Synthesizer).

### Plán

#### 1.1 Livewire Synthesizer pro StateContainer

Vytvořit `TableStateSynthesizer` implementující `Livewire\Mechanisms\HandleComponents\Synthesizers\Synth`:

```php
// packages/table/src/Livewire/TableStateSynthesizer.php
class TableStateSynthesizer extends Synth
{
    public static $key = 'tableState';

    public static function match($target): bool;
    public function dehydrate($target): array;  // StateContainer → JSON-safe array
    public function hydrate($data): StateContainer;  // JSON → StateContainer
    public function get(&$target, $key);  // wire:model binding
    public function set(&$target, $key, $value);  // wire:model update
}
```

Registrace v `WireTableServiceProvider::boot()`:
```php
Livewire::propertySynthesizer(TableStateSynthesizer::class);
```

#### 1.2 Definice state schématu

```php
// packages/table/src/Concerns/TableStateSchema.php
final class TableStateSchema
{
    public static function defaults(): array
    {
        return [
            'sort.column' => '',
            'sort.direction' => 'asc',
            'pagination.perPage' => 10,
            'filters' => [],
            'columnFilters' => [],
            'selection.records' => [],
            'columns.hidden' => [],
            'rows.expanded' => [],
            'rows.flattenMode' => false,
            'rows.subRowFilters' => [],
            'modal.action.show' => false,
            'modal.action.formData' => [],
            'modal.action.isBulk' => false,
            'modal.action.isHeaderAction' => false,
            'modal.halt.show' => false,
            'modal.halt.config' => [],
            'modal.halt.formData' => [],
            'modal.halt.confirmed' => false,
            'modal.halt.context' => [],
            'ready' => false,
            'polling.active' => true,
        ];
    }
}
```

#### 1.3 Migrace WithTable.php

1. Nahradit 20+ `public` properties jedním:
   ```php
   public StateContainer $tableState;
   ```
2. Inicializace v `mountWithTable()`:
   ```php
   $this->tableState = new StateContainer(TableStateSchema::defaults());
   ```
3. Přepsat všechny přístupy: `$this->tableSortColumn` → `$this->tableState->get('sort.column')`
4. Blade views: `wire:model="tableSortColumn"` → `wire:model="tableState.sort.column"`

#### 1.4 Zpětná kompatibilita (přechodné období)

- PHP `__get()` / `__set()` magic methods na WithTable delegující na StateContainer
- `@deprecated` anotace na stará property jména
- Deprecation warning přes `Core\Support\Deprecation::property()`
- Odstranění ve v3

#### 1.5 Dirty tracking výhody

StateContainer má vestavěný `DirtyStateTracker` — automaticky sleduje změny, umožňuje optimální Livewire updates (jen dirty paths se serializují).

### Soubory k úpravě

| Soubor | Změna |
|--------|-------|
| `packages/table/src/Livewire/TableStateSynthesizer.php` | NOVÝ |
| `packages/table/src/Concerns/TableStateSchema.php` | NOVÝ |
| `packages/table/src/Concerns/WithTable.php` | Nahradit 20+ properties → StateContainer |
| `packages/table/src/WireTableServiceProvider.php` | Registrace Synthesizer |
| `packages/table/resources/views/**/*.blade.php` | wire:model cesty |
| `packages/table/tests/**` | Aktualizace property přístupů |

### Rizika

- **Livewire Synthesizer API stability** — ověřit kompatibilitu s Livewire 3.x
- **wire:model binding** — nested dot-notation musí fungovat v Blade šablonách
- **Third-party integrace** — kód přistupující přímo k `$component->tableSortColumn` se rozbije

### Testy

- Unit: `TableStateSynthesizer` dehydrate/hydrate round-trip
- Unit: `TableStateSchema` defaults completeness
- Feature: Livewire component s StateContainer — sort/filter/select funguje
- Feature: Zpětná kompatibilita `__get`/`__set` delegace

---

## 2. Core/Metadata + Capabilities + Components (DataComponent) plná integrace

### Problém

Column již `extends DataComponent` a používá `CapabilitySet`, ale:
- Filter a další UI třídy nejsou napojeny na Metadata/Capabilities
- Není runtime registrace modelů do `MetadataRegistry` (metadata se nepoužívá pro automatické capabilities)
- `CapabilityResolver` není volán automaticky — capabilities se stále nastavují ručně přes fluent API (`->searchable()`, `->sortable()`)

### Cíl

Automatická detekce capabilities z modelu metadata. Uživatel definuje `Column::make('email')` a systém automaticky ví, že email je `Searchable + Sortable + Filterable` protože je to DB column typu `varchar`.

### Plán

#### 2.1 Automatická registrace modelu

V `TableQueryService` nebo `WithTable::bootWithTable()`:

```php
$registry = app(MetadataRegistry::class);
$registry->registerModel($this->getTableModelClass());
```

Registrace proběhne jednou a výsledek se cachuje v `MetadataCache`.

#### 2.2 CapabilityResolver auto-resolve

Při `Column::make('name')` v kontextu tabulky s modelem `User`:

1. `MetadataRegistry::getColumn('User', 'name')` → `ColumnMetadata(type: 'varchar', nullable: false)`
2. `CapabilityResolver::resolve($columnMetadata)` → `CapabilitySet(Searchable, Sortable, Filterable, Editable)`
3. Column automaticky získá capabilities bez explicitního `->searchable()`

Explicitní `->searchable(false)` přepíše auto-resolve (user override).

#### 2.3 Filter integrace s Capabilities

```php
// Aktuální stav
SelectFilter::make('status')->options([...])

// V2 — Filter automaticky ví o column type
SelectFilter::make('status')  // auto-resolves options z enum cast
```

Filter base class získá přístup k `MetadataRegistry` a `CapabilitySet`.

#### 2.4 Relation metadata auto-load

Pro `Column::make('company.name')`:
1. Parse `RelationPath` (již existuje)
2. `MetadataRegistry::getRelation('User', 'company')` → `RelationMetadata(type: BelongsTo, ...)`
3. Follow chain: `MetadataRegistry::getColumn('Company', 'name')` → capabilities
4. Automaticky: joinable, searchable, sortable

#### 2.5 Accessor capability inference

```php
Column::make('full_name')
    ->expression("CONCAT(first_name, ' ', last_name)")
    // → CapabilitySet(Searchable, Sortable, SqlExpression)

Column::make('computed_score')
    // bez expression → CapabilitySet(RuntimeOnly)
    // → NOT searchable, NOT sortable (strictní režim)
```

### Soubory k úpravě

| Soubor | Změna |
|--------|-------|
| `packages/table/src/Concerns/WithTable.php` | Auto-register model metadata |
| `packages/table/src/Concerns/TableQueryService.php` | Resolve capabilities z registry |
| `packages/table/src/Columns/Column.php` | Auto-resolve capabilities pokud nejsou explicitní |
| `packages/table/src/Filters/Filter.php` | Přístup k MetadataRegistry |
| `packages/core/src/Core/Capabilities/CapabilityResolver.php` | Rozšířit o filter capabilities |
| `packages/core/src/Core/Metadata/MetadataRegistry.php` | Lazy auto-register z model class |

### Rizika

- **Performance** — metadata registrace při prvním request (cache mitiguje)
- **Implicit behavior** — uživatelé zvyklí na explicitní `->searchable()` mohou být překvapeni auto-resolve
- **Schema changes** — column rename v DB bez aktualizace kódu → capabilities se změní

### Testy

- Unit: `CapabilityResolver` auto-resolve z `ColumnMetadata`
- Unit: Relation chain resolution
- Feature: Column bez explicitních capabilities → auto-resolved z DB schema
- Feature: Explicitní `->searchable(false)` přepíše auto-resolve

---

## 3. Core/Hydration integrace do SaveHandler

### Problém

`SaveHandler` aktuálně volá `$model->update($data)` přímo. Nepoužívá `Core\Hydration\Dehydrator` pro:
- Cast-aware dehydraci (json→string, Carbon→datetime string)
- Relation dehydraci (nested form data → parent + related model saves)
- `MutationPipeline` before/after hooks

### Cíl

`SaveHandler` deleguje persistenci na `Dehydrator`, který správně transformuje state → model attributes s respektováním castů a mutací.

### Plán

#### 3.1 Dehydrator integrace

```php
// Aktuální persist():
$model->update($data);

// V2 persist():
$dehydrator = app(Dehydrator::class);
$dehydrator->dehydrate($model, $data);
$model->save();
```

#### 3.2 MutationPipeline zapojení

`MutationPipeline` se spustí jako součást `dehydrate()`:
1. Before mutations (user-defined transformace)
2. Cast resolution (json encode, date format, etc.)
3. Attribute assignment
4. After mutations

To nahrazuje stávající `mutateDataBeforeSave` callback — ale zpětně kompatibilně (callback se stane jednou z before mutations).

#### 3.3 Relation save přes Hydration

Aktuální `RelationshipSaveHandler` ručně iteruje fieldy a ukládá vztahy. V2 Dehydrator rozumí dot-notation cestám a umí:
- `company.name` → najde/vytvoří BelongsTo related model
- `tags` → sync BelongsToMany
- `addresses.*.street` → HasMany create/update

#### 3.4 Zpětná kompatibilita

- `using()` callback stále funguje (bypass Dehydrator)
- `mutateDataBeforeSave()` se wrappne jako MutationPipeline before hook
- `beforeSave` / `afterSave` hooks beze změny

### Soubory k úpravě

| Soubor | Změna |
|--------|-------|
| `packages/forms/src/Forms/Runtime/SaveHandler.php` | `persist()` deleguje na Dehydrator |
| `packages/forms/src/Forms/Runtime/RelationshipSaveHandler.php` | Refaktor → Dehydrator relation handling |
| `packages/core/src/Core/Hydration/Dehydrator.php` | Rozšířit o relation dehydraci |
| `packages/core/src/Core/Hydration/MutationPipeline.php` | Integrace s SaveHandler callbacks |

### Rizika

- **Relation save complexity** — HasMany/BelongsToMany/MorphMany mají různé save strategie
- **Ordering** — musí se respektovat: parent save → pivot save → child save
- **Existing tests** — SaveHandler testy mohou vyžadovat mock Dehydrator

### Testy

- Unit: Dehydrator s cast-aware transformací
- Unit: MutationPipeline s before/after hooks
- Feature: Form save přes Dehydrator — BelongsTo, HasMany, BelongsToMany
- Feature: Zpětná kompatibilita `using()` callback

---

## 4. Core/State/StateHydrator v StateManager

### Problém

`StateManager` deleguje na `StateContainer` pro storage, ale nepoužívá `StateHydrator` pro typovou konverzi. Livewire posílá všechny hodnoty jako stringy — `StateHydrator` umí konvertovat na `int`, `bool`, `Carbon`, `json` podle type hints.

### Cíl

`StateManager::fill()` a `setState()` automaticky hydratují hodnoty přes `StateHydrator` na základě field type definitions.

### Plán

#### 4.1 Type definitions z formuláře

Formulářová pole mají implicitní typy:
- `TextInput` → `string`
- `Select` → `string|int` (podle options)
- `Toggle` / `Checkbox` → `bool`
- `DateTimePicker` → `datetime`
- `FileUpload` → `array`

`FormConfig` bude obsahovat `stateDefinitions: array<string, string>` mapující field name → type hint.

#### 4.2 StateManager hydrace

```php
// V StateManager::fill()
public function fill(array $data): void
{
    if ($this->stateDefinitions !== []) {
        $hydrator = new StateHydrator();
        $data = $hydrator->hydrate($data, $this->stateDefinitions);
    }
    $this->container->replaceClean($data);
    // ... sync s Livewire
}
```

#### 4.3 Automatické type definitions

`FormConfig::materialize()` iteruje schema a sbírá type definitions:
```php
foreach ($this->schema as $field) {
    if ($field instanceof FieldComponent) {
        $definitions[$field->getName()] = $field->getStateType(); // 'string', 'bool', 'datetime', etc.
    }
}
```

### Soubory k úpravě

| Soubor | Změna |
|--------|-------|
| `packages/forms/src/Forms/Runtime/StateManager.php` | Integrace StateHydrator |
| `packages/forms/src/Forms/Config/FormConfig.php` | `stateDefinitions` property |
| `packages/forms/src/Forms/Config/ConfigBuilder.php` | Sbírání type definitions ze schematu |
| `packages/forms/src/Components/FieldComponent.php` | `getStateType(): string` metoda |

### Rizika

- **Type mismatch** — Livewire wire:model binduje přímo na component property, hydrace se musí spustit ve správný moment
- **Nullable handling** — prázdný string vs null vs 0
- **Performance** — hydrace každého fill() volání (minimální overhead)

### Testy

- Unit: StateManager s StateHydrator — `"1"` → `true` pro Toggle
- Unit: StateManager bez definitions — pass-through (zpětná kompatibilita)
- Feature: Form fill z DB → hydrated types → wire:model → submit → správné typy

---

## 5. Filter views → form field komponenty

### Problém

5 filter Blade views (`select.blade.php`, `date.blade.php`, `number-range.blade.php`, `ternary.blade.php`, `text.blade.php`) jsou samostatné šablony s vlastním HTML/Alpine. Nepoužívají existující form field komponenty (`Select`, `DateTimePicker`, `TextInput`), což znamená duplicitní UI kód a nekonzistentní UX.

### Cíl

Filter views renderují form field komponenty interně. Vizuální výsledek je identický, ale implementace je sdílená.

### Plán

#### 5.1 Filter → Field mapping

| Filter | Form field |
|--------|-----------|
| `SelectFilter` | `Select` |
| `DateFilter` | `DateTimePicker` |
| `NumberRangeFilter` | 2× `TextInput` (type=number) |
| `TernaryFilter` | `Select` (yes/no/all) |
| `TextFilter` | `TextInput` |

#### 5.2 Filter base class rozšíření

```php
// packages/table/src/Filters/Filter.php
abstract class Filter
{
    /**
     * Build the form field(s) used to render this filter.
     * @return array<FieldComponent>
     */
    abstract public function getFormFields(): array;

    /**
     * Render the filter using form field components.
     */
    public function render(): View
    {
        return view('wire-table::tables.filters.form-field', [
            'fields' => $this->getFormFields(),
            'wireModelPrefix' => "tableFilters.{$this->getName()}",
        ]);
    }
}
```

#### 5.3 Konkrétní filter implementace

```php
class SelectFilter extends Filter
{
    public function getFormFields(): array
    {
        return [
            Select::make('value')
                ->options($this->getOptions())
                ->placeholder($this->getPlaceholder())
                ->searchable($this->isSearchable()),
        ];
    }
}

class NumberRangeFilter extends Filter
{
    public function getFormFields(): array
    {
        return [
            TextInput::make('from')
                ->type('number')
                ->placeholder(__('wire-table::messages.from')),
            TextInput::make('to')
                ->type('number')
                ->placeholder(__('wire-table::messages.to')),
        ];
    }
}
```

#### 5.4 Nová generická Blade šablona

```blade
{{-- wire-table::tables.filters.form-field --}}
<div class="wire-filter">
    @foreach ($fields as $field)
        {!! $field->statePath($wireModelPrefix)->toHtml() !!}
    @endforeach
</div>
```

#### 5.5 Staré views

Přesunout do `views/tables/filters/legacy/` s `@deprecated` komentářem. Odstranit ve v3.

### Soubory k úpravě

| Soubor | Změna |
|--------|-------|
| `packages/table/src/Filters/Filter.php` | `getFormFields()` abstract, `render()` delegace |
| `packages/table/src/Filters/SelectFilter.php` | Implementace `getFormFields()` |
| `packages/table/src/Filters/DateFilter.php` | Implementace `getFormFields()` |
| `packages/table/src/Filters/NumberRangeFilter.php` | Implementace `getFormFields()` |
| `packages/table/src/Filters/TernaryFilter.php` | Implementace `getFormFields()` |
| `packages/table/src/Filters/TextFilter.php` | Implementace `getFormFields()` (pokud existuje) |
| `packages/table/resources/views/tables/filters/form-field.blade.php` | NOVÝ — generická šablona |
| `packages/table/resources/views/tables/filters/*.blade.php` | Přesun do `legacy/` |

### Rizika

- **Styling konzistence** — form fields mají vlastní sizing/spacing, filter context je kompaktnější
- **wire:model paths** — musí matchovat existující `tableFilters.{name}.value` strukturu
- **Alpine interakce** — některé filtry mají custom Alpine behavior (date picker)

### Testy

- Feature: Každý filter renderuje form field a wire:model binduje správně
- Visual: Porovnání starých vs nových filtrů (manuální QA)

---

## 6. Modal shell jako `<x-wire::modal>` komponenta

### Problém

Modal HTML shell (overlay, panel, close button, header/body/footer slots) je inlinovaný v `index.blade.php` (~600+ řádků). Existují 3 Blade views v `core/resources/views/modals/` (modal, slide-over, confirmation), ale action modal v table je renderovaný přímo v `index.blade.php`.

### Cíl

Extrahovat modal shell do Blade komponenty `<x-wire::modal>` s atributy pro konfiguraci. Table `index.blade.php` ji jen volá s content sloty.

### Plán

#### 6.1 Blade komponenta

```php
// packages/core/src/Foundation/View/Components/Modal.php
class Modal extends BladeComponent
{
    public function __construct(
        public bool $show = false,
        public string $maxWidth = '2xl',
        public bool $closeable = true,
        public bool $slideOver = false,
        public ?string $heading = null,
        public ?string $subheading = null,
        public ?string $icon = null,
        public ?string $iconColor = null,
    ) {}

    public function render(): View
    {
        return view('wire-core::components.modal');
    }
}
```

#### 6.2 Blade šablona

```blade
{{-- wire-core::components.modal --}}
<div
    x-data="wireModal({ show: @entangle($attributes->wire('model')) })"
    x-show="show"
    x-cloak
    {{ $attributes->merge(['class' => 'wire-modal']) }}
>
    <div class="wire-modal-overlay" @click="closeable && close()"></div>
    <div class="wire-modal-panel wire-modal-{{ $maxWidth }} @if($slideOver) wire-slide-over @endif">
        @if($heading)
            <div class="wire-modal-header">
                @if($icon)
                    <x-wire::icon :name="$icon" :color="$iconColor" />
                @endif
                <h3>{{ $heading }}</h3>
                @if($subheading)<p>{{ $subheading }}</p>@endif
            </div>
        @endif

        <div class="wire-modal-body">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="wire-modal-footer">
                {{ $footer }}
            </div>
        @endisset

        @if($closeable)
            <button @click="close()" class="wire-modal-close">
                <x-wire::icon name="x-mark" />
            </button>
        @endif
    </div>
</div>
```

#### 6.3 Registrace

V `WireCoreServiceProvider`:
```php
Blade::component('wire::modal', Modal::class);
```

#### 6.4 Refaktor index.blade.php

Nahradit inline modal HTML:

```blade
{{-- Aktuální stav (zjednodušeno): ~200 řádků inline HTML --}}
@if($showActionModal)
    <div class="fixed inset-0 z-50 ...">
        <div class="modal-overlay ...">...</div>
        <div class="modal-panel ...">
            <div class="modal-header">...</div>
            <div class="modal-body">...</div>
            <div class="modal-footer">...</div>
        </div>
    </div>
@endif

{{-- V2: ~20 řádků --}}
<x-wire::modal
    wire:model="showActionModal"
    :heading="$modalData['heading'] ?? null"
    :subheading="$modalData['subheading'] ?? null"
    :icon="$modalData['icon'] ?? null"
    :icon-color="$modalData['iconColor'] ?? null"
    :max-width="$modalData['maxWidth'] ?? '2xl'"
>
    @if($actionFormInstance)
        {!! $actionFormInstance->toHtml() !!}
    @else
        @include('wire-table::tables.partials.modal-form', ['modalData' => $modalData])
    @endif

    <x-slot:footer>
        @include('wire-table::tables.partials.modal-actions', ['modalData' => $modalData])
    </x-slot:footer>
</x-wire::modal>
```

Stejně pro halt modal a případně confirmation modal.

#### 6.5 Alpine JS refaktor

Extrahovat Alpine `wireModal` jako reusable Alpine data:
```js
// wire-core Alpine plugin
Alpine.data('wireModal', ({ show }) => ({
    show,
    close() { this.show = false; },
    // focus trap, escape key, scroll lock
}))
```

### Soubory k úpravě

| Soubor | Změna |
|--------|-------|
| `packages/core/src/Foundation/View/Components/Modal.php` | NOVÝ |
| `packages/core/resources/views/components/modal.blade.php` | NOVÝ |
| `packages/core/src/WireCoreServiceProvider.php` | Registrace Blade komponenty |
| `packages/table/resources/views/tables/index.blade.php` | Nahradit inline modal → `<x-wire::modal>` |
| `packages/core/resources/js/` | Alpine `wireModal` data |

### Rizika

- **Alpine state sync** — `x-show` + `wire:model` musí spolupracovat bez flickeringu
- **Transition animations** — stávající CSS transitions se musí zachovat
- **Slot rendering order** — Blade slot rendering vs Livewire lifecycle timing

### Testy

- Feature: Modal open/close přes Livewire
- Feature: Form submission přes modal
- Visual: Konzistence s aktuálním designem (manuální QA)

---

## 7. Plugin systém — opravy a vylepšení

### Analýza současného stavu

Plugin systém (`PluginManager`, `Plugin` contract) má solidní základ — lifecycle register/boot, hook systém, registrace query pipes, column/filter typů. Existuje jeden reálný plugin (`SortablePlugin`). Nicméně analýza odhalila **10 kritických problémů**:

#### P1. Hooks nejsou nikde v runtime volány (KRITICKÉ)

`runHook()` se nevolá z žádného runtime kódu. Dokumentace definuje 7 hooků (`table.configuring`, `table.querying`, `table.queried`, `form.saving`, `form.saved`, `action.executing`, `action.executed`), ale žádný z nich nikdo nedispatchuje.

**Důkaz:** `grep -r "runHook" packages/` vrací výsledky pouze v:
- `PluginManager.php` (definice)
- `PluginManagerTest.php` (testy)
- `SortablePluginTest.php` (testy)

`SortablePlugin` registruje `table.querying` hook a nastavuje `force_sort_column` v payloadu, ale nikdo tento payload nečte.

#### P2. QueryExecutor ignoruje plugin pipes (KRITICKÉ)

`QueryExecutor::getDefaultPipes()` vrací hardcoded 8 pipes. Nikde nekonzultuje `PluginManager::getQueryPipes()`. Plugin pipes registrované přes `addQueryPipe()` se nikdy nespustí.

#### P3. Column/filter type registry se nikde nepoužívá (STŘEDNÍ)

`addColumnType()` a `addFilterType()` ukládají class-string do mapy, ale žádný runtime kód (Table, WithTable, Column factory) tuto mapu nečte. Registrace custom column typu nemá žádný efekt.

#### P4. Hooks nejsou typově bezpečné (STŘEDNÍ)

Payload je `array<string, mixed>`. Callback dostane netypovaný array, musí předpokládat strukturu. Žádná validace, žádné IDE autocomplete, žádná ochrana proti typo v klíči.

#### P5. Žádná priorita hooků (NÍZKÁ)

Hooky se spouští v pořadí registrace. Nelze říct "tento hook musí běžet první" nebo "tento hook musí běžet poslední". Pro multi-tenancy plugin (scope musí být první) nebo audit plugin (log musí být poslední) je to omezení.

#### P6. Žádné závislosti mezi pluginy (NÍZKÁ)

Plugin nemůže deklarovat, že vyžaduje jiný plugin. `SortablePlugin` by měl záviset na `wire-table`, ale nemůže to vyjádřit.

#### P7. Žádná konfigurace pluginů (NÍZKÁ)

Plugin dostane `PluginManager` v `register()` a `boot()`, ale nemá standardní způsob, jak přijmout konfiguraci od uživatele (např. `'export' => ['format' => 'xlsx', 'chunk_size' => 500]`).

#### P8. Chybí unhook/disable mechanismus (NÍZKÁ)

Registrovaný hook nelze odebrat. Plugin nelze deaktivovat bez odebrání z config.

#### P9. Hook system duplikuje Laravel Events (DESIGN)

9 Event tříd v `Core\Events\` (`ActionExecuted`, `CellUpdated`, `TableFiltered`, ...) pokrývají stejné lifecycle body jako hooks (`action.executed`, `form.saved`, `table.queried`). Dva paralelní systémy pro stejný účel.

#### P10. Chybí Action type extension point (NÍZKÁ)

Existuje `addColumnType()` a `addFilterType()`, ale chybí `addActionType()` pro custom action typy.

---

### Plán oprav

Opravy jsou rozdělené na **P-A (kritické — runtime wiring)** a **P-B (vylepšení — API rozšíření)**.

**Stav k 2026-05-25:** implementováno a otestováno. Hotové jsou runtime hook dispatch body pro table/form/action, zapojení plugin query pipes, consumption column/filter/action type registries, hook priority, typed payload DTO + `runTypedHook()`, opt-in plugin dependencies/config a SortablePlugin force-sort wiring. Ověření: PHPStan, Pint a plný Pest suite.

#### 7A. Runtime wiring — hooks a pipes (KRITICKÉ)

##### 7A.1 Hook dispatch v TableQueryService

```php
// packages/table/src/Concerns/TableQueryService.php — buildQuery()

// Před plan (hook: table.configuring)
if (app()->bound(PluginManager::class)) {
    $manager = app(PluginManager::class);

    $payload = $manager->runHook('table.configuring', [
        'table' => $table,
        'columns' => $columns,
        'filters' => $filters,
    ]);
    // Pluginy mohou modifikovat columns/filters
    $columns = $payload['columns'] ?? $columns;
    $filters = $payload['filters'] ?? $filters;
}

// Po plan, před execute (hook: table.querying)
$payload = $manager->runHook('table.querying', [
    'table' => $table,
    'plan' => $this->lastPlan,
    'query' => $baseQuery,
]);
// Plugin může přepsat sort (SortablePlugin use-case)
if (isset($payload['force_sort_column'])) {
    // Override sort v QueryPlan nebo post-execute
}

// Po execute (hook: table.queried)
$manager->runHook('table.queried', [
    'table' => $table,
    'query' => $query,
    'plan' => $this->lastPlan,
]);
```

##### 7A.2 Hook dispatch v SaveHandler

```php
// packages/forms/src/Forms/Runtime/SaveHandler.php — save()

// Před validací (hook: form.saving)
if (app()->bound(PluginManager::class)) {
    $payload = app(PluginManager::class)->runHook('form.saving', [
        'config' => $this->config,
        'data' => $data,
    ]);
    $data = $payload['data'] ?? $data;
}

// Po uložení (hook: form.saved)
app(PluginManager::class)->runHook('form.saved', [
    'config' => $this->config,
    'record' => $record,
]);
```

##### 7A.3 Hook dispatch v WithTable (akce)

```php
// packages/table/src/Concerns/WithTable.php — executeActionPipeline()

// Před execute (hook: action.executing)
$manager->runHook('action.executing', [
    'action' => $action,
    'context' => $context,
]);

// Po execute (hook: action.executed)
$manager->runHook('action.executed', [
    'action' => $action,
    'result' => $result,
]);
```

##### 7A.4 Plugin pipes v QueryExecutor

Dva přístupy:

**Varianta A — QueryExecutor konzumuje PluginManager přímo:**

```php
// packages/core/src/Core/Query/QueryExecutor.php
private function getDefaultPipes(Builder $builder, ?string $searchTerm): array
{
    $corePipes = [
        new ApplyScopes,
        new ApplySoftDeletes,
        new ApplyRelations,
        new ApplySearch($strategy, $searchTerm),
        new ApplyFilters,
        new ApplySorting,
        new ApplyAggregates,
        new ApplyEagerLoads,
    ];

    // Append plugin pipes
    if (app()->bound(PluginManager::class)) {
        $pluginPipes = app(PluginManager::class)->getQueryPipes();
        $corePipes = array_merge($corePipes, array_values($pluginPipes));
    }

    return $corePipes;
}
```

**Varianta B — TableQueryService předává pipes přes `withPipes()`:**

```php
// packages/table/src/Concerns/TableQueryService.php
$executor = new QueryExecutor;

if (app()->bound(PluginManager::class)) {
    $pluginPipes = app(PluginManager::class)->getQueryPipes();
    if ($pluginPipes !== []) {
        $defaultPipes = [...]; // 8 default + plugin
        $executor = $executor->withPipes($defaultPipes);
    }
}
```

**Doporučení:** Varianta B — zachovává QueryExecutor jako čistou třídu bez service container závislosti. TableQueryService je bridge a patří mu orchestrace.

##### 7A.5 Column/filter type registry consumption

```php
// packages/table/src/Table.php nebo WithTable.php
// Při resolving custom column type:
public function resolveColumnType(string $type): ?string
{
    if (app()->bound(PluginManager::class)) {
        $types = app(PluginManager::class)->getColumnTypes();
        if (isset($types[$type])) {
            return $types[$type];
        }
    }
    return null;
}
```

Reálně — column/filter types se nepoužívají přes string lookup (uživatel přímo importuje `SparklineColumn::make(...)`). Registry je užitečná pro:
- Config-driven table builders (JSON → Column instances)
- Debug/introspekce (jaké typy jsou registrované)

**Doporučení:** Ponechat registry pro introspekci, přidat `resolveColumnType()` / `resolveFilterType()` metody na Table pro budoucí config-driven use-case.

#### 7B. Typově bezpečné hook payloady

##### 7B.1 Hook payload DTO třídy

```php
// packages/core/src/Core/Plugin/Hooks/
final readonly class TableConfiguringPayload
{
    public function __construct(
        public Table $table,
        public array $columns,
        public array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'columns' => $this->columns,
            'filters' => $this->filters,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            table: $data['table'],
            columns: $data['columns'],
            filters: $data['filters'],
        );
    }
}

final readonly class TableQueryingPayload { /* table, plan, query */ }
final readonly class TableQueriedPayload { /* table, query, plan, results */ }
final readonly class FormSavingPayload { /* config, data */ }
final readonly class FormSavedPayload { /* config, record */ }
final readonly class ActionExecutingPayload { /* action, context */ }
final readonly class ActionExecutedPayload { /* action, result */ }
```

##### 7B.2 Typed hook metoda na PluginManager

```php
/**
 * Run a typed hook. Callback receives and returns a payload DTO.
 *
 * @template T of object
 * @param  string  $name
 * @param  T  $payload
 * @return T
 */
public function runTypedHook(string $name, object $payload): object
{
    foreach ($this->hooks[$name] ?? [] as $callback) {
        $result = $callback($payload);
        if ($result !== null && is_object($result)) {
            $payload = $result;
        }
    }
    return $payload;
}
```

Stará `runHook(string, array)` zůstává pro zpětnou kompatibilitu. Nový kód používá `runTypedHook()`.

##### 7B.3 Typed hook registrace

```php
// Plugin
$manager->hook('table.configuring', function (TableConfiguringPayload $payload) {
    // IDE autocomplete funguje
    $payload->table->getColumns();
    return $payload;
});
```

Callback type-hint slouží jako dokumentace. Runtime se nevaliduje (performance).

#### 7C. Hook priorita

##### 7C.1 Priority parametr

```php
public function hook(string $name, callable $callback, int $priority = 0): void
{
    $this->hooks[$name][] = ['callback' => $callback, 'priority' => $priority];
}

public function runHook(string $name, array $payload = []): array
{
    $hooks = $this->hooks[$name] ?? [];

    // Sort by priority (lower = earlier)
    usort($hooks, fn ($a, $b) => $a['priority'] <=> $b['priority']);

    foreach ($hooks as $hook) {
        $result = ($hook['callback'])($payload);
        if (is_array($result)) {
            $payload = $result;
        }
    }

    return $payload;
}
```

Konvence:
- `-100` — security/scope (multi-tenancy)
- `0` — default
- `100` — audit/logging

#### 7D. Plugin závislosti

##### 7D.1 Rozšíření Plugin interface

```php
interface Plugin
{
    public function getId(): string;
    public function register(PluginManager $manager): void;
    public function boot(PluginManager $manager): void;

    /**
     * Plugin IDs that must be registered before this plugin.
     * @return array<int, string>
     */
    public function dependencies(): array;  // default [] v abstract base
}
```

**Zpětná kompatibilita:** Přidat `HasDependencies` interface místo změny `Plugin`:

```php
interface HasDependencies
{
    /** @return array<int, string> */
    public function dependencies(): array;
}
```

`PluginManager::register()` kontroluje:
```php
if ($plugin instanceof HasDependencies) {
    foreach ($plugin->dependencies() as $dep) {
        if (!$this->has($dep)) {
            throw new RuntimeException(
                "Plugin '{$plugin->getId()}' requires '{$dep}' which is not registered."
            );
        }
    }
}
```

#### 7E. Plugin konfigurace

##### 7E.1 HasConfiguration interface

```php
interface HasConfiguration
{
    /**
     * Default configuration values.
     * @return array<string, mixed>
     */
    public function defaultConfig(): array;
}
```

##### 7E.2 Config resolution v PluginManager

```php
public function register(Plugin $plugin): void
{
    // ... existing logic ...

    if ($plugin instanceof HasConfiguration) {
        $configKey = "wire-core.plugins.config.{$plugin->getId()}";
        $userConfig = config($configKey, []);
        $merged = array_merge($plugin->defaultConfig(), $userConfig);
        $this->pluginConfigs[$plugin->getId()] = $merged;
    }
}

public function getPluginConfig(string $pluginId): array
{
    return $this->pluginConfigs[$pluginId] ?? [];
}
```

Uživatel konfiguruje v `config/wire-core.php`:
```php
'plugins' => [
    ExportPlugin::class,
],
'plugins' => [
    'config' => [
        'export' => ['format' => 'xlsx', 'chunk_size' => 500],
    ],
],
```

#### 7F. Konsolidace Hooks vs Laravel Events

##### Problém

Hooks (`table.querying`) a Events (`TableSearching`, `TableFiltered`) pokrývají stejné lifecycle body. Dva systémy pro jeden účel.

##### Rozhodnutí

**Hooks** a **Events** mají různé účely — zachovat oba:

| Aspekt | Hooks | Laravel Events |
|--------|-------|----------------|
| **Účel** | Modifikace chování (payload transform) | Notifikace (read-only observation) |
| **Návratová hodnota** | Modifikovaný payload | Void (listener nevrací) |
| **Timing** | Synchronní, v pipeline | Synchronní nebo queued |
| **Konzumenti** | Pluginy | Application code, queued jobs |
| **Příklad** | Multi-tenancy přidává `tenant_id` do form data | Audit log zaznamenává akci |

**Pravidlo:** Hook dispatch PŘED Event dispatch. Hook může modifikovat, Event reportuje finální stav.

```php
// V runtime:
// 1. Hook — plugin může modifikovat
$payload = $manager->runHook('action.executed', [...]);

// 2. Event — notifikace o výsledku
event(new ActionExecuted($action, $result));
```

#### 7G. Action type extension point

```php
// PluginManager
/** @var array<string, class-string> */
private array $actionTypes = [];

public function addActionType(string $name, string $actionClass): void
{
    $this->actionTypes[$name] = $actionClass;
}

/** @return array<string, class-string> */
public function getActionTypes(): array
{
    return $this->actionTypes;
}
```

---

### Soubory k úpravě

| Soubor | Změna |
|--------|-------|
| `packages/core/src/Core/Plugin/PluginManager.php` | Priority, `runTypedHook()`, `addActionType()`, plugin configs |
| `packages/core/src/Core/Plugin/Contracts/Plugin.php` | Beze změny (nové interfaces místo změny) |
| `packages/core/src/Core/Plugin/Contracts/HasDependencies.php` | NOVÝ |
| `packages/core/src/Core/Plugin/Contracts/HasConfiguration.php` | NOVÝ |
| `packages/core/src/Core/Plugin/Hooks/TableConfiguringPayload.php` | NOVÝ |
| `packages/core/src/Core/Plugin/Hooks/TableQueryingPayload.php` | NOVÝ |
| `packages/core/src/Core/Plugin/Hooks/TableQueriedPayload.php` | NOVÝ |
| `packages/core/src/Core/Plugin/Hooks/FormSavingPayload.php` | NOVÝ |
| `packages/core/src/Core/Plugin/Hooks/FormSavedPayload.php` | NOVÝ |
| `packages/core/src/Core/Plugin/Hooks/ActionExecutingPayload.php` | NOVÝ |
| `packages/core/src/Core/Plugin/Hooks/ActionExecutedPayload.php` | NOVÝ |
| `packages/table/src/Concerns/TableQueryService.php` | Hook dispatch (configuring, querying, queried) + plugin pipes |
| `packages/table/src/Concerns/WithTable.php` | Hook dispatch (action.executing, action.executed) |
| `packages/forms/src/Forms/Runtime/SaveHandler.php` | Hook dispatch (form.saving, form.saved) |
| `packages/core/tests/Unit/Core/Plugin/PluginManagerTest.php` | Nové testy pro priority, typed hooks, dependencies, config |
| `packages/sortable/src/SortablePlugin.php` | Implementovat `HasDependencies` (vyžaduje wire-table) |

### Implementační pořadí

| Krok | Oblast | Závislost |
|------|--------|-----------|
| 7.1 | Hook dispatch v runtime (7A.1–7A.3) | Žádná — nejkritičtější fix |
| 7.2 | Plugin pipes v QueryExecutor (7A.4) | Žádná — paralelní s 7.1 |
| 7.3 | Hook priorita (7C) | Po 7.1 (mění runHook signaturu) |
| 7.4 | Typed payloady (7B) | Po 7.1 (přidává nové DTO) |
| 7.5 | Plugin závislosti (7D) | Po 7.1 |
| 7.6 | Plugin konfigurace (7E) | Po 7.1 |
| 7.7 | Action type extension (7G) | Nezávislé |
| 7.8 | Column/filter type consumption (7A.5) | Nezávislé |

### Rizika

- **Performance** — `app()->bound()` check v hot path (TableQueryService). Mitigace: cache `PluginManager` reference v property
- **Hook payload BC** — přidání typed payloadů nesmí rozbít existující array-based hooks. Mitigace: `runHook()` zůstává, `runTypedHook()` je nové API
- **Plugin interface BC** — nové metody nelze přidat na interface. Mitigace: `HasDependencies`/`HasConfiguration` jako opt-in interfaces
- **SortablePlugin** — aktuálně nastavuje `force_sort_column` v payload, ale nikdo ho nečte. Fix v 7A.1 musí implementovat i čtení tohoto klíče v `TableQueryService`

### Testy

- Unit: `runHook()` dispatch z `TableQueryService` — hook modifikuje columns
- Unit: `runHook()` dispatch z `SaveHandler` — hook modifikuje data
- Unit: Plugin pipes se aplikují v QueryExecutor pipeline
- Unit: Hook priority ordering
- Unit: Typed payload round-trip
- Unit: `HasDependencies` — chybějící dependency → RuntimeException
- Unit: `HasConfiguration` — merge user config + defaults
- Feature: SortablePlugin `force_sort_column` skutečně přeřadí query
- Feature: Multi-tenancy plugin scope se aplikuje na query

---

## Závislostní graf

```
7. Plugin systém opravy (7A: runtime wiring) ◄── PRVNÍ — odblokuje SortablePlugin
   │
   ├──► 7B-G. Plugin vylepšení (typed payloady, priorita, závislosti, config)
   │
1. StateContainer (Livewire Synthesizer)
   │
   ├──► 2. Metadata + Capabilities plná integrace
   │        │
   │        └──► 5. Filter views → form fields (potřebuje capabilities)
   │
   ├──► 4. StateHydrator v StateManager
   │        │
   │        └──► 3. Hydration v SaveHandler (potřebuje hydrated types)
   │
   └──► 6. Modal shell komponenta (nezávislé, paralelizovatelné s 2-5)
```

**Doporučené pořadí implementace:**

| Fáze | Položky | Paralelizovatelné |
|------|---------|-------------------|
| V2.0 | #7A Runtime wiring (hooks + pipes) | Ano — lze před vším ostatním |
| V2.1 | #1 StateContainer + #6 Modal shell + #7B-G Plugin vylepšení | Ano (3 nezávislé) |
| V2.2 | #4 StateHydrator + #2 Metadata/Capabilities | Ano (nezávislé) |
| V2.3 | #3 Hydration v SaveHandler | Ne (závisí na #4) |
| V2.4 | #5 Filter views → form fields | Ne (závisí na #2) |

---

## Migrace a zpětná kompatibilita

### Strategie

1. **Deprecation-first** — staré API funguje s deprecation warning v celém v2 cyklu
2. **Removal v v3** — kompletní odstranění deprecated API
3. **Migration guide** — dokument s before/after příklady pro každou změnu
4. **Codemods** — kde je to možné, poskytnout Rector rules pro automatickou migraci

### Breaking changes shrnutí

| Změna | Dopad | Mitigace |
|-------|-------|----------|
| `$tableSortColumn` → `$tableState->get('sort.column')` | Kód přistupující k public properties | `__get`/`__set` magic + deprecation |
| Auto-resolved capabilities | Columns mohou být searchable bez explicitního volání | Opt-in přes config flag |
| SaveHandler přes Dehydrator | Custom `using()` callbacks s raw data | `using()` bypass zůstává |
| Filter views změna | Custom filter šablony | Legacy views zachovány |
| Modal shell refaktor | Custom modal CSS targeting inline HTML | CSS třídy zachovány |
| Hook `runHook()` priority parametr | Stávající hooks bez priority → `priority=0` | Zpětně kompatibilní default |
| Typed hook payloady | Nové `runTypedHook()` API | `runHook()` array API zůstává |
| `HasDependencies` / `HasConfiguration` | Nové opt-in interfaces | `Plugin` interface beze změny |

---

## Kritéria dokončení

Každá položka je hotová když:

1. Implementace prochází PHPStan level 6
2. Všechny existující testy prochází beze změn (nebo s minimální úpravou)
3. Nové testy pokrývají novou funkcionalitu (min 80% coverage nového kódu)
4. Deprecation warnings jsou na místě pro staré API
5. Dokumentace v `architecture/decisions/` (ADR pro každou položku)
6. Žádná performance regrese (benchmark před/po)
