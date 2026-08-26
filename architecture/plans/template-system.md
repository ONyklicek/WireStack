---
title: Template system — theme jako kanonický vlastník vzhledu
date: 2026-08-23
revised: 2026-08-24 (hloubkový audit proti kódu — viz §15)
scope: packages/core, packages/forms, packages/table, packages/sortable (boost je mimo — nemá UI views)
status: plan (nezahájeno)
related:
  - architecture/plans/canonical-ownership-consolidation.md
  - architecture/plans/render-engine-htmlable-first.md
  - architecture/decisions/0005-tailwind-4-support.md
  - architecture/decisions/0022-exception-strategy.md
  - docs/theming.md, docs/cs/theming.md
---

# Template system

Cíl: uživatel si má umět předělat **vzhled celého konceptu** — ne jednu buňku,
ale řádek, hlavičku, pole, tlačítko, badge, modal, prázdný stav — a přesto
zůstat updatovatelný. Dnes to nejde: jediná systémová páka na vzhled je
`vendor:publish --tag=…::views`, což je fork Blade souboru. Fork zamrzne na
verzi, ve které vznikl.

## 1. Co dnes brání theme vrstvě

Měřeno na `2.0.0` (23. 8. 2026), ověřeno auditem 24. 8. 2026:

| Vrstva | Stav | Číslo |
| --- | --- | --- |
| Třídy v Blade natvrdo | vlastníkem je šablona, ne PHP | **137 z 205** blade souborů (core 51/81, forms 31/47, table 55/74, sortable 0/3) |
| Třídy vlastněné v PHP | jediný skutečný vlastník je `HasColor` | 544 řádků class-stringů v `packages/core/src/Foundation/Concerns/HasColor.php` |
| Owner-facing API na vzhled | `->color()`, `->size()`, `->extraAttributes()`, `->view()` | pokrývá jen instanci, ne systém |
| Systémová páka | `vendor:publish` views | fork, nekompatibilní s updatem |
| Ochrana proti vizuální regresi | žádná | 0 snapshot testů, 0 screenshotů; utility třídu asserují **2** testové soubory ze 496 |

Z toho plyne to podstatné: **theme, která by přepisovala jen PHP resolvery, by
ovládala cca 10 % viditelného povrchu.** Zbytek je literál v šabloně. Template
system proto není nová třída — je to přesun vlastnictví tříd z Blade do PHP a
migrace 137 šablon na jeho čtení. Poslední řádek tabulky říká, proč to není
mechanická práce: dnes v repu neexistuje nic, co by dokázalo, že se při té
migraci vzhled nezměnil. To řeší fáze 0.

## 2. Koncept

Jedna aktivní theme = PHP vlastník **tokenů** (sémantický klíč → literální
řetězec Tailwind tříd), složený z **per-surface providerů**. Package šablony
žádnou utility třídu neobsahují. Theme uživatele dědí z výchozí a přepisuje jen
to, co chce.

```
DefaultTheme (per-surface providery)  ──┐
AppTheme (jen rozdíly)                ──┴─> ThemeManager ──> ThemeSheet (plochá mapa, O(1), memo na request)
                                                                 │
                          owner (Column/Action/Field) ───────────┤ ── čte token, hoistuje do RenderPlan/Skeletonu
                          chrome bez ownera (layout partial) ────┘ ── @wireClass('…')
```

Čtyři vrstvy, s klesající bezpečností updatu — a to je záměr, ne kompromis:

| Vrstva | Co mění | Bezpečnost updatu |
| --- | --- | --- |
| 1. Tokeny (theme třída) | vzhled celého systému i jednotlivých ploch | úplná — markup zůstává náš |
| 2. Render hooky | vložení vlastního markupu do pojmenovaných kotev | úplná — kotvy jsou veřejné API |
| 3. `->view()` / app-level view | markup jedné komponenty | vysoká — data přicházejí z kontraktu |
| 4. `vendor:publish` views | markup čehokoliv | fork, ale **theme v něm dál funguje** (§7) |

### Co v rozsahu není

- **Runtime přepínání theme** per tabulku/panel/tenant. Kontrakt to nevylučuje
  (theme se rozhoduje v `ThemeManager`, ne `config()` voláním z Blade), ale
  pozor: doplnit se to nedá zadarmo — viz tvrdé omezení v §6.4.
- **Distribuovatelné theme balíčky.** Totéž — kontrakt ano, implementace ne.
- **`packages/boost`.** Nemá UI views, jen `resources/boost/guidelines/*.blade.php`
  (dokumentační korpus). Do migrace nevstupuje.

## 3. Kontrakty a soubory

Vše v `packages/core/src/Foundation/Theme/` — nejnižší vrstva, která to může
vlastnit (invariant z `CLAUDE.md`). Doménová složka místo globálního `Managers/`
je záměr: kopíruje `Foundation/Icons/IconManager.php` a `Core/Plugin/PluginManager.php`,
tedy skutečnou praxi repa (layout v `AI_CODING_STANDARD.md` §Managers je
pojmenování, ne umístění).

```
Foundation/Theme/
├── Contracts/Theme.php             # surfaces(): list<ThemeSurface>; extends(): ?class-string<Theme>; name(): string
├── Contracts/ThemeSurface.php      # tokens(): array<string,string>; prefix(): string
├── DefaultTheme.php                # složí per-surface providery, sám žádné třídy nedrží
├── Surfaces/{Button,Badge,Icon,Modal,Dropdown,Callout,EmptyState,Toast,Table,Forms}Surface.php
├── ThemeManager.php                # singleton; sloučí řetěz dědičnosti, vydá ThemeSheet
├── ThemeSheet.php                  # readonly: plochá mapa + get() s fallbackem (interní tvar, ne veřejné API)
├── Concerns/InteractsWithTheme.php # $this->themeClass('badge.base') pro ownery
├── HookRegistry.php                # §5
├── Exceptions/UnknownThemeToken.php
└── Console/{MakeTheme,ThemeTokens,ThemeSafelist}Command.php
```

**Proč per-surface providery a ne jedna mapa.** `canonical-ownership-consolidation.md`
§Cílový model výslovně zakazuje "jeden bag class stringů pro všechno" a
předepisuje per-surface resolvery; `CLAUDE.md` totéž ("Do not collapse distinct
UI surfaces into one universal helper"). `ThemeSheet` je proto **implementační
detail** — plochá mapa existuje kvůli O(1) čtení, ne jako veřejný tvar. Vlastník
sémantiky zůstává per-surface a odpovídá canonical surface typům z toho plánu
(`badge`, `text`, `modal-icon`, `button-solid`, `button-outlined`, `button-link`,
`icon-button`, `dropdown-item`, `toggle-track`, `banner`, `rating-active`).

```php
// config/wire-core.php
'theme' => App\Wire\Themes\AppTheme::class,   // null = DefaultTheme
```

```php
final class AppTheme extends DefaultTheme
{
    /** Jen rozdíly; ThemeManager je slije přes rodičovský řetěz. */
    public function overrides(): array
    {
        return [
            'table.row.base'       => 'border-b border-slate-200 dark:border-slate-800',
            'forms.input.base'     => 'rounded-xl border-slate-300 dark:bg-slate-900',
            'button.solid.primary' => 'bg-brand-600 text-white hover:bg-brand-500',
        ];
    }
}
```

### Kdo token čte — pravidlo, ne preference

1. **Kde existuje owner** (`Column`, `Action`, `HeaderAction`, `BulkAction`,
   pole, komponenta), token čte **owner v PHP** a předává ho do render-dat.
   Tak to chce bod 3 cílového modelu v consolidation plánu *a zároveň* výkonový
   model: `ColumnRenderPlan.php:170-188` už dnes kompiluje `body-cell` do
   `Skeleton`u jednou per column — token se tam přečte jednou, ne per řádek.
2. **Jen chrome bez přirozeného ownera** (layoutové partialy, toolbar, prázdný
   stav) čte přes direktivu `@wireClass('table.toolbar.base', $extra)`.

Direktiva se registruje vedle `wireStackScripts` (`WireCoreServiceProvider.php:252`).
**Žádný globální helper (`wire_class()`) ani facade** — repo dnes nemá ani jeden
helper-function soubor, ani jednu facade, a theme není důvod zavádět dvě nové
konvence. PHP kontext bere `app(ThemeManager::class)` nebo trait.

### Fallback a varianty

Klíč je `<plocha>.<část>[.<varianta|stav>]`. `get('table.cell.compact')` spadne
na `table.cell.base`. Neznámý token: v `app.debug` vyhodí `UnknownThemeToken`
(ADR 0022), v produkci vrátí `''` a jednou zaloguje přes existující
`Core/Support/Deprecation.php` (warn-once vlastník; nepsat vlastní).

### Precedence tříd — otevřený defekt, který theme zdědí

Plán **netvrdí**, že `extraAttributes()` přebije theme, protože dnes to není
pravda. `Column::extraAttributes()` bere raw string (`Column.php:1313`) a šablona
ho lepí za `class="…"` (`body-cell.blade.php:25`) — vznikne druhý `class`
atribut a prohlížeč použije první, tedy naši třídu. Uživatelova třída se tiše
zahodí. Navíc je to nekonzistentní s core `HasExtraAttributes` (pole,
`HasExtraAttributes.php:28`).

Rozhodnutí: **merge tříd je součást fáze 2** (jinak theme system slíbí
přizpůsobení, které nefunguje), řeší se jednou v owner API — `class` z
extraAttributes se slučuje do jednoho atributu za token. Sjednocení signatury
`Column::extraAttributes(string)` na pole je breaking change → samostatná
položka do 2.1, ne sem.

## 4. Slovník tokenů

Pojmenování je **veřejné API**; po vydání je přejmenování breaking change.
Slovník se zmrazí ve fázi 1 a vznikne jako `architecture/plans/template-system-tokens.md`
(bez souboru není co mrazit). `ThemeSheet` drží alias mapu `starý → nový` pro
budoucí přesuny, s warn-once přes `Deprecation`.

Pravidla:

1. Sdílená plocha bez prefixu balíčku: `button.*`, `badge.*`, `icon.*`,
   `modal.*`, `dropdown.*`, `callout.*`, `empty-state.*`, `toast.*`.
2. Plocha specifická pro balíček s prefixem: `table.row.*`, `table.cell.*`,
   `table.header.*`, `table.card.*`, `table.pagination.*`, `forms.field.*`,
   `forms.input.*`, `forms.label.*`, `forms.error.*`.
3. Barvy jsou tokeny taky, po povrchách: `color.button-solid.primary`,
   `color.badge.warning`, `color.toggle-track.success`. Názvy povrchů se berou
   z canonical surface listu, ne vymýšlejí znovu.
4. **Hodnota tokenu je literál.** Žádná interpolace — Tailwind JIT musí řetězec
   vidět a mapa je zároveň allow-list proti injekci tříd (pravidlo, které dnes
   hlídá `HasColor`, bod 4 cílového modelu).
5. Dark mode je součástí hodnoty tokenu, ne druhý token.
6. Kompatibilita s nejnižší podporovanou verzí Tailwindu podle ADR 0005.

### `HasColor` po migraci

`HasColor` zůstává owner-facing API (`getBadgeColorClasses()` atd.), tělo přestane
být `match` mapou a začne číst `color.*` tokeny. Nula breaking changes, jeden
vlastník. **Pozor na past:** `HasColor::$colorClassCache` je `protected static`
a klíčovaný jen barvou (`HasColor.php:37`) — po delegaci by první theme zamrzla
na celý životní cyklus procesu (Octane worker). Cache se **ruší** (lookup do
ploché mapy je už O(1) a levnější než dnešní `match`), ne opravuje.

Totéž platí pro `HasSize::getBadgeSizeClasses` a `Modals/Support/ModalStyle`.
Tím se zároveň uzavírá otevřená položka z
[`canonical-ownership-consolidation.md`](canonical-ownership-consolidation.md) —
viz §14, kde je vztah obou plánů rozhodnutý.

## 5. Render hooky (vrstva 2)

Registr pojmenovaných kotev; zápis v service provideru aplikace přes kontejner
(`app(HookRegistry::class)->add(...)`), ne facade.

Startovní sada kotev (každá je API): `table.toolbar.start|end`,
`table.header.after`, `table.row.before|after`, `table.cell.end`,
`table.empty.after`, `forms.field.before|after`, `forms.actions.start|end`,
`modal.header.after`, `modal.footer.start`.

Kotva bez registrace musí stát **nula** — prázdné pole, žádné `view()` volání,
žádná alokace v řádkovém loopu. Kotvy uvnitř řádku (`table.row.*`, `table.cell.end`)
se musí rozhodnout **před** kompilací skeletonu, jinak rozbijí "static once,
dynamic per row": registrovaná kotva mění *tvar* skeletonu, tedy patří do jeho
cache klíče, ne do `fill()`.

## 6. Výkonový kontrakt

Řádkový loop je nejcitlivější místo v repu (viz
[`render-engine-htmlable-first.md`](render-engine-htmlable-first.md), princip
„static once, dynamic per row", a `Foundation/View/Skeleton.php` jako jeho
vlastník). Theme ho nesmí zhoršit:

1. `ThemeSheet` je plochá mapa složená jednou za request; čtení je
   `$sheet[$token] ?? fallback` — bez `match`, bez reflexe, bez `view()->exists()`.
2. V horkých cestách token čte owner a hoistuje ho do `*RenderPlan` / skeletonu
   (`ColumnRenderPlan`, `RowRenderPlan`, `$columnMeta` v `tables/index.blade.php`) —
   stejný tvar, jaký tam už existuje pro metadata.
3. **Pojistka: render counter neexistuje.** Plán §1 render-engine dokumentu ho
   předepisuje, ale v repu není (ověřeno grepem). Tento plán se na něj proto
   nesmí odvolávat jako na hotovou věc — buď se postaví jako součást fáze 5
   (a je to rozpočtově nezanedbatelná položka), nebo se pojistka nahradí
   golden-markup diffem z fáze 0, který regresi typu "token se čte per řádek"
   nechytí přímo, ale chytí každou změnu výstupu. **Doporučení: postavit counter
   ve fázi 5**, protože jinak nemá tento plán žádnou obranu proti návratu
   `N×View`.
4. **Cache klíče = strop pro runtime přepínání theme.** Dnes existují tři memo
   vrstvy, které theme identitu v klíči nemají:
   `HasViewRenderCache::$viewRenderCache` (statická, request-scoped, signature
   bez theme), per-instance skeletony (`Action::$buttonSkeletons`,
   `Table::$mobileCardSkeletons`, …) a `HasColor::$colorClassCache`. Při jedné
   theme na aplikaci je to v pořádku. **Jakmile by se theme přepínala za běhu
   (per tenant/panel), musí theme identita do klíče všech tří** — jinak druhý
   tenant dostane markup prvního. Zapsáno sem, aby to při rozšiřování nikdo
   neobjevoval znovu.
5. Octane: memo `ThemeSheet` se flushne na `RequestTerminated` vedle už
   existujících flushů (`WireCoreServiceProvider.php:276-283`).

## 7. Publish views a vlastní theme

Požadavek: publish nesmí rozbít vlastní theme. Po migraci to vychází samo —
publikovaná šablona dostává třídy z tokenů (přes render-data ownera nebo
`@wireClass`), ne z literálů, takže **fork drží markup, ale vzhled dál řídí
theme**. Oproti dnešku, kdy fork zmrazí i vzhled, je to zásadní posun.

Úplný výčet override cest po migraci (dnes docs zmiňují jen dvě):

1. `->view('moje.sablona')` na komponentě.
2. **App-level view bez publishe** — `HasView::resolveView()` (`HasView.php:50-64`)
   padá z `wire-table::tables.columns.text` na neprefixované `tables.columns.text`
   v aplikaci. Nezdokumentované; stojí `view()->exists()` per render, což je i
   výkonová položka render-engine plánu.
3. `vendor:publish --tag=…::views` — fork.

K tomu dvě opatření:

- **Manifest a drift.** `wire:views:status` porovná checksum publikovaných šablon
  proti verzi v balíčku a vypíše rozešlé (`--diff` ukáže rozdíl). Manifest se
  generuje při buildu balíčku. Pro uživatele, kteří publikovali **před** migrací,
  je to jediná cesta, jak zjistit, že jejich fork drží staré literály a theme
  ho míjí — do upgrade notes patří výslovně.
- **Označení strukturálních šablon.** `tables/index.blade.php`,
  `partials/body-row-open.blade.php` a další nosiče morph markerů dostanou v
  manifestu příznak „fork na vlastní riziko"; smazaný `@if` v řádkovém loopu
  chytí až browser driver, ne Pest.

Publish tagy se neruší ani nezužují — jen se z nich stává poslední možnost.

## 8. Tailwind scanning

Riziko je užší, než vypadá, ale není nulové:

- **Package PHP je už dnes v globech.** `docs/getting-started.md:79-89` předepisuje
  `./vendor/nyoncode/wire-*/src/**/*.php` pro všechny čtyři balíčky a workbench
  `resources/css/app.css` má `@source` na `packages/*/src`. Třídy přesunuté z
  views do `Foundation/Theme/Surfaces/*.php` tedy u uživatele podle docs
  **scanovány jsou**. Do upgrade notes patří jen: kdo si glob zkrátil na
  `resources/views/**`, přijde po upgradu o polovinu tříd.
- **Aplikační theme scanovaná není.** `app/Wire/Themes/AppTheme.php` default
  Laravel/Tailwind preset nescanuje (TW3 preset míří na `resources/views` a
  `app/View/Components`). `wire:theme:make` proto musí ve výstupu vypsat řádek
  do `content` / `@source`, který si má uživatel zkopírovat.
- `wire:theme:safelist` vygeneruje blade se všemi hodnotami tokenů — záchranná
  brzda pro netypické build pipeline.

## 9. Fáze

Každá fáze je samostatně mergeable a končí zeleným gate. Pořadí je dané rizikem:
infrastruktura, důkaz o neměnnosti výstupu, pak nejméně horké šablony, table naposled.

| Fáze | Obsah | Rozsah | Gate |
| --- | --- | --- | --- |
| 0 | **Golden-markup harness** (render fixní sady povrchů → uložený HTML → byte-identita) + kontrakty, `ThemeManager`, `ThemeSheet`, direktiva, výjimka, config klíč, `wire:theme:make` | ~10 nových souborů, 0 migrovaných šablon | `test:core`, `coverage:verify` |
| 1 | Slovník tokenů: inventura 137 šablon → `architecture/plans/template-system-tokens.md`, zmrazení pojmenování | dokument + kostry surface providerů | review |
| 2 | `HasColor`/`HasSize`/`ModalStyle` → `color.*` tokeny (**včetně zrušení statické cache**) + merge `class` z extraAttributes + core primitives | core, 51 šablon | `test:core`, golden markup, `verify:drivers`, coverage |
| 3 | forms: field wrapper, inputy, layouty | forms, 31 šablon | `test:forms`, golden markup, `verify:drivers` |
| 4 | table mimo horkou cestu: toolbar, filtry, paginace, summary, modaly, mobile card | table, ~40 šablon | `test:table`, golden markup, `verify:drivers` |
| 5 | table horká cesta: `index.blade.php`, `body-cell`, `body-row-open`, sub-rows — hoist do RenderPlan **+ render counter** (§6.3) | table, ~15 šablon | `test:table`, Integration, golden markup, `verify:drivers`, render counter |
| 6 | sortable (drag handle), render hooky, `wire:views:status` + manifest | sortable 3 šablony + hooky | `test:sortable`, Integration |
| 7 | Gate `verify-theme-tokens.php`, docs EN **i CS**, upgrade notes, ADR, **`sync-boost-docs.php --check`** | docs + CI | `docs:check`, `docs:standard`, `docs:api`, `docs:verify-ui`, boost sync |

## 10. Vynucení (bez něj se to rozpadne zpět)

`scripts/verify-theme-tokens.php` v CI: **utility třída v package Blade =
selhání buildu.** Bez toho příští PR vrátí literál do šablony a theme začne tiše
lhát. Skript vzniká ve fázi 2 s `--only=<package>`, gate se zapne ve fázi 7.

Allow-list: `Surfaces/*.php`, `widgets/bar-chart/safelist.blade.php`, generovaný
safelist, `docs-site/`.

## 11. Testy

- **Golden markup (fáze 0, nosný gate).** Fixní sada povrchů (tabulka se všemi
  typy sloupců, formulář se všemi poli, modal, prázdný stav, mobile card) se
  vyrenderuje a uloží. Každá migrační fáze musí vydat **byte-identický** výstup.
  Bez tohoto gate je migrace 137 šablon nekontrolovatelná: dnes utility třídu
  asserují 2 testové soubory ze 496 a repo nemá screenshoty.
- Unit: sloučení řetězce dědičnosti, fallback varianty, alias mapa + warn-once,
  neznámý token v debug/produkci, prázdný hook registry, kotva měnící tvar
  skeletonu (cache klíč).
- Integration: aplikační theme přepíše token → změněný markup v **každém** ze
  čtyř balíčků.
- Perf: render counter (fáze 5) — theme lookupy nerostou s `R`.
- Browser: `npm run verify:drivers` po fázích 2–6.
- Coverage: `verify-coverage.php` hlídá diff v `packages/*/src/*.php` (řádek 117)
  proti floorům (core 95, forms 95, table 91, sortable 96) — každý surface
  provider potřebuje vlastní test, jinak fáze 2 gate shodí.

**Past při vývoji:** publikované views v `workbench/` stínují package views —
při migraci může vypadat, že se token neprojevil, přitom se renderuje stará
publikovaná kopie. Před verifikací je smazat.

## 12. Rizika

| Riziko | Dopad | Mitigace |
| --- | --- | --- |
| Migrace 137 šablon bez důkazu o neměnnosti | tichá vizuální regrese napříč balíčky | golden markup jako fáze 0, gate v každé fázi |
| Statická `$colorClassCache` po delegaci | první theme zamrzne na celý Octane worker | cache zrušit ve fázi 2 (§4) |
| Slovník tokenů se po vydání přejmenuje | breaking change pro každou app theme | zmrazení ve fázi 1 do vlastního dokumentu, alias mapa, strict mode |
| Regrese výkonu v řádkovém loopu | návrat „N×View" | hoist do RenderPlan/Skeletonu, render counter ve fázi 5 |
| Aplikační theme mimo Tailwind globy | polovina uživatelských tříd zmizí | `wire:theme:make` vypíše glob řádek, `wire:theme:safelist` |
| Fork publikovaný před migrací | theme ho míjí, uživatel neví proč | `wire:views:status`, výslovně v upgrade notes |
| Morph markery v `index.blade.php` | rozbitá Livewire morph, neviditelná pro Pest | fáze 5 zvlášť, žádná strukturální změna při migraci tříd, drivery |
| Migrace uvázne v půlce | půl systému čte tokeny, půl literály | fázový gate + `--only=<package>` na lint skriptu |
| Kotva uvnitř řádku mění tvar skeletonu | špatný markup z cache | tvar do cache klíče (§5) |

## 13. Otevřené otázky

1. Callable hodnoty tokenů? **Návrh: ne** — rozbíjí Tailwind scanning i
   memoizaci; stav řešit variantou tokenu.
2. Typovaný ViewModel pro `->view()` (vrstva 3)? **Návrh: ano, samostatným
   plánem po fázi 7** — jinak se dvě velké migrace potkají v jednom souboru.
3. Presety na instanci (`->compact()`)? **Návrh: varianty tokenů**, dokud se
   neukáže potřeba.
4. Sjednotit `Column::extraAttributes(string)` na pole jako v core? Breaking
   change → 2.1, ne sem (§3).

## 14. Vztah k `canonical-ownership-consolidation.md`

Oba plány sahají na stejné soubory (`HasColor`, modal/badge/button povrchy,
duplikované `match` mapy v šablonách — dnes v `modals/confirmation`,
`modals/slide-over`, `modals/modal`, `audit/trail`, `components/color-picker`,
`partials/date-time-typing`). Bez rozhodnutí by vznikly dvě různé cílové podoby
téhož kódu.

**Rozhodnutí: template system je implementací cílového modelu toho plánu, ne
jeho konkurentem.** Per-surface providery ze §3 *jsou* "per-surface resolvery"
z jeho bodu 2, owner-first pravidlo ze §3 *je* jeho bod 3, literální stringy
jsou jeho bod 4. Otevřené položky H2/H3/H4/M1–M4/L1 se proto neřeší zvlášť —
splní se tím, že daný povrch dostane token a šablona přestane mít vlastní mapu.
Do consolidation plánu patří jednořádkový odkaz sem, ne paralelní práce.

Pokud by se ale ukázalo, že některá položka (typicky H2 ButtonColumn, největší
blast radius) chce vlastní vizuální verifikaci dřív, než dojde fáze 2, má
přednost consolidation plán a template system ji pak už jen převezme.

## 15. Co změnil audit (24. 8. 2026)

Auditováno proti kódu, ne proti plánu. Opraveno oproti první verzi:

- **Rozpor s canonical ownership** — první verze zaváděla `ThemeSheet` jako
  jednu plochou mapu a `@wireClass` do všech šablon; obojí je přesně to, co
  consolidation plán zakazuje ("bag class stringů", obcházení owner API).
  Přepsáno na per-surface providery + owner-first pravidlo (§3, §14).
- **Statické cache** — dopad delegace `HasColor` na `$colorClassCache` (a strop,
  který tři memo vrstvy kladou budoucímu runtime přepínání) v první verzi
  chyběl (§4, §6.4).
- **Chybějící důkaz o neměnnosti výstupu** — repo nemá snapshoty ani screenshoty
  a utility třídu asserují 2 soubory ze 496; golden-markup harness je nově
  fáze 0 a nosný gate (§1, §11).
- **Render counter** — první verze se odvolávala na pojistku z render-engine
  plánu jako na hotovou; neexistuje (§6.3).
- **Tailwind riziko bylo přehnané** — package `src/**/*.php` je v dokumentovaných
  globech už dnes; skutečné riziko je aplikační theme třída (§8).
- **`extraAttributes` precedence** — tvrzení "instance přebije theme" bylo
  nepravdivé (druhý `class` atribut, vyhrává první); merge tříd je nově součást
  fáze 2 (§3).
- **Doplněno:** třetí override cesta (app-level view bez publishe, §7),
  `sync-boost-docs.php --check` jako gate fáze 7, `packages/boost` výslovně mimo
  rozsah, CS docs do fáze 7, deliverable slovníku jako soubor (§4), umístění
  `ThemeManager` odůvodněné proti standardu (§3), žádný helper/facade (§3).
