---
title: V3 — Volitelná administrace a hotové části jako balíčky
date: 2026-09-05
scope: packages/admin (nový — shell), packages/core (plugin seam, modules.except), docs, workbench
status: HOTOVO 2026-09-05 — fáze A, B i C
north_star:
  - architecture/decisions/0028-optional-panel-shell.md
  - architecture/decisions/0029-modules-as-installable-packages.md
  - architecture/decisions/0030-hook-surface.md
depends_on:
  - architecture/decisions/0020-application-owner-layer.md   # „routing opt-in, no shell" — tuhle čáru to překračuje
  - architecture/decisions/0026-registration-seam.md         # Catalog jako jediný seznam
  - architecture/decisions/0027-routing-zones.md             # zóna = name prefix route skupiny
parent: architecture/plans/v2-master-plan.md
---

# V3 — Volitelná administrace a hotové části jako balíčky

Zadání vlastníka 2026-09-05: *„vystavět administraci volitelnou a také možnost
přidávat hotové části jako balíčky"*, upřesněné třemi větami: **„balíček by měl
přidávat, ne přepisovat"**, **„kompletní administrace jako samostatný
balíček?"** a **„systém háčků by měl být rozmanitý, aby se uživatelům dobře
pracovalo napříč systémem"**. Všechny tři jsou nosné: první je invariant (ADR
0029 §3), druhá odpověď „ano" a tvar fáze C (ADR 0028 §1), třetí je to, co
z invariantu dělá splnitelný slib — háčky jsou motor aditivní cesty, takže
fáze B je o nich (ADR 0030). Měření posunulo všechny tři.

## 0. Co měření našlo (2026-09-05, proti stromu, ne proti plánům)

| Půlka zadání | Stav | Co doopravdy chybí |
|---|---|---|
| administrace volitelná | `wire-panels` je samostatný balíček, nic na něj neukazuje; `routes.enabled => false`; core neumí URL (`ResolvesPageUrls` → `UnroutedPageUrls`, `WireCoreServiceProvider.php:454`); zóny hotové | **layout**. V `packages/*` žádný neexistuje. Jediný shell v repu je ručně psaný workbench (`components/layouts/wire.blade.php` + `previews/workspace.blade.php`, ~80 řádků Blade) — a patří do **nového balíčku `wire-admin`**, ne do panels |
| hotové části jako balíčky | `DomainModule` *je* `Plugin`; provider rozprostře resources/dashboardy/skupinu (`bootModules()`, `:510`); přes `Catalog` to rovnou routuje i hledá | **tři hrany**: samoregistrace balíčku není nikde napsaná, pozdní registrace tiše projde, a resource z modulu se nedá přepsat |

Tři měření, která změnila zadání:

1. **Balíček se umí zaregistrovat sám už dnes.** `WireSortableServiceProvider.php:30`
   — `$this->app->resolving(PluginManager::class, …)` s `has()` guardem, v
   `registeredPackage()`. Není to chybějící API, je to nenapsaná dokumentace.
2. **Pozdní registrace je tichá.** `PluginManager::register()` nemá guard proti
   `$this->booted` (`:56`). Registrace v `boot()` místo v `register()`: plugin
   se zapíše, `boot()` se mu nezavolá, `bootModules()` už proběhl — modul je
   „nainstalovaný" a menu prázdné, bez jediného hlášení.
3. **Resource z modulu nejde přepsat — a nemá jít.** `ResourceRegistry::register():60`
   odmítne dvě různé třídy na jednom klíči (a `Catalog` znovu napříč zdroji).
   To odmítnutí *je* ten invariant „přidávat, ne přepisovat", takže zůstává.
   Otázka tím pádem není „jak přepsat", ale **jaká je aditivní cesta** — a ta
   ze dvou třetin existuje (hooky a makra `PluginManager`, ADR 0014); chybí
   jenom možnost modulový resource odmítnout.

A jedna věc, kterou měření **nenašlo** jako chybějící: registr, kontrakt ani
builder. Pod shellem je hotové všechno (`Catalog`, `Workspace::navigation()` se
skupinami/ikonami/pořadím/odznaky, `urlFor()` se zónou, stránky, paleta, toasty).

## 1. Fáze A — distribuce ✅ HOTOVO 2026-09-05

ADR 0029, body 1 a 2. Co skutečně vzniklo:

1. **Guard.** `PluginManager::register()` hodí `registeredAfterBoot()`, když už
   je manager nabootovaný — a kontroluje se **jako první**, před duplicitou:
   guard za přiřazením by vyhodil výjimku a plugin v seznamu nechal. Mutační
   test proběhl (guard vypnutý → dva ze tří nových testů padnou).
2. **Config se taky přestal mlčet.** `WireCoreServiceProvider` dřív neplatné
   položky přeskakoval; teď se přeskočí jen prázdný řetězec (koncová čárka),
   cokoli jiného, co pluginem být nemůže, hodí `notAPlugin()` a rozliší přitom
   „třída neexistuje" od „chybí kontrakt", a hodnota, která není pole, hodí
   `invalidPluginList()` místo PHP chyby uvnitř provideru.
3. **Konzument.** Workbench dělá module package: `BillingModule` se registruje
   přes `resolving()` ve `WorkbenchServiceProvider`, `OperationsModule` zůstal
   v configu. Ověřeno na nabootované aplikaci — `plugins: sortable,billing,operations`,
   `resources: invoices,tasks,documents`, `groups: billing,operations,insights`.
   Závislost operations→billing drží sama: `resolving` callbacky běží dřív než
   `afterResolving`, kde se čte config.
4. **Docs.** `docs/core/modules.md` + CS mirror: sekce „Modul jako balíček"
   (obě cesty, tabulka „kdo kterou používá", podsekce „Balíček přidává,
   nepřepisuje"). `docs/core/plugins.md` + CS: tabulka toho, co config přijme
   a co odmítne, pravidlo o fázi u vzoru z balíčku, a věta o tom, že `boot()`
   registraci uzavírá. Guidelines pro boost přepsané, `boost:sync-docs` proběhl.

Brány: `composer test` 6345 ✅, `composer analyse` ✅, `composer lint` ✅,
`docs:check` / `docs:standard` / `docs:api` ✅.

Co si z toho odnést: **oprava zadání.** ADR 0029 první verze tvrdila, že
samoregistrace balíčku není nikde zdokumentovaná — `docs/core/plugins.md` §
„Register Plugins From A Package" ji popisuje. Nenapsané bylo, že tudy jde
i *modul*, a pravidlo o fázi. ADR je opravená.

A jeden následek guardu, který stál čtyři testy: **registrace do nabootovaného
manageru byla v testech běžná** (`DomainModuleTest`, `WireToolsTest`,
`TableQueryServiceTest`). Všechny čtyři teď bindují čerstvý `PluginManager`, což
je zároveň poctivější — testují fázi, ve které se registruje doopravdy.

## 2. Fáze B — háčky ✅ HOTOVO 2026-09-05

ADR 0030. **Jedno měření změnilo zadání a je to to hlavní, co si z fáze odnést:
`table.configuring` sloupec do tabulky přidat neumí.** Běží uvnitř
`TableQueryService` nad poli, která se chystá spotřebovat planner, takže sloupec
přidaný tam se hledá a řadí — a nikdy nevykreslí. Aditivní slib z ADR 0029 tedy
pro tabulky neměl mechanismus *žádný*, ne částečný. Našel to první test, který
místo dotazu vykreslil stránku; ani PHPStan, ani unit testy to nemohly vidět.

Co vzniklo:

1. **`Hook` enum** ve `Foundation/Enums/` — přijímá se všude, kde dřív stál
   řetězec (`hook()`, `runHook()`, `runTypedHook()`, `hasHook()`), a řetězec
   platí dál.
2. **`HookTarget`** + kontrakty `HasHookTarget` (payload ví, odkud je)
   a `IdentifiesHookTarget` (hostitel řekne svůj registrovaný klíč). Nese ho
   všech devět payloadů jako `?HookTarget $target = null` na konci konstruktoru,
   takže je to přídavek, ne přeskládání veřejného tvaru.
3. **Zúžení `for:`** na `hook()`, vyhodnocené na jednom místě
   (`PluginManager::outOfScope()`) pro oba dispatchery. Dispatch bez cíle zúžený
   callback **nepustí** — pustit callback jednoho modulu na cizí komponentu je
   ta horší ze dvou chyb.
4. **`Hook::TableComposing`** — nový, typed-only, dispatchovaný jednou
   ve `WithTable::getTable()` nad složenou instancí. To je ten chybějící
   mechanismus.
5. **`Hook::FormConfiguring`** — totéž pro formulář, v `Form::getConfig()`.
6. **Resource stránky `wire-panels` implementují `IdentifiesHookTarget`** přes
   `BelongsToResource`, takže `for: 'invoices'` míří na klíč, pod kterým se
   modul zaregistroval. Bez toho by zúžení uměl jen ten, kdo zná třídu.

Konzument, který to drží poctivé: `packages/panels/tests/Unit/ScopedHookTest.php`
**vykreslí** dvě resource stránky a ověří, že hook zúžený klíčem přidá sloupec
jen jedné z nich. Právě on odhalil bod výše — a taky to, že chybějící `use`
v `WithTable` PHPStan neohlásil.

Nedodáno vědomě: `infolist.configuring`, `navigation.building`, `page.mounting`,
`search.querying`, `export.configuring`, `widget.configuring`. Pojmenované
v ADR 0030 §6, čekají na konzumenta — a symetrie konzument není.

Zbývá z původního plánu fáze B: **`modules.except`** (aplikační skip list
registrovaných klíčů). Pořád platí, že se přidá s prvním modulem, který ho
potřebuje.

Brány po fázi A i B: `composer test` 6378 ✅, `analyse` ✅, `lint` ✅,
`ModuleLayers` ✅, docs gates ✅, `coverage:verify` ✅ (forms 95 → 96, floor
zvednutý), `verify:drivers` **82/82** ✅ — poslední jmenovaně proto, že
`WithTable::getTable()` je horká cesta každé tabulky. Souhrn po fázi C je v §4.

Zamítnuto a nemá se to vracet bez ADR: `replace` mapa třída→třída (dělá
z „kdo obsluhuje `users`" otázku pořadí bootu) a container binding na resource
(statické `key()`/`pages()` ho na polovině ploch neuvidí).

## 3. Fáze C — shell ✅ HOTOVO 2026-09-05

ADR 0028. Vznikl **nový balíček `packages/admin` (`nyoncode/wire-admin`)** nad
`wire-panels`:

```text
wire-admin -> wire-panels -> wire-table -> wire-forms -> wire-core
```

- `<x-wire-admin::layout>` — rám se sloty `head` / `brand` / `topbar` / `user`,
  paleta a toasty uvnitř, `@wireStackScripts` v hlavě (pozdě doručený controller
  je to jediné, co ADR 0024 zakazuje). Žádný objekt `Panel`.
- `<x-wire-admin::sidebar>` — menu nad `Workspace`, aktivní položka z názvu routy,
  odkazy z `ResolvesPageUrls`, `:linked-only` pro aplikaci, která nechce
  nezaroutované řádky, mobilní přepínač.
- `Zone` dostal sourozence `currentKey()` / `keyOf()` nad jedním privátním
  matcherem — menu potřebuje klíč routy a ten anchored pattern patří sem, ne do
  shellu (`livewire.update` obsahuje `wire.`).
- Workbench je konzument: `components/layouts/wire.blade.php` je teď to, co píše
  **aplikace** — jmenuje layout a naplní sloty; všechno pod tím je balíčkův.

**Dva režimy URL se sloučily.** Shell je layout routovaných stránek, takže
`wire.{key}.index` *je* shell a sidebar bere URL z `ResolvesPageUrls`. Tím padá
i podmínka, kterou §4 progressu držela otevřenou. `/previews/workspace` zůstává
schválně: je to ta druhá, pořád první-třídní cesta — aplikace, která si menu
kreslí sama.

**Co našel prohlížeč a Pest ne** (driver `admin-shell`, 14 checků):

1. **Menu se na telefonu neschovalo.** Stav řídil `resize` handler, zatímco
   `lg:` třídy se matchují na media query — dva zdroje pro jednu hranici. Teď
   poslouchá přímo ten dotaz, což pokrývá i zoom a rotaci.
2. **`verify-resource-routes` ověřoval `body.min-h-screen`** — třídu ručně psané
   workbench layoutu. Ta aserce ztratila smysl ve chvíli, kdy se rám přestěhoval
   do balíčku; nahrazena třemi silnějšími (sidebar, brand ze slotu, aktivní
   položka), ne smazána.

Docs: `docs/core/admin-shell.md` + CS mirror, README balíčku, `AI_BLUEPRINT.md`
(sekce `wire-panels` i `wire-admin` — panels tam chyběl už dřív), `CLAUDE.md`
(graf, `test:admin`, rozcestník).

## 4. Brány (podle `AI_CHANGE_PROTOCOL.md`)

**Stav po fázi C (2026-09-05):** `composer test` **6392** ✅, `analyse` ✅,
`lint` ✅, `ModuleLayers` ✅, `docs:check` / `docs:standard` / `docs:api` ✅,
`boost:sync-docs` + `boost:check-docs` ✅, `coverage:verify` ✅ —
`admin` **100 %**, floor nastavený, `forms` 96 %. `verify:drivers` **83/83** ✅
(včetně nového `admin-shell`, 14/14).

- `composer test:core` / `test:panels` / (fáze C) `test:admin` úzce, pak
  `composer test`.
- `vendor/bin/pest --filter ModuleLayers` — fáze A sahá do `Core/Plugin` (L1).
- `COMPOSER_PROCESS_TIMEOUT=1800 composer coverage:verify` a
  `php scripts/verify-coverage.php build/clover.xml --diff=origin/1.x`.
- Veřejné API → obě docs stránky v jednom commitu + `composer boost:sync-docs` +
  `npm run docs:standard` + `npm run docs:api`.
- Fáze C navíc: `npm run verify:drivers` (nic jiného u toho neběží, výstup do
  souboru) a screenshoty/preview refresh, pokud přibudou views.

## 5. Pasti, které tenhle repozitář už zaplatil

- **Publikované views v testbenchi stínují balíčkové** — editace Blade bez
  efektu na testy i preview.
- **Červený driver je napřed podezření na prostředí**: bisekce driver →
  `git stash push -- <cesta>` → restart preview serveru.
- **Jména fixtur v testech jsou globální**; balíčkové sady projdou, `composer test`
  spadne na `Cannot redeclare class`.
- **Chybějící `use` PHPStan neohlásí**, když se nepoužitá třída jen `::class`uje:
  `app()->bound(PluginManager::class)` bez importu se v `WithTable` vyhodnotilo
  na `NyonCode\WireTable\Concerns\PluginManager`, tiše vrátilo `false` a hook
  se nikdy nespustil. Analýza i unit testy prošly; našel to až render.
- **`focus()` na skrytém elementu je tichý no-op** — po otevření dialogu počkat
  na vykreslení.
- **Livewire 4 čte `livewire.component_layout`**, ne `livewire.layout`.
- **`RouteRegistrar::middleware()` nahrazuje, nesčítá.**

## 6. Co se vědomě nedělá

- **`Panel` objekt s fluent API** (branding, barvy, per-panel middleware). To je
  riziko, které ADR 0020 pojmenovalo; tripwire je v ADR 0028 §Consequences.
- **Discovery modulů skenováním.** Balíček se registruje vlastním providerem,
  který composer objeví; skenování `App\Modules\` by byla druhá odpověď na
  otázku, kterou provider odpovídá (§4 progressu, pořád bez konzumenta).
- **Vlastní URL schéma shellu.** Zóna je `name()` route skupiny (ADR 0027).
- **Shell uvnitř `wire-panels`.** Tři ADR držely, že owner vrstva shell nemá;
  balíček navíc je jediný opt-in, který aplikace nemůže získat omylem.
- **`replace` mapa** (viz fáze B) — přepisování je přesně to, co zadání zakázalo.
- **Háčky „pro úplnost".** Symetrie není důvod; konzument ano (ADR 0030 §6).
- **Nový array-payload háček.** Dispatch dvakrát je dluh z 2.x, ne vzor.
