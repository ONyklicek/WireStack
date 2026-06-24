---
title: Canonical ownership consolidation — remaining items
date: 2026-06-04
scope: packages/core, packages/forms, packages/table, packages/sortable
status: plan (open items; H1 + H2 done — viz Hotovo)
related:
  - architecture/plans/icon-color-enum-audit.md
  - memory/design_system_concern_consolidation_2026_06_04.md
---

# Konsolidace kanonického vlastnictví — zbývající položky

Navazuje na konsolidaci color/size/icon do `Foundation/Concerns/*`
(`design_system_concern_consolidation_2026_06_04`). Tento dokument drží
**neimplementované** položky z auditu invariantů (2026-06-04 #2).

**Současní kanoničtí vlastníci (referenční):**
- `packages/core/src/Foundation/Concerns/HasColor.php` — paleta tříd
  (`getBadgeColorClasses`, `getTextColorClasses`, `getModalIconBgClass`,
  `getModalIconTextClass`, `getAlertColorClasses` (banner surface),
  `getModalSubmitButtonClasses` (modal submit surface) public static;
  `getSolidColorClasses`, `getOutlinedColorClasses`, `getGhostColorClasses`,
  `getIconButtonColorClasses` protected instance).
- `packages/core/src/Foundation/Concerns/HasColumnSpan.php` — `getColumnSpanClass`
  (responsive grid span → Tailwind, kanonický pro infolist entries i layout).
- `packages/core/src/Foundation/Concerns/HasSize.php` — `getBadgeSizeClasses`.
- `packages/core/src/Foundation/Colors/Color.php` — `resolve()` (alias resolver).
- `packages/core/src/Foundation/Concerns/HasIcon.php` + `Icons/IconManager.php`.

**Systémové omezení (důvod, proč drift vznikl):** část canonical API dnes žije
na traitech a je buď `public static`, nebo `protected` instance metoda. Přímé
volání `HasColor::method()` je na novějších PHP verzích sice syntakticky možné,
ale je to špatný cílový tvar pro veřejné API a na PHP 8.4 deprecated; view vrstva
proto skončila u kopírovaných `match` map. Náprava nemá být "volat traity z Blade",
ale **dostat canonical paletu za stabilní owner-facing API** — viz
[Cílový model](#cilovy-model-ve-stylu-filamentu).

---

## ✅ Hotovo (kontext)

- **H1 — modal-icon delegace.** `ModalComponent` + `ConfirmationComponent`
  nyní `use HasColor` a delegují `iconBgClass()`/`iconColorClass()` na
  `self::getModalIconBgClass/getModalIconTextClass`. Canonical metody rozšířeny
  na věrný superset (přidán `primary`+`gray`, neutrální gray default). Identický
  výstup pro reálné vstupy, veřejné API beze změny. Tested: core 967 green.

- **H2 — Htmlable render audit (2026-06-24).** Vícevláknový audit + sjednocení
  reusable markupu na PHP htmlable gettery / kanonické resolvery; Blade už jen
  konzumuje. Hotovo:
  - **Loading spinner** — jeden kanonický partial `wire-core::partials.spinner`
    (param `$class`, volitelný `$wireTarget`); 4 inline kopie (table column
    partial, header-action, action-modal ×2, forms file-upload) na něj
    delegují.
  - **M3 alert** — `Alert::getColorClasses()` deleguje na nový
    `HasColor::getAlertColorClasses()` (banner surface, `bg-*-50/border/text`).
  - **M3 rating** — `Rating::getColorClasses()` jako owner-local brighter
    `rating-active` surface (`-500/-400`); explicitně přijato jako vlastní
    povrch, ne `text` drift.
  - **Modal submit button** — `HasColor::getModalSubmitButtonClasses()`,
    propsán přes `HasModal::getModalConfig()['submitButtonClasses']`; oba footery
    action-modalu (slide-over i centered) sjednoceny (souvisí s M1).
  - **Column-span** — `HasColumnSpan::getColumnSpanClass($default='')`; 6
    infolist entry views už neopakují `match`.
  - **TextEntry** — `getTextColorClass()` + `getBadgeColorClass()` místo
    statických `HasColor::` volání v Blade.
  - **Stat (stats-overview)** — `getValueColorClass/getDescriptionColorClass/`
    `getChartColorClass()`; odstraněna nebezpečná `text-{$color}-600`
    interpolace.
  - **BarChartWidget** — `getCardRadiusClass()` + `getPartialName()`.
  - **StackedColumn** — `getLinesHtml(): Htmlable` (escaped) místo Blade closure.
  - **Table responsive** — `getStackedTableHiddenClass/getStackedCardsVisibleClass()`.
  - **Sortable drag handle** — markup do partialu `wire-sortable::partials.`
    `drag-handle` + `Table::getDragHandleHtml()` macro; injektován do Alpine
    configu místo JS template stringu.
  - **HeaderAction::getBadgeHtml()** sjednocen na `Htmlable` (jako ActionGroup).
  - Tested: core 1155 / forms 395 / table 809 / sortable green; pint passed;
    phpstan beze změny (8 preexisting errorů mimo dotčené soubory).

---

## Cílový model (ve stylu Filamentu)

Místo jedné univerzální `Support/ColorClasses.php` vrstvy je cílový tvar:

1. **Sémantická paleta / registry.**
   Jediný owner pro významy `primary`, `success`, `danger`, `warning`, `info`,
   `gray` + aliasy (`blue`, `green`, `yellow`, `secondary`). Dnes tu roli plní
   hlavně `Color::resolve()` a literal mapy v `HasColor`; do budoucna má být
   tato vrstva explicitní.
2. **Per-surface resolvery.**
   Stejná semantická barva se může renderovat jinak podle povrchu. Nesmí vzniknout
   jeden "bag class stringů pro všechno". Canonical surface typy jsou minimálně:
   `badge`, `text`, `modal-icon`, `button-solid`, `button-outlined`,
   `button-link`, `icon-button`, `dropdown-item`, `toggle-track`, `banner`,
   případně `rating-active`.
3. **Owner-facing API pro views.**
   Kde Blade dostává přirozeného ownera (`Action`, `HeaderAction`, `BulkAction`,
   `Column`, komponenta), má se delegovat přes public helper / render-data API na
   ownerovi. Sdílená support/helper třída je vhodná jen tam, kde žádný přirozený
   owner neexistuje nebo by jinak vznikalo více stejných public accessorů.
4. **Tailwind JIT-safe implementace.**
   Třídy musí zůstat jako literální stringy, bez runtime skládání názvů tříd.

Tento dokument proto používá jazyk **"canonical surface resolver"**. Konkrétní
implementace může být `Foundation/Support/*`, malé value objekty typu
`*ColorMap`, nebo owner-local helpery, podle blast radius a přirozeného ownera.

---

## LOW RISK

### L1 — Duplicitní button-size `match` (BaseAction vs HasButtonStyles)
- **Kanonický vlastník:** `HasSize` (kandidát: nové `getButtonSizeClasses`).
- **Místo porušení:**
  - `packages/core/src/Actions/BaseAction.php:142-157`
    (`resolveButtonSizeClasses`, **živé** — volá `Action.php:153`).
  - `packages/core/src/Actions/Concerns/HasButtonStyles.php:73-92`
    (`getButtonSizeClasses`, **fakticky mrtvé** — žádná třída trait neuse-uje,
    jen deprecated alias `Concerns/HasButtonStyles.php`).
- **Proč problém:** dvě byte-identické size mapy (icon `p-*` + non-icon
  `px/py/text/gap`).
- **Refaktor:** zavést canonical button-size resolver pro surface `button`
  (např. `getButtonSizeClasses`, `ButtonSizeMap`, nebo public helper na ownerovi)
  a nechat `BaseAction` delegovat. `HasButtonStyles` je mrtvý → může delegovat
  také, nebo zůstat beze změny do v2 a pak se odstranit s deprecated aliasem.
- **Riziko:** nízké (identický výstup). Architektonické riziko je spíš přidat
  nové public API na špatného ownera než nemít jednu globální support třídu.
- **BC:** žádná změna veřejné signatury.

### L2 — Per-column size mapy bez vlastníka
- **Místa:** `ImageColumn.php:163-174`, `IconColumn.php:184-194`,
  `StackedColumn.php:362-373` + `379-393`, `ButtonColumn.php:510-516`,
  `Column.php:952-962`.
- **Proč problém:** roztříštěné pixel/Tailwind size škály; drobná divergence.
- **Refaktor:** sjednotit do canonical size resolveru (rozšířit `HasSize`).
  Pozn.: škály se *liší účelem* (avatar px pro UI-Avatars API vs CSS třídy),
  takže nutné rozlišit více resolverů, ne jeden.
- **Riziko:** nízké až střední (pokud se sjednotí konkrétní hodnoty → vizuální
  posun).

### L3 — Inline single-color literály
- **Místa:** `packages/forms/resources/views/components/slider.blade.php:87`
  (`bg-primary-100…`), `forms/.../tags.blade.php:124-126`,
  `forms/.../markdown-editor.blade.php:27`,
  `packages/sortable/src/Concerns/WithSortable.php:72`.
- **Proč problém:** tyto literály obcházejí canonical surface ownery, ale
  nejsou všechny stejného typu. `slider` / `tags` / `sortable` vypadají jako
  selection/soft-primary surface; `markdown-editor` je obyčejný inline link/text
  surface. Bez této klasifikace hrozí falešné "sjednocení".
- **Refaktor:** po zavedení odpovídajícího surface resolveru / owner helperu
  nahradit voláním canonical API. Pozn.: ne každý literál musí skončit na
  `badge`/`text` resolveru; některé jsou samostatný surface.
- **Riziko:** nízké; nejdřív potvrdit surface klasifikaci, až potom případnou
  hue změnu.

---

## MEDIUM RISK

### M1 — `ConfirmationComponent::submitButtonClasses()`
- **Kanonický vlastník:** `HasColor::getSolidColorClasses()`.
- **Místo porušení:** `packages/core/src/Modals/View/ConfirmationComponent.php:87-99`.
- **Proč problém:** lokální solid mapa **driftnutá** — `hover:bg-*-500` (canonical
  `-700`), bez `text-white`, bez `dark:` variant.
- **Refaktor:** delegovat na `getSolidColorClasses($color)`. **Vyžaduje vizuální
  rozhodnutí** — sjednocení změní hover intenzitu a přidá dark mode.
- **Riziko:** střední (vizuální změna potvrdit screenshotem).

### M2 — `ActionGroup` duplikace
- **Místo porušení:** `packages/core/src/Actions/ActionGroup.php:338-345`
  (button-size mapa — viz L1) + `387-393` (badge-color mapa
  `bg-primary-500 text-white` — **odlišný povrch**, solid count-badge, ne soft
  pill).
- **Proč problém:** size mapa = L1 duplikát; badge-color je vědomě jiný styl, ale
  mapping palety je duplikován.
- **Refaktor:** size přes L1 canonical resolver; count-badge necpát do
  `getBadgeColorClasses()`, ale modelovat jako vlastní surface
  (`action-count-badge` / `badge-solid`) pokud se objeví druhý consumer.
  Single-consumer today → lze zatím nechat owner-local helper.
- **Riziko:** střední.

### M3 — Forms Blade palety (alert, rating) — ✅ HOTOVO (viz H2)
- **Vyřešeno (2026-06-24):** alert → `HasColor::getAlertColorClasses()` (banner
  surface), rating → `Rating::getColorClasses()` (owner-local `rating-active`
  brighter surface, vědomě ponecháno mimo `text` resolver). Hodnoty zachovány
  1:1, žádný vizuální posun.
- **Kanonický vlastník:** `Color::resolve()` + surface resolvery (`banner`,
  `rating-active`, případně `text` pokud vizuálně sedí).
- **Místa porušení:**
  - `packages/forms/resources/views/components/alert.blade.php:2-7` — plná color
    mapa (success/warning/danger), default `bg-blue-50` (banner styl
    `bg-*-50/border-*-200/text-*-800` — **jiný povrch** než soft pill).
  - `packages/forms/resources/views/components/rating.blade.php:7-12` — color
    mapa s **odlišnými hodnotami** (`text-primary-500`, `text-amber-400`) vs
    canonical `getTextColorClasses` (`-600/-400`).
- **Proč problém:** paleta mimo canonical, hodnoty se rozcházejí.
- **Refaktor:** alert = vlastní `banner` resolver; rating = buď explicitně přijmout
  jako zvláštní brighter `rating-active` surface, nebo po vizuální kontrole
  sjednotit na `text` resolver. Není to čistý badge/text drift.
- **Riziko:** střední (vizuální posun hue).

### M4 — Audit trail Blade
- **Místo porušení:** `packages/core/resources/views/audit/trail.blade.php:17-42`.
- **Proč problém:** inline per-event barvy (created→emerald, updated→blue,
  deleted→red, bulk→amber) mimo canonical.
- **Refaktor:** rozdělit na dvě vrstvy: (1) event→semantic color mapping jako
  owner-local business pravidlo, (2) event chip/icon surface přes canonical
  resolver. Semantika a surface se nemají míchat.
- **Riziko:** střední.

---

## HIGH RISK

### H2 — `ButtonColumn` kompletní paralelní button-styling
- **Kanonický vlastník:** `HasColor` (solid/outlined/icon) + `HasSize`.
- **Místa porušení:**
  - `packages/table/src/Columns/ButtonColumn.php:444-516` — tři vnořené `match`
    mapy barev (`outlined`/`link`/`default`) + size mapa (`455-461`) + icon-size
    mapa (`510-516`).
- **Proč problém:** kompletní druhá implementace palety tlačítek mimo canonical;
  `link` varianta v canonical **neexistuje** a `ButtonColumn` navíc používá širší
  set surface arms (`info`, `secondary`) než dnešní action canonical. Nejde o
  prostou delegaci na stávající metody.
- **Refaktor:** (1) definovat canonical button surface taxonomy alespoň pro
  `button-solid`, `button-outlined`, `button-link`, `icon-button`; (2) rozhodnout,
  zda `ButtonColumn` adoptuje stejný button surface contract jako akce, nebo si
  ponechá table-specific owner s delegací do sdílených map; (3) až potom převést
  lokální `match` mapy.
- **Riziko:** **vysoké** — největší blast radius, vizuální regrese; dělat
  samostatně s vizuální verifikací.

### H3 — `ToggleColumn::getOnColorClass()` lokální resolver
- **Kanonický vlastník:** `Color::resolve()` + paleta.
- **Místo porušení:** `packages/table/src/Columns/ToggleColumn.php:117-130`.
- **Proč problém:** mapuje `primary→bg-blue-600, success→bg-green-600,
  warning→bg-yellow-500…` — používá **green/blue/yellow hue**, kdežto canonical
  paleta sjednotila success→**emerald**, primary→**primary-600**, warning→**amber**.
  → nekonzistentní se zbytkem appky.
- **Refaktor:** přidat canonical `toggle-track` / `solid-nontext` surface resolver.
  Není to badge ani text; potřebuje vlastní kontrastní pravidla.
- **Riziko:** **vysoké** — mění hue (green→emerald, blue→primary), viditelná
  změna toggle barev; vyžaduje vědomé přijetí + screenshot.

### H4 — Blade views akcí duplikují instanční color metody
- **Kanonický vlastník:** action trigger surfaces (`dropdown-item`,
  `button-solid`, `button-outlined`, případně `button-link`) zpřístupněné přes
  owner API.
- **Místa porušení:**
  - `packages/core/resources/views/actions/dropdown-item.blade.php:17-23` —
    **byte-identické** s `getGhostColorClasses()`.
  - `packages/table/resources/views/tables/actions/header-action.blade.php:11-25`
    — duplikuje size + button surface logiku pro `HeaderAction`.
  - `packages/core/resources/views/actions/bulk-button.blade.php:5-18` —
    solid/outlined mapy **bez `dark:` variant a bez `gap-*`** (drift) + size mapa.
- **Proč problém:** barevná logika v šablonách místo delegace; bulk-button už
  driftnut (chybí dark mode); `header-action` navíc není `ButtonColumn` problém,
  ale action-trigger view drift.
- **Refaktor:** preferovat public render helpers / render-data API na
  `Action`/`HeaderAction`/`BulkAction`, ne volání traitů z Blade. `dropdown-item`
  = no-op delegace; `header-action` = přesun na owner helper; `bulk-button` =
  po owner delegaci přidá dark mode + gap (vizuální změna → potvrdit).
- **Riziko:** střední–vysoké (`dropdown-item` triviální, `header-action`
  střední, `bulk-button` mění výstup).

---

## Doporučené pořadí (low → high)

1. **Cílový model** — potvrdit taxonomy: semantic palette + per-surface resolvery
   + owner API pro views. Sdílená support vrstva je volba implementace, ne
   povinný první krok.
2. **L1** — canonical button-size resolver; `BaseAction` deleguje, deprecated
   `HasButtonStyles` podle potřeby také.
3. **H4 dropdown-item + header-action** — low/medium-risk owner delegace bez
   změny surface semantics. `bulk-button` zvlášť.
4. **M1 / M2 / M4** — confirmation submit, ActionGroup count-badge, audit trail;
   tady už se vyplatí mít rozlišené surface contracts.
5. **L3 + M3 (rating)** — až po potvrzení, který literál je skutečně `text`
   surface a který je vlastní surface.
6. **M3 alert** — přidat `banner` resolver.
7. **H3 ToggleColumn** — přidat `toggle-track` resolver a vědomě přijmout hue změnu.
8. **H4 bulk-button** — po owner delegaci potvrdit dark-mode/gap změnu.
9. **H2 ButtonColumn** — největší blast radius, samostatně, vizuální verifikace.

## Reziduální rizika / nejistoty

- Některý drift (chybějící `dark:` v bulk-button) může být **záměrný** — před
  sjednocením potvrdit.
- Tailwind JIT: všechny class stringy musí zůstat literální (žádná dynamická
  konkatenace tříd) — platí pro support třídu i Blade.
- Největší architektonické riziko je **slít různé surface contracts do jedné
  univerzální mapy tříd**. Filament-like model funguje právě proto, že `button`,
  `link`, `badge`, `toggle`, `dropdown item` a další povrchy mají vlastní
  resolver nad stejnou sémantickou paletou.
- Single-consumer povrchy (Alert banner, Toggle, ActionGroup count-badge) drží
  *odlišný vizuální jazyk*, ne jen duplikát — sjednocení palety neznamená
  sjednotit i povrch; rozlišit "stejná paleta" vs "stejný styl".
- Nezměřen runtime/CSS-bundle dopad případné shared support vrstvy; nepřidávat ji
  bez konkrétní potřeby.
