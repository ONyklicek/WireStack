# ADR 0016: V1 Core-Runtime Wiring

## Status

ACCEPTED

## Context

Codebase obsahuje kompletní `Core/` namespace s plně otestovanými moduly:
- `ActionPipeline` (stages: Before → Execution → After → Notification → Redirect)
- `ValidationPipeline` (wrapper nad Laravel Validator s `ValidationResult`)
- `ActionRegistry`, `MetadataRegistry`
- 9 event tříd (`ActionExecuting`, `ActionExecuted`, `CellUpdating`, `CellUpdated`, `TableSearching`, `TableSearched`, `TableFiltering`, `TableFiltered`, `TableRefreshed`)
- `Deprecation` utility

**Žádný z těchto modulů není propojen s runtime.** `WithTable` trait používá:
- Hand-rolled 75-řádkový before/action/after pipeline místo `ActionPipeline`
- 4× přímý `Validator::make()` místo `ValidationPipeline`
- Raw field arrays `['name','type','label']` v action modalech místo `Form` systému
- Hardcoded české stringy bez `__()`
- Legacy confirmation modal (zero interních volajících) redundantní s halt modalem

`TableQueryService` je jediná část, která Core infrastrukturu již používá (`QueryPlanner` → `QueryPlan` → `QueryExecutor`).

## Decision

Propojit existující Core moduly s runtime v 6 fázích, bez breaking changes:

### Phase 1 — Container Registration (S)
Zaregistrovat `ValidationPipeline`, `ActionRegistry`, `MetadataRegistry` jako singletony a `ActionPipeline` jako transient v `WireCoreServiceProvider::registerCore()`.

### Phase 2 — ActionPipeline Integration (L)
Nahradit `WithTable::executeActionPipeline()` delegací na `Core\Actions\ActionPipeline`. Zachovat zpětnou kompatibilitu přes adapter closures, které překládají `ActionContext` na named-parameter array pro existující reflection-based `invokeActionCallback()`.

### Phase 3 — ValidationPipeline Integration (M)
Nahradit 4× `Validator::make()` v `WithTable` za `app(ValidationPipeline::class)->validate()`. Přidat helper `throwIfValidationFailed(ValidationResult)` pro konverzi na `ValidationException`.

### Phase 4 — Form Integration in Action Modals (L)
Umožnit `HasModal::form()` přijímat `Form` instance (ne jen raw arrays). V `WithTable` vytvořit Form s `->statePath('actionModalFormData')->livewire($this)` pro automatický wire:model binding. Zachovat legacy raw array path.

### Phase 5 — Event Dispatching (S)
Dispatchovat 9 Core event tříd z odpovídajících míst v `WithTable` (`executeActionPipeline`, `handleActionSuccess`, `updateTableCell`, `buildTableQuery`, `invalidateTable`).

### Phase 6 — Dead Code Removal + i18n (M)
Deprecovat legacy confirmation modal. Zabalit hardcoded české stringy do `__()` s lang soubory pro `cs` a `en`.

### Dependency Graph

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

### Odloženo na v2

| Oblast | Důvod |
|--------|-------|
| StateContainer nahrazující 26 raw Livewire properties | Breaking change v Livewire serializaci |
| Core/Metadata + Capabilities + Components (DataComponent) | Vyžaduje novou Column dědičnost |
| Core/Hydration integrace do SaveHandler | Vyžaduje změnu save lifecycle |
| Filter views → form field komponenty | Kosmetické, nízká priorita |
| Modal shell jako `<x-wire::modal>` komponenta | ~600 řádků refaktoring, UI-only |

## Consequences

**Pozitivní:**
- Core moduly přestanou být dead code
- Jednotný validation flow (ValidationPipeline) napříč forms i table
- Action lifecycle řízený pipeline stages — extensibilní přes custom stages
- Event dispatching umožní audit logging, monitoring, webhooks
- i18n umožní vícejazyčné nasazení
- Form v action modalech = plné field typy, validace, state management

**Negativní:**
- Phase 2 zvyšuje stack depth action execution (pipeline wrapping)
- Phase 4 vytváří dva rendering paths v modal-form.blade.php (Form vs legacy)
- Deprecation legacy confirmation modalu může rozbít external code volající `confirmTableAction()` přímo

**Rizika:**
- Adapter closures v Phase 2 musí přesně replikovat chování reflection-based named parameter matching
- `ActionContext` property `records` typuje `Collection` — bulk action payload musí být `Collection`, ne raw array
