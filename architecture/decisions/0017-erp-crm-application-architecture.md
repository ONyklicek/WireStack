# ADR 0017: ERP/CRM Application Architecture

## Status

PROPOSED

## Context

Wire má veřejné API, které je ergonomicky blízké Filamentu/Nově:
- fluent builders (`Form`, `Table`, `Action`)
- Blade/Livewire-centric rendering
- fields, columns, actions, widgets, modals
- package split `core` / `forms` / `table` / `sortable`

To je dobrý základ pro admin UI a CRUD aplikace. Není to ale ještě dostatečný
architektonický model pro ERP/CRM software.

ERP/CRM use-cases přidávají požadavky, které běžné admin frameworky neřeší jako
primární invariant:
- workflow a procesní stavy
- silné authorization + scope boundaries
- audit-first mutace
- bulk a cross-record operace
- dlouho běžící a async operace
- velké tabulky a komplexní query planning
- multi-module composition (`crm`, `sales`, `billing`, `inventory`, ...)
- stabilní oddělení UI, execution a domain vrstev

Aktuální implementace už má silné interní stavební bloky (`Core/Query`,
`Core/State`, `Core/Actions`, `Core/Validation`, plugin systém), ale několik
částí je příliš centralizovaných nebo příliš generických:
- `WithTable` je runtime orchestrator s velmi širokým blast radiem
- `Column` je příliš široký base typ a absorbuje mnoho surface-specific chování
- `Core/` funguje jako silný interní engine, ale chybí mu dostatečně explicitní
  higher-level owners (`Resource`, `Page`, `Workspace`)
- extensibility je z velké části hook-based místo explicitních typed contracts
- runtime často používá `app()` a přímé `new` místo jasných seamů mezi vrstvami

Pokud má být Wire “lepší než Filament/Nova pro ERP/CRM”, nesmí cílit na
“více features ve stejném tvaru”. Musí mít:
- DX podobné Filamentu/Nově
- ale architekturu více domain-first, modulární a execution-safe

## Decision

Wire bude cílit na model:

**Filament-like DX, Nova-like ownership, ERP-safe execution model.**

To konkrétně znamená:
- fluent a owner-centric API na povrchu
- modulární a znovupoužitelné internals
- explicitní aplikační vrstvu nad `Form` / `Table` / `Action`
- striktní execution boundaries pro query, save, action, audit a authorization

## Target Architecture

### 1. Foundation

Nejnižší vrstva, bez business orchestrace:
- design semantics (`color`, `icon`, `size`, typography, spacing)
- shared support utilities
- minimal shared concerns
- render-data helpers pro per-surface UI contracts

Foundation:
- nesmí znát `Resource`, `Page`, workflow ani doménové moduly
- musí být znovupoužitelný napříč packages

### 2. UI Primitives

Reusovatelné UI building blocks:
- `Action`
- `Form`
- `Table`
- `Widget`
- `Modal`
- budoucí `Infolist` / detail surfaces

Tyto primitives:
- musí mít silné fluent API
- nesmí vlastnit doménovou orchestrace
- musí být použitelné standalone i jako stavební blok pro vyšší ownery

### 3. Execution

Vrstva, která řeší runtime chování, ne render:
- action execution
- validation
- persistence
- transactions
- audit
- notifications
- queue / async dispatch
- policy + scope enforcement

Sem patří:
- `ActionContext`, `ActionResult`, action handlers / bus
- persistence contracts pro forms a bulk operace
- typed pipelines místo ad-hoc callback řetězců

Execution:
- musí být headless
- nesmí být svázaná s Blade views
- UI na ni pouze deleguje

### 4. Application Surfaces

Vrstva, která dnes v Wire prakticky chybí jako first-class owner:
- `Resource`
- `ListPage`
- `EditPage`
- `ViewPage`
- `RelationManager`
- `Workspace`
- `Dashboard`

Tato vrstva:
- skládá `Form`, `Table`, `Action`, `Widget`
- určuje ownership business use-casu
- je hlavní integrační bod pro ERP/CRM moduly

Application surface je ekvivalent toho, co v Nově drží `Resource` a co ve
Filamentu drží resource/page/screen ownership, ale s větším důrazem na:
- workflow
- module composition
- business navigation
- cross-surface consistency

### 5. Domain Modules

Druhá osa architektury vedle technických packages:
- `crm`
- `sales`
- `billing`
- `inventory`
- `projects`
- `support`

Každý modul má být schopen:
- deklarovat resources a pages
- registrovat workflows a policies
- poskytovat dashboards / widgets / actions
- používat shared primitives bez zásahů do jejich internals

### 6. Infrastructure Adapters

Nejnižší integrační vrstva:
- Livewire adapter
- Eloquent adapter
- export/import adaptery
- queue adapter
- notification driver adaptery
- third-party integration adaptery

Infrastructure:
- nesmí diktovat shape execution modelu
- má být zaměnitelná a testovatelná

## Architectural Invariants

### A. Fluent API je public contract, ne interní coupling

Veřejné API má zůstat ergonomické jako Filament/Nova. To ale nesmí vést k tomu,
že interní runtime zůstane centralizovaný v několika megatřídách.

### B. UI surface a execution surface jsou oddělené

`Action`, `Form`, `Table` nesmí přímo vlastnit persistence, audit a workflow
orchestrace. Musí delegovat na execution contracts.

### C. Table engine musí být headless

Tabulka se dělí na:
- dataset/query engine
- state engine
- selection/bulk engine
- render adapter
- Livewire adapter

Žádná jednotlivá trait nebo třída nesmí držet celý tento stack sama.

### D. Column base class musí zůstat malá

Base `Column` má řešit minimum společných vlastností. Surface-specific a
business-specific chování patří do specializovaných column typů:
- `TextColumn`
- `MoneyColumn`
- `StatusColumn`
- `RelationColumn`
- `EditableColumn`
- `MetricColumn`
- atd.

### E. Forms se dělí na schema / state / validation / persistence / effects

`Form` zůstává public owner, ale interně musí být tvrději odděleno:
- schema definition
- state hydration
- runtime validation
- persistence strategy
- relationship persistence
- side effects

### F. Actions jsou domain commands s UI adaptery

Akce nesmí být jen “button callback”.

Action model musí podporovat:
- UI trigger
- domain command
- bulk operation
- queued operation
- audited operation
- policy-checked operation

### G. Authorization, scope, tenancy a audit jsou first-class invariants

Každá mutace a každý query path musí mít jasné místo, kde se vynucuje:
- view policy
- action policy
- data scope
- tenant scope
- audit trail

Tyto concerny nesmí být ponechány jen custom callbackům.

### H. Extensibility preferuje typed contracts před generic hooks

Plugin/hook systém může zůstat pro integrace a edge cases, ale primární rozšíření
musí jít přes explicitní typed contracts:
- resource contracts
- action handler contracts
- field/column extension contracts
- export/import contracts
- workflow contracts

### I. Design system je semantic + per-surface

Sdílené barvy/ikony/size se centralizují na úrovni semantics, ale rendering musí
zůstat per-surface:
- badge
- text
- button-solid
- button-outlined
- button-link
- icon-button
- dropdown-item
- toggle-track
- banner
- rating-active

Neexistuje jeden univerzální bag CSS tříd pro všechno.

## Package Direction

### `packages/core`

`core` má být:
- Foundation
- base execution infrastructure
- actions/modals/notifications/widgets primitives

`core` nemá být:
- generický catch-all pro veškerou aplikační orchestrace

### `packages/forms`

`forms` má vlastnit:
- field system
- form schema
- state/render adapters
- form-specific runtime

`forms` nemá přímo určovat:
- workflow orchestration
- cross-module business save semantics

### `packages/table`

`table` má vlastnit:
- table primitive
- columns / filters / exports
- query/state engines a jejich adapters

`table` nemá vlastnit:
- resource/page lifecycle
- business workflow ownership

### `packages/sortable`

`sortable` zůstává extension package nad table execution contracts:
- reorder mode
- reorder persistence strategy
- sortable-specific UI and hooks

Nesmí zavádět zpětnou vazbu do `forms` nebo `core` mimo explicitní seam.

## Consequences

### Positive

- Wire přestane být jen admin toolkit a získá tvar application frameworku
- nový ERP/CRM feature work bude mít stabilní owner layer
- menší blast radius při refaktorech
- snazší testování execution logiky bez render layer
- lepší připravenost na queue, workflow a audit-heavy use-cases
- lepší modularita pro samostatné business packages

### Negative

- přibude více explicitních tříd a seamů
- krátkodobě se zvýší počet adapter vrstev
- některé současné “chytré” convenience paths bude nutné zpřísnit nebo odstranit
- část stávající plugin flexibility bude muset ustoupit explicitním kontraktům

### Risks

- příliš rychlá extrakce aplikační vrstvy může rozbít současnou jednoduchost API
- pokud se nejprve nerozřeže `WithTable` a `Column`, nová resource/page vrstva
  se jen nalepí na stávající monolity
- pokud se ponechá příliš mnoho raw callback escape hatchů, execution model
  zůstane nekonzistentní

## Current Gaps Against This Target

K datu tohoto ADR jsou největší odchylky následující:

### 1. Chybí first-class application owners

Wire má silné primitives (`Form`, `Table`, `Action`), ale nemá ještě stabilní
vrstvu:
- `Resource`
- `Page`
- `RelationManager`
- `Workspace`

To znamená, že business ownership dnes často končí přímo v table/form runtime.

### 2. `WithTable` je příliš široký runtime owner

Současný table runtime stále centralizuje:
- state
- query orchestration
- action execution
- modal flow
- inline edit
- polling
- export integration

To je proti cíli “headless engine + adapter”.

### 3. `Column` base class je příliš široký

Base column absorbovala mnoho specializovaných concerns, které mají žít ve
specializovaných column typech.

### 4. `Core/` je silný engine bez dost silných higher-level ownerů

`Core/Query`, `Core/State`, `Core/Actions`, `Core/Validation` jsou užitečné,
ale jejich síla dnes převyšuje sílu aplikační vrstvy nad nimi. Výsledkem je
silný interní engine a relativně slabý application model.

### 5. Extensibility je příliš hook-oriented

Array hooky + typed hooky poskytují flexibilitu, ale pro ERP/CRM use-cases je
potřeba více explicitních typed contracts a méně generic mutation points.

### 6. Runtime seams jsou stále příliš implicitní

Časté `app()` lookupy a přímé `new` v runtime hot paths snižují modularitu,
testovatelnou izolaci a přenositelnost execution vrstev.

### 7. Design system ownership ještě není plně per-surface

Canonical ownership práce jde správným směrem, ale stále existují lokální
surface maps v action/table/form rendering paths. To je potřeba dokončit podle
per-surface modelu, ne přes jeden univerzální helper.

## Migration Direction

### Phase 1 — Establish the north star

- přijmout tuto architekturu jako cílový model
- všechny nové shared abstrakce posuzovat podle této vrstvené struktury

### Phase 2 — Shrink current monoliths

- rozřezat `WithTable` na headless collaborators + Livewire adapter
- zmenšit `Column` base class
- oddělit render data od execution logic

### Phase 3 — Introduce application owners

- zavést `Resource` / `Page` / `RelationManager` / `Workspace` contracts
- nechat `Form`, `Table`, `Action` fungovat jako primitives pod nimi

### Phase 4 — Tighten execution seams

- posunout save/action/query orchestration do typed execution services
- omezit `app()` a přímé `new` v runtime hot paths
- zúžit generic plugin hooks

### Phase 5 — Add domain module axis

- zavést modulární skeleton pro ERP/CRM domény
- resources/workspaces/workflows nechat registrovat na úrovni modulu

## Non-Goals

- přímá klonace Filament resource API
- přímá klonace Nova package layoutu
- sjednocení všech UI surface do jednoho helperu nebo jednoho rendering engine
- přesun celé současné architektury v jednom release

## Decision Summary

Wire se nebude optimalizovat jako “feature-richer Filament/Nova clone”.

Wire se bude optimalizovat jako:

**modulární ERP/CRM application framework s DX podobným Filamentu/Nově, ale s
výrazně explicitnější execution a ownership architekturou.**
