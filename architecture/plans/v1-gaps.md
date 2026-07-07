---
title: V1 gaps assessment
date: 2026-06-04
scope: repo-wide
status: assessment
related:
  - architecture/decisions/0017-erp-crm-application-architecture.md
  - architecture/plans/erp-crm-features.md
  - architecture/plans/v2-deferred-items.md
---

# V1 gaps assessment

> **⚠️ Konsolidace (2026-07-06):** Řešení těchto mezer drží
> [`v2-master-plan.md`](v2-master-plan.md). Mapování: #1 owner vrstva → **V2.3**;
> #2 tenancy, #3 workflow, #5 queue → **V2.4**; #6 saved views, #8 large-table UX
> → **V2.5**; #4 import **HOTOVO**; #7 relation management → **V2.3** (owner vrstva
> povýší `RelationManager`); #9 docs/Tailwind drift → pre-V2 hygiena. Tento
> dokument zůstává jako **odůvodnění** (proč mezery bolí), ne jako plán řešení.

Tento dokument zachycuje **produktové a provozní mezery V1**.

Neřeší primárně strukturální refaktory typu `WithTable` / `Column` / `Core/`
recomposition — ty jsou vědomě odložené do V2. Cílem je oddělit:

- co je akceptovaný limit V1
- co začne bolet už při prvním širším nasazení

## Výchozí závěr

`V1` je použitelná jako:
- admin/backoffice toolkit
- základ pro první interní CRM/ERP moduly
- pilotní business aplikace

`V1` ještě není kompletní jako:
- ERP/CRM application framework
- multi-tenant product shell
- workflow-heavy business platform

## Gaps

### 1. Chybí aplikační owner vrstva (`Resource` / `Page` / `Workspace`)

**Dopad:** vysoký pro framework positioning, střední pro první app.

Repo dnes exponuje hlavně primitives:
- `core`
- `forms`
- `table`
- `sortable`

Viz [README.md](../../README.md) a package overview. V dokumentaci i ve zdrojích
není first-class vrstva pro:
- `Resource`
- `Page`
- `RelationManager`
- `Workspace`

To znamená, že ownership business use-casu končí převážně v Livewire komponentě,
`Table`, `Form` a lokálních akcích, ne v explicitním aplikačním modelu.

**V1 dopad:**
- pro jednu aplikaci akceptovatelné
- pro framework reuse a více týmů slabé

### 2. Multi-tenancy je manuální nebo plugin-based, ne first-class

**Dopad:** vysoký pro SaaS / ERP nasazení.

Aktuální doporučené cesty jsou:
- ruční `where('tenant_id', ...)` v query, viz [docs/table/overview.md](../../docs/table/overview.md)
- custom tenant plugin / macro, viz [docs/core/plugins.md](../../docs/core/plugins.md)
- per-record authorize closure, viz [docs/authorization.md](../../docs/authorization.md)

To je flexibilní, ale ne dostatečně bezpečné jako výchozí model pro business
software, kde tenant scope patří mezi systémové invarianty.

**V1 dopad:**
- interní single-tenant app: akceptovatelné
- multi-tenant produkt: slabé defaulty, vysoké riziko lidské chyby

### 3. Chybí first-class workflow / process layer

**Dopad:** vysoký pro skutečné ERP/CRM use-cases.

Akce, modaly a tabulky existují, ale vestavěný workflow model ne. V docs je
`workflow` zmiňován jen jako příklad custom action type přes plugin registry,
viz [docs/core/plugins.md](../../docs/core/plugins.md).

Není vestavěné:
- transition model
- state machine / process policy
- approval flow owner
- task inbox / workflow workspace

**V1 dopad:**
- CRUD a jednoduché akce: v pořádku
- procesně těžké use-casy: budou ad-hoc v aplikaci

### 4. Chybí import pipeline

**Dopad:** vysoký pro onboarding dat.

Repo má exporty, ale nenašel jsem built-in:
- `ImportAction`
- `Importer`
- `ImportJob`

V ERP/CRM je import běžný day-1 požadavek:
- kontakty
- produkty
- ceníky
- historická data

**V1 dopad:**
- bez importu je produkt použitelný, ale nástup dat bude řešen mimo framework
- pro zákaznické nasazení to bude brzo chybět

### 5. Chybí first-class queued/background operations

**Dopad:** střední až vysoký podle objemu dat.

V plánu exportů je explicitně zmíněný background export přes queue job,
viz [erp-crm-features.md](erp-crm-features.md), ale v `packages/**` jsem
nenašel built-in queueable action/export job abstractions.

To znamená, že:
- dlouhé bulk akce
- velké exporty
- asynchronní business operace

nejsou ještě first-class execution path.

**V1 dopad:**
- malé a střední datasety: akceptovatelné
- větší provoz: chybí bezpečná async cesta

### 6. Chybí saved filters / saved views / table presets

**Dopad:** střední, ale velmi praktický.

Table umí URL state persistence pro bookmarkable linky, viz
[docs/table/advanced.md](../../docs/table/advanced.md), ale “saved filters”
jsou stále vedené ve future ideas, viz [future-ideas.md](../future-ideas.md).

To znamená, že chybí:
- uložené pohledy tabulky
- sdílené filter presets
- per-user table layouts / named views

**V1 dopad:**
- technicky použitelné
- UX pro power users slabší než u zralých admin platforem

### 7. Child-record management škáluje jen do určité míry

**Dopad:** střední.

V1 má dva dobré nástroje:
- `Repeater` pro menší inline child collections, viz [docs/forms/fields/repeater.md](../../docs/forms/fields/repeater.md)
- sub-rows pro drill-down v tabulce, viz [docs/table/sub-rows.md](../../docs/table/sub-rows.md)

Sám `Repeater` ale říká, že pro child records s vlastním filtrováním,
paginací nebo těžkými workflows má existovat vlastní table nebo screen.

Chybí tedy first-class relation management layer.

**V1 dopad:**
- jednoduché parent-child flows: dobré
- komplexní relation screens: budou ručně skládané

### 8. Large-table UX ještě není dotažené

**Dopad:** střední, vysoký pro datově těžké appky.

V plánovacích dokumentech jsou jako budoucí směry zmíněné:
- grouping
- virtual scrolling
- advanced filters
- column presets

Viz [core/unified-engine.md](../core/unified-engine.md) a [ADR 0013](../decisions/0013-unified-data-ui-engine.md).

To znamená, že současná tabulka je silná funkčně, ale pro opravdu velké
datové plochy jí ještě chybí některé ergonomické vrstvy.

**V1 dopad:**
- většina běžných tabulek: v pořádku
- datově těžké ERP seznamy: bude to limit

### 9. Docs / compatibility messaging drift

**Dopad:** nízký až střední, ale zbytečně matoucí.

Root README stále uvádí `Tailwind CSS 3.x`, viz [README.md](../../README.md),
zatímco package README pro `forms` a `table` už obsahují Tailwind 4 install
sekce, viz:
- [packages/forms/README.md](../../packages/forms/README.md)
- [packages/table/README.md](../../packages/table/README.md)

Historická ADR k tomu říká “Tailwind 3 only in v0.1.0”, viz
[0005-tailwind-4-support.md](../decisions/0005-tailwind-4-support.md).

To je především docs/product messaging gap, ne runtime blocker.

## Priority For V1 Reality

Pokud mají strukturální změny čekat na V2, největší praktické V1 mezery jsou:

1. import pipeline
2. tenant-safe defaults
3. queued/background operations
4. saved table views / filter presets

Tyto čtyři oblasti mají nejvyšší poměr dopad / užitnost pro reálné nasazení,
aniž by nutně vyžadovaly plnou V2 architektonickou migraci.

## Explicitly Not Counted As V1 Gaps Here

Tyto oblasti jsou záměrně vyřazené z tohoto seznamu, protože spadají do
vědomě odložené strukturální práce:

- rozřezání `WithTable`
- zmenšení `Column` base class
- resource/page/workspace zavedení jako plná framework vrstva
- úplná execution seam cleanup

To jsou V2 změny. V tomto dokumentu jsou zmíněny jen tam, kde už mají přímý
produktový dopad ve V1.
