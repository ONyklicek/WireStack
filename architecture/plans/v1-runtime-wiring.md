# Plan: V1 Architecture Alignment - Wiring Core Into Runtime

## Context

Codebase ma kompletni `Core/` namespace (ActionPipeline, ValidationPipeline, StateContainer, Events, Hydration, Metadata) s plnymi unit testy — ale ZADNY z nich neni propojeny s runtime. `WithTable` pouziva hand-rolled implementace (75-radkovy pipeline, 4x `Validator::make()`, 26 raw public properties). Action modaly renderuji formulare z raw poli `['name','type']` misto Form systemu. Legacy confirmation modal nema zadneho volajiciho. 9 event trid neni nikde dispatchovano.

**Cilem je propojit existujici Core moduly s runtime bez breaking changes.**

---

## Phase 1 — Container Registration (S)

### Co
Zaregistrovat Core services v `WireCoreServiceProvider` — prerequisite pro vsechny dalsi faze.

### Soubory
- `packages/core/src/WireCoreServiceProvider.php`

### Zmeny
Pridat `registerCore()` volanou z `register()`:
```php
private function registerCore(): void
{
    $this->app->singleton(ValidationPipeline::class);
    $this->app->singleton(ActionRegistry::class);
    $this->app->singleton(MetadataRegistry::class);
    // ActionPipeline je transient (kazde execute = novy pipeline)
    $this->app->bind(ActionPipeline::class);
}
```

### Overeni
- `app(ValidationPipeline::class)` vraci instanci
- `app(ActionPipeline::class)` vraci novou instanci pri kazdem resolve
- Vsechny existujici testy prochazi beze zmen

---

## Phase 2 — ActionPipeline Integration (L)

### Co
Nahradit hand-rolled `executeActionPipeline()` ve `WithTable` delegaci na `Core\Actions\ActionPipeline`.

### Soubory
- `packages/table/src/Concerns/WithTable.php`

### Zmeny

1. **Pridat `payloadToContext()`** — prevede stary `$payload` array na `ActionContext`:
   ```php
   private function payloadToContext(array $payload, string $actionName): ActionContext
   {
       return new ActionContext(
           record: $payload['record'] ?? null,
           records: $payload['records'] ?? null,
           formData: $payload['data'] ?? [],
           actionName: $actionName,
       );
   }
   ```

2. **Pridat `contextToPayload()`** — prevede `ActionContext` zpet na named-parameter array pro `invokeActionCallback()` (zpetna kompatibilita se stary closures):
   ```php
   private function contextToPayload(ActionContext $ctx): array
   ```

3. **Prepsat `executeActionPipeline()` telo** (signatura zustava stejna):
    - Vytvori `ActionContext` z `$payload`
    - Wrappne before/after callbacks do adapter closures: `fn(ActionContext $ctx) => $this->invokeActionCallback($cb, $this->contextToPayload($ctx))`
    - Adapter detekuje `ActionHalt` navrat a ulozi do `$ctx->set('pendingHalt', $halt)`
    - Zavola `app(ActionPipeline::class)->execute($ctx, $wrappedAction)`
    - Po execute: zkontroluje `pendingHalt` → `showHaltModal()`, `notification` → notify, `redirect` → redirect

4. **`invokeActionCallback()`** zustava beze zmeny (reflection bridge pro existujici akce)

### Overeni
- Akce bez modalu funguje (execute → success → invalidate)
- Akce s halt modalem zobrazi modal
- Before/after callbacks se volaji ve spravnem poradi
- `confirmed=true` preskoci before callbacks
- Bulk akce deselectuji po dokonceni

---

## Phase 3 — ValidationPipeline Integration (M)

### Co
Nahradit 4x `Validator::make()` ve `WithTable` za `ValidationPipeline`.

### Soubory
- `packages/table/src/Concerns/WithTable.php`

### Zmeny

Nahradit ve 4 metodach:

| Metoda | Radek | Zmena |
|--------|-------|-------|
| `validateActionModalForm()` | ~1317 | `app(ValidationPipeline::class)->validate($data, $rules, $messages, $attributes)` → throw pokud `->failed()` |
| `submitHaltModal()` | ~1523 | Stejne |
| `updateTableCell()` pre-validate | ~1667 | Stejne |
| `validateTableCell()` | ~1849 | Stejne |

Helper pro konverzi `ValidationResult` na `ValidationException`:
```php
private function throwIfValidationFailed(ValidationResult $result): void
{
    if ($result->failed()) {
        throw ValidationException::withMessages($result->errors());
    }
}
```

Odebrat `use Illuminate\Support\Facades\Validator;` import.

### Overeni
- Modal form validace stale hazí chyby do Livewire error bagu
- Cell validace vraci stejnou strukturu chyb
- PHPStan prochazi

---

## Phase 4 — Form Integration in Action Modals (L) ✅ DONE

### Co
Umoznit action modalum pouzit `Form` instanci misto raw field arrays. Zpetne kompatibilni — raw arrays stale fungují.

### Implementovane zmeny

**HasModal.php:**
- `form()` signatura: `array|Form|Closure` (bylo `array|Closure`)
- Pridat `protected Form|Closure|null $formInstance = null`
- Auto-detekce Component objektu v array: `form([TextInput::make('name')])` → automaticky zabali do `Form::make()->schema([...])` (fix: `Component` nema `toArray()`, takze `normalizeFormFields` je tichy zahazoval)
- `containsComponents(array): bool` — detekuje pritomnost `FieldComponent` instanci v poli
- `getFormInstance(?Component $livewire, mixed $context): ?Form` — resolvuje Form (vcetne closure→Form), nastavuje `statePath('actionModalFormData')`, `livewire($livewire)`, plni `fillFormUsing` defaults
- `hasFormInstance(): bool` — rychly check jestli je Form nakonfigurovany
- `hasFormModal()` a `doesRequireConfirmation()` — updatovany pro Form instance
- `getFormFields()` a `getFormDefaults()` — vraci `[]` kdyz je Form instance (Form si ridi vlastni stav); `getFormFields()` osetruje i pripad kdy closure vraci `Form` misto array
- `getModalConfig()` — pridat `'hasFormInstance'` flag

**WithTable.php:**
- `protected ?Form $actionModalFormInstance` property (neni serializovana Livewire — re-resolvuje se on demand)
- `openActionModal()`, `openBulkActionModal()`, `openHeaderActionModal()` — volaji `$action->getFormInstance($this, $context)` a ukladaji vysledek
- `submitActionModal()` — re-resolvuje Form instanci pred validaci (neni serializovana mezi Livewire requesty), pouzije `$form->validate()` misto legacy `validateActionModalForm()`
- `closeActionModal()` — `$this->actionModalFormInstance = null`
- `getActionModalFormInstance(): ?Form` — lazy re-resolve: pokud `null` a modal je otevreny, zavola `resolveActionModalFormInstance()`
- `resolveActionModalFormInstance()` — najde action podle typu (row/bulk/header), zavola `$action->getFormInstance($this, $context)`

**index.blade.php (oba modal content bloky):**
```blade
@if($actionFormInstance)
    {!! $actionFormInstance->toHtml() !!}
@else
    @include('wire-table::tables.partials.modal-form', ['modalData' => $modalData])
@endif
```

**Halt modal** — beze zmeny (ActionHalt ma vlastni `form(array)` API, neni propojen s Form systemem)

### Opravene bugy
1. **Component array tichy drop** — `normalizeFormFields()` hledala `toArray()` na Component objektech, ktere ji nemaji → fieldy se tichy zahodily → `hasFormModal()` vracelo false → modal se renderoval jako confirmation bez formulare. Fix: `form()` detekuje Component objekty v array a automaticky je zabali do `Form::make()->schema([...])`.
2. **Form instance ztracena mezi Livewire requesty** — `protected ?Form $actionModalFormInstance` neni serializovana Livewire, takze po `openActionModal` → re-render → `submitActionModal` byla `null`. Fix: lazy re-resolve v `getActionModalFormInstance()` a `submitActionModal()` pres novou `resolveActionModalFormInstance()` metodu.

### Podporovane API
```php
// 1. Array of Component objects — auto-wrapped do Form::make()->schema([...])
Action::make('edit')->form([TextInput::make('name'), Select::make('role')->options([...])])

// 2. Form instance primo
Action::make('edit')->form(Form::make()->schema([TextInput::make('name')]))

// 3. Closure vracejici array Component objektu
Action::make('edit')->form(fn ($record) => [TextInput::make('name')->default($record->name)])

// 4. Closure vracejici Form
Action::make('edit')->form(fn ($record) => Form::make()->schema([TextInput::make('name')->default($record->name)]))

// 5. Legacy raw array (zpetne kompatibilni)
Action::make('edit')->form([['name' => 'reason', 'type' => 'textarea', 'label' => 'Duvod']])
```

### Overeni
- PHPStan: 0 errors
- Existujici testy: beze regresi
- Legacy raw array fields: beze zmeny
- Component array: auto-wrap do Form, `wire:model` binduje na `actionModalFormData.{name}`
- Form instance: `wire:model` binduje na `actionModalFormData.{name}` diky `statePath()`
- `fillFormUsing()` callback se aplikuje i na Form instance
- Form instance prezije Livewire request cycle diky lazy re-resolve

---

### Zbyvajici ukoly z Phase 4 (nizka priorita)
- `HasForm.php` interface — smazat nebo opravit (dead code, nikdo neimplementuje)
- `ActionMacros.php` — zatim prazdny, muze registrovat forms-specific extensions v budoucnu

---

## Phase 5 — Event Dispatching (S)

### Co
Dispatchovat 9 existujicich Core event trid z odpovídajicich mist v runtime.

### Soubory
- `packages/table/src/Concerns/WithTable.php`

### Dispatch body

| Event | Dispatch misto |
|-------|---------------|
| `ActionExecuting` | Zacatek `executeActionPipeline()` |
| `ActionExecuted` | `handleActionSuccess()` |
| `CellUpdating` | `updateTableCell()` pred write |
| `CellUpdated` | `updateTableCell()` po uspechu |
| `TableSearching` | `buildTableQuery()` kdyz search != null |
| `TableSearched` | `buildTableQuery()` po aplikaci search |
| `TableFiltering` | `updatedTableFilters()` |
| `TableFiltered` | `buildTableQuery()` po aplikaci filtru |
| `TableRefreshed` | `invalidateTable()` |

Pouzit `event(new EventClass(...))`.

### Overeni
- Pridat test: listener na `ActionExecuted`, spustit akci, assert fired
- Vsechny existujici testy prochazi (eventy jsou additive)

---

## Phase 6 — Dead Code Removal + i18n (M)

### Co
Odstranit legacy confirmation modal, zabalit ceske stringy do `__()`.

### Soubory
- `packages/table/src/Concerns/WithTable.php`
- `packages/core/src/Actions/ActionHalt.php`
- `packages/core/src/Actions/Concerns/HasModal.php`
- `packages/table/src/Filters/Filter.php`, `DateFilter.php`, `NumberRangeFilter.php`
- `packages/table/src/Columns/ButtonColumn.php`, `SelectColumn.php`
- `packages/table/resources/views/tables/index.blade.php`

### Zmeny

**Legacy confirmation modal:**
- Pridat `@deprecated` + `Deprecation::method()` na: `confirmTableAction()`, `executeConfirmedAction()`, `closeConfirmationModal()`, `confirmBulkAction()`, `getConfirmationModalData()`
- Smazat 4 public properties: `$showConfirmationModal`, `$confirmActionName`, `$confirmRecordKey`, `$isBulkAction` (nulovy pocet internich volanich)
- Smazat odpovidajici Blade blok v `index.blade.php` (~80 radku)

**i18n:**
- Vytvorit `packages/core/resources/lang/cs/actions.php` a `en/actions.php`
- Vytvorit `packages/table/resources/lang/cs/messages.php` a `en/messages.php`
- Nahradit hardcoded stringy:
    - `'Potvrdit akci'` → `__('wire-core::actions.confirm_heading')`
    - `'Potvrdit'` → `__('wire-core::actions.confirm_submit')`
    - `'Zrušit'` → `__('wire-core::actions.confirm_cancel')`
    - `'Sloupec nenalezen'` → `__('wire-table::messages.column_not_found')`
    - `'Vyberte...'` → `__('wire-table::messages.select_placeholder')`
    - `'Od'`/`'Do'` → `__('wire-table::messages.from')`/`__('wire-table::messages.to')`
    - atd.

### Overeni
- Blade nereferuje `$showConfirmationModal` ani `confirmTableAction`
- PHPStan prochazi (zadne reference na smazane properties)
- Stringy se resolvi spravne v cs i en locale
- `Deprecation::method()` loguje warning pri volani deprecated metod

---

## Odlozeno na v2 (mimo scope)

| Oblast | Duvod |
|--------|-------|
| StateContainer nahrazujici 26 raw Livewire properties | Breaking change v Livewire serializaci |
| Core/Metadata + Capabilities + Components (DataComponent) | Vyzaduje novou Column dedicnost hierarchii |
| Core/Hydration integrace do SaveHandler | Vyzaduje zmenu save lifecycle |
| Core/State/StateHydrator v StateManager | Vyzaduje zmenu form state flow |
| Filter views → form field komponenty | Kosmeticke, nizka priorita |
| Modal shell jako `<x-wire::modal>` komponenta | ~600 radku refaktoring, UI-only |

---

## Zavislostni graf fazi

```
Phase 1 (Container) ──► Phase 2 (ActionPipeline) ──┐
                   ──► Phase 3 (Validation)     ──┤
                                                   ▼
                                          Phase 4 (Form in Modals)
                                                   │
                                                   ▼
                                          Phase 5 (Events)
                                                   │
                                                   ▼
                                          Phase 6 (Cleanup + i18n)
```

Phases 2 a 3 jsou paralelizovatelne.
