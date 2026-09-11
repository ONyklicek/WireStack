---
title: Navigace — sub-navigace záznamu, vodorovné menu, filtr/oblíbené/nedávné, klávesnice
date: 2026-09-11
scope: |
  packages/core (Foundation/Routing, Core/Resources/Navigation), packages/panels
  (Resources/Pages, Routing, views), packages/admin (View, views, config, lang),
  docs/{panels,admin}/ + docs/cs, workbench/scripts, scripts/hook-names.json
status: KROK 0 a ČÁST A DODÁNY 2026-09-11 (viz §2 a §3, oddíl „Co skutečně vzniklo"). Kroky 2–6 nezahájeny. Zadání vlastníka („vylepšit navigaci v administraci i obecně"), měřeno proti stromu 2026-09-11
owner_request: |
  všechny čtyři směry naráz: sub-navigace záznamu, vodorovné (top) menu,
  hledání/oblíbené/nedávné v menu, klávesnice a přístupnost menu
decision: |
  Čtyři části, jeden společný základ. Krok 0 (vlastník otázky „kde jsem")
  je podmínkou tří ze čtyř — bez něj vznikne aktivní stav počtvrté, pokaždé
  v jiné šabloně. Části jsou pak samostatně dodatelné a samostatně vratné.
adrs:
  - architecture/decisions/0025-core-module-layers.md   # L0 Foundation / L1 Core — kam smí ActiveNavigation
  - architecture/decisions/0026-registration-seam.md    # Catalog, ProvidesPages, ResolvesPageUrls
  - architecture/decisions/0027-routing-zones.md        # zóna se čte při renderu stránky, ne v Livewire updatu
  - architecture/decisions/0028-optional-panel-shell.md # sloty, ne fluent panel builder
  - architecture/decisions/0030-hook-surface.md         # PageChrome regiony, page.mounting
parent: architecture/plans/v3-optional-admin-and-module-packages.md
siblings:
  - architecture/plans/admin-chrome-placement.md        # nezahájený; část B sdílí jeho config blok `layout`
---

# Navigace: čtyři povrchy, jeden základ

Menu samo je hotové a vyladěné — rail, flyouty, skládací skupiny, badge, zóny,
aktivní řádek podle klíče zóny, ovladač `verify-workspace-nav`. Tenhle plán na
něj nesahá jako na rozbitou věc. Přidává **druhou vrstvu navigace** (sub-navigace
záznamu), **druhý tvar** (vodorovné menu), **paměť** (oblíbené a nedávné) a
dotahuje **klávesnici**.

---

## 0. Co je hotové, aby to plán nevymýšlel podruhé

| Hotovo | Kde |
|---|---|
| `Workspace::navigation()` / `items()` — seskupení, řazení, viditelnost, `linkedOnly`, hook `navigation.building` | `packages/core/src/Core/Resources/Workspace.php` |
| `NavigationItem` / `NavigationGroup` / `NavigationGroups` nad kanonickými concerny (`HasLabel`, `HasIcon`, `HasVisibility`, `HasSortOrder`, `CanBeCollapsed`) | `packages/core/src/Core/Resources/Navigation/` |
| Klíč → URL přes `ResolvesPageUrls`, zóny přes `Zone` | `packages/panels/src/Routing/`, `packages/core/src/Foundation/Routing/Zone.php` |
| Sidebar, rail (64 px, rozhodnutý před prvním paintem), tooltip vs. popover, badge jako pilulka vs. tečka | `packages/admin/resources/views/{sidebar,partials/nav-item,partials/rail}.blade.php` |
| Drobečky jako `NavigationItem[]`, výchozí stopa resource → titulek | `packages/core/src/Core/Resources/View/Breadcrumbs.php`, `packages/panels/src/Resources/Concerns/BelongsToResource.php:99–120` |
| ⌘K paleta, v ní i položky menu (`PaletteNavigation`, `linkedOnly: true`) | `packages/core/src/GlobalSearch/` |
| Per-uživatelské úložiště: `PreferenceDriver` + `PreferenceManager` (null/session/database) | `packages/core/src/Foundation/Preferences/` |

**Nic z toho se nenahrazuje.** Každá část níž buď rozšiřuje existujícího
vlastníka, nebo si o nového řekne v nejnižší vrstvě, která ho unese.

---

## 1. Co měření našlo

### 1a. Aktivní stav je dnes tři různá pravidla, všechna v šabloně

| Fakt | Kde | Co z toho plyne |
|---|---|---|
| `$isActive = isset($itemKey) && $itemKey === $activeKey` | `partials/nav-item.blade.php:33` | registrovaná položka se rozsvítí podle **klíče zóny** — robustní, platí i na `edit` stránce |
| `$isCurrent = rtrim($url,'/') === rtrim(url()->current(),'/')` | `:34` | vlastní `->url()` položka se rozsvítí jen na **přesné shodě URL** → `/settings/general/edit` nerozsvítí `/settings/general` |
| `$hasActiveChild = collect($children)->contains(…stejná přesná shoda…)` | `:35` | totéž pro potomky, plus třetí zápis téhož pravidla |
| `Zone::parse()` má v regexu poslední segment **nepojmenovaný** (`\.[^.]+$`) | `packages/core/src/Foundation/Routing/Zone.php:111` | druh stránky (`index`/`view`/`edit`/vlastní) se zahodí — přesně to, co sub-navigace potřebuje |

Tři pravidla pro jednu otázku, v Blade souboru. Čtvrtý povrch (vodorovné menu)
by z toho udělal šest. To je důvod, proč je krok 0 první a ne poslední.

### 1b. Sub-navigace záznamu neexistuje

| Fakt | Kde | Co z toho plyne |
|---|---|---|
| `ProvidesPages::pages()` vrací `kind => class\|RoutePage`; router zná čtyři tvary, neznámý klíč je vlastní segment | `packages/core/src/Foundation/Routing/Contracts/ProvidesPages.php`, `packages/panels/src/Routing/ResourceRoutes.php:59–64,151` | **seznam záložek už je deklarovaný** — nic nového se deklarovat nemusí |
| `SHAPES` ví, který druh bere záznam (`record: bool`) | `ResourceRoutes.php:59–64` | „stránky jednoho záznamu" jsou odvoditelné; u vlastní stránky rozhoduje `{record}` v `uri()` |
| `RoutePage` nese `permission()` → `can:` middleware, a `getPermission()` existuje výslovně proto, aby šlo schovat tlačítko, které tam vede | `packages/core/src/Foundation/Routing/RoutePage.php:91–101` | záložka na stránku bez oprávnění se nevykreslí, a pravidlo se nepíše podruhé |
| `RoutePage` **nemá** label, ikonu ani pořadí, a `permission` si drží vlastní vlastností místo `HasAuthorization` (concern je importovaný jen kvůli `{@see}`) | tamtéž, `:7,27,33,71` | label/ikona/pořadí musí přijít **z kanonických concernů**, ne zopakovat tenhle rozchod |
| `BelongsToResource` si zónu čte v `mountBelongsToResource()` do public property (`$breadcrumbZone`) | `packages/panels/src/Resources/Concerns/BelongsToResource.php:47–52` | druh stránky se musí držet stejně — ADR 0027, jinak je po prvním Livewire updatu null |
| `pageUrl($page, $record)` už umí odkaz na libovolný druh stránky ve správné zóně | tamtéž `:139–151` | URL záložky je jeden existující volací řádek |
| Hlavička stránky (drobečky + titulek) je jeden partial pro všech pět stránek | `packages/panels/resources/views/pages/partials/header.blade.php` | záložky mají jedno místo, kam patří |
| Relation managery se skládají **pod** formulář/infolist | `pages/{view,edit}-page.blade.php` | jsou vložené, ne routované — proto nejsou v rozsahu, viz §9 |

### 1c. Vodorovné menu

| Fakt | Kde | Co z toho plyne |
|---|---|---|
| `admin-chrome-placement.md` ho **výslovně odložil** jako „jiná featura, jiný layout" | `architecture/plans/admin-chrome-placement.md:247` | tenhle plán tu čáru vědomě překračuje; sdílí s ním config blok `layout`, takže obojí je aditivní v jednom souboru |
| `config/wire-admin.php` má jediný klíč `brand` | `packages/admin/config/wire-admin.php` | `layout.navigation` je druhý (nebo třetí, přistane-li sousední plán dřív) klíč, ne přepis souboru |
| Layout je `<div class="lg:flex">` + `<aside>` + `<header>` + `<main>` | `layout.blade.php:161–165,378` | vodorovné menu je **druhý pruh pod headerem**, ne přepsaný header |
| `wireFlyout` bere `placement` a `offset` a teleportuje panel do `<body>` | `packages/core/resources/js/dropdown.js:350,372` | panel dolů (`bottom-start`) je existující primitiv, nový ovladač netřeba |
| Sidebar sám varuje: dvě kopie menu = každý `data-testid` v dokumentu dvakrát a každý test tiše počítá dvojnásobek | `sidebar.blade.php:11–15` | vodorovné menu **musí** mít vlastní jména (`admin-topnav-*`), protože na telefonu bude šuplík vedle něj |
| `[data-rail-only] { display: none; }` je globální pravidlo z `partials/rail.blade.php` | `partials/rail.blade.php` (style blok) | kdyby se rail partial u vodorovného tvaru nevložil, **vyskočí badge tečky** vedle pilulek. Vkládá se dál, jen se nikdy nezapne |
| Ledger jmen `@wireEl` smí růst, ne se zmenšovat; dnes 251 jmen, z toho 11 `admin-nav-*` | `scripts/hook-names.json` | nová jména se zapisují `npm run hooks:names` |

### 1d. Filtr, oblíbené, nedávné

| Fakt | Kde | Co z toho plyne |
|---|---|---|
| ⌘K paleta už položky menu hledá, `linkedOnly: true`, bez limitu | `packages/core/src/GlobalSearch/PaletteNavigation.php` | filtr v menu **není hledání** — je to prohlížení se zachovanou strukturou. Musí to tak být napsané i v docs, jinak je to druhý vyhledávač |
| `PreferenceDriver` je kanonický per-uživatelský JSON pytlík klíčovaný surface + user (+ view) | `packages/core/src/Foundation/Preferences/Contracts/PreferenceDriver.php` | oblíbené a nedávné **nemají** mít vlastní úložiště |
| Výchozí driver je `null`, pro hosta `session` | `packages/core/config/wire-core.php:331–339` | v aplikaci, která nic nenastavila, se **nic neuloží** → připínání se nesmí nabízet, jinak je to tlačítko, které tiše nedělá nic |
| Sidebar je Blade komponenta, ne Livewire | `packages/admin/src/View/Sidebar.php` | připnutí potřebuje round trip → §5 zavádí jedinou malou Livewire komponentu, ne převod celého menu |
| `Hook::PageMounting` (`page.mounting`) se dispatchuje jednou z traitu, který skládá každá resource stránka, a **naposled** (record i formulář jsou hotové) | `packages/core/src/Foundation/Enums/Hook.php:109`, `Core/Plugin/Hooks/PageMountingPayload.php` | „co jsem naposledy navštívil" má vlastníka; stránka, která hook nedispatchuje, se nezapamatuje — a to je viditelné, ne tiché |

### 1e. Klávesnice a přístupnost

| Fakt | Kde | Co z toho plyne |
|---|---|---|
| Šuplík na telefonu zavírá Escape a klik do ztmavení; **fokus se nikam nepřesouvá a nevrací** | `sidebar.blade.php:34–37,54–62` | otevřený šuplík = Tab chodí po stránce **za** překryvem. Skutečná vada, ne ozdoba |
| `<main id="wire-admin-main">` nemá `tabindex="-1"` | `layout.blade.php:378` | skip link cíl nedostane fokus v části prohlížečů — odkaz vypadá, že nedělá nic |
| V menu se chodí jen Tabem, řádek po řádku; skupina má `aria-expanded`/`aria-controls`, řádek `aria-label`, `aria-current="page"` | `sidebar.blade.php:113–124`, `nav-item.blade.php:125–135` | základ je v pořádku; chybí pohyb šipkami, Home/End a skok na první písmeno |
| Řádky nemají žádnou `focus-visible:` třídu | `nav-item.blade.php:136–142` | spoléhá se na výchozí obrys prohlížeče — ověřit v ovladači **dřív**, než se cokoli přidá |
| `aria-current="page"` nese i řádek, který je aktivní jen „protože jsem někde uvnitř resource" (edit stránka) | `nav-item.blade.php:135` | se sub-navigací (§3) by byly na stránce dvě `aria-current="page"`. Řeší krok 0 |

---

## 2. Krok 0 — jeden vlastník otázky „kde jsem"

Nic z §1a není samostatná vada; dohromady je to čtvrtá kopie jednoho pravidla,
která se chystá vzniknout. Kanonický vlastník, v nejnižší vrstvě, která ho unese.

**`Zone::pageOf()` / `Zone::currentPage()`** — L0, tentýž jediný ukotvený regex,
jen s pojmenovanou třetí skupinou:

```php
// Zone.php:111 — jediné místo, kde je tvar jména routy napsaný
preg_match('/^(?<zone>.+\.)?wire\.(?<key>[^.]+)\.(?<page>[^.]+)$/', $routeName, $m)
```

Tři čtenáři místo dvou, žádný nový tvar. Stejné varování jako u `current()`:
čte se při renderu **stránky**, ne v Livewire updatu.

**`Core\Resources\Navigation\ActiveNavigation`** — L1 (jmenuje `NavigationItem`,
takže do Foundation nesmí; ADR 0025 a test `ModuleLayers` to chytí hned):

```php
final readonly class ActiveNavigation
{
    public static function current(): self;          // Zone::current() + currentKey() + currentPage() + url()->current()
    public static function for(?string $zone, ?string $key, ?string $page, string $url): self;

    public function isActive(NavigationItem $item, ?string $key = null): bool;
    public function isBranchActive(NavigationItem $item): bool;   // já, nebo některý potomek
    public function isExactly(NavigationItem $item, ?string $key = null): bool; // pro aria-current
    public function page(): ?string;                 // druh stránky — čte §3
}
```

Pravidla, v tomhle pořadí:

1. **`activeWhen()`, pokud ho položka deklarovala** — přebíjí všechno ostatní.
2. **Shoda registrovaného klíče** s klíčem zóny. Dnešní pravidlo, beze změny:
   `edit` stránka rozsvítí řádek resource.
3. **URL**, na přesnou shodu. Lomítko na konci a relativní URL se normalizují
   pryč (`->url('/settings')` vs absolutní `route()`), query string ne.

**Změna proti prvnímu návrhu, při implementaci.** Původně tu stálo „přesná
shoda, **nebo** předek aktuální cesty, kromě kořene zóny". Předkovské pravidlo
se ukázalo jako nebezpečné a kořen z téhle třídy nepoznatelný: `ActiveNavigation`
vidí URL položky a URL požadavku, ne to, kde je shell namountovaný — takže
položka *Domů* mířící na `/admin` by svítila na každé stránce pod ním, napořád.
Vždy svítící řádek je hlasitější vada než řádek, který nesvítí, i když by mohl.
Oprava §1a se tedy dodává jako **jednořádkové opt-in**:

```php
NavigationItem::make('Settings')->url(route('settings.general'))->activeWhen('settings/*');
```

Vedlejší efekt je, že krok 0 je jinak beze změny chování: žádné dnešní menu
nezmění vzhled, mění se jen `aria-current` a přibývá slovník.

**`NavigationItem::activeWhen(Closure|string|array $patterns)`** — opt-in přebití
pro položku, která ví víc než konvence (externí odkaz, stránka za jinou cestou).
String jde přes `Str::is` proti `request()->path()` **i** proti jménu routy, což
je tentýž pár, na který se ptá zbytek frameworku. Closure dostane `ActiveNavigation`.

**Co se tím maže:** `nav-item.blade.php:33–36` — tři `@php` řádky a jedna
`collect()->contains()` v šabloně. Sidebar objekt postaví jednou (vedle `zone`
a `activeKey`, tam, kde se dnes čte zóna) a pošle ho dolů.

**A jedno zpřesnění, které z toho padne zadarmo:** `aria-current="page"` jen při
`isExactly()`, jinak `aria-current="true"`. Řádek resource na edit stránce je
„jsem tady uvnitř", ne „tohle je ta stránka" — a §3 tu druhou roli obsadí.

Dotčené soubory: `Zone.php`, nový `ActiveNavigation.php`, `NavigationItem.php`,
`Sidebar.php`, `partials/nav-item.blade.php`.
Bez BC breaku: pravidlo 2 je dnešek, pravidlo 3 je dnešek plus normalizace,
`activeWhen()` je nová volitelná věta.

### Co skutečně vzniklo (2026-09-11)

| Soubor | Co |
|---|---|
| `packages/core/src/Foundation/Routing/Zone.php` | `currentPage()`, `pageOf()`; třetí skupina v jediném ukotveném vzoru |
| `packages/core/src/Core/Resources/Navigation/ActiveNavigation.php` | nový vlastník — `current()`, `withKey()`, `isActive()`, `isExactly()`, `hasActiveChild()`, `ariaCurrent()`, `matchesPatterns()` |
| `packages/core/src/Core/Resources/Navigation/NavigationItem.php` | `activeWhen()`, `isActiveWhen()` (trojhodnotové) |
| `packages/admin/src/View/Sidebar.php` | `active()` — **protected**, jinak by `InvokableComponentVariable` zastínil objekt v šabloně |
| `packages/admin/resources/views/partials/nav-item.blade.php` | čtyři `@php` řádky pryč; `aria-current` z `ariaCurrent()` |

Dvě věci, které měření přidalo proti plánu:

1. **Relativní vs. absolutní URL.** `ResolvesPageUrls` odpovídá `route()`, tedy
   absolutně; ruční položka je `->url('/settings')`. Porovnání syrových řetězců
   je prohlásilo za různé na stránce, na kterou obě míří. `url()->to()` obě
   strany srovná do jednoho tvaru.
2. **Rozbalovací tlačítko není stránka.** Řádek s potomky se vykresluje jako
   `<button>`; `aria-current="page"` na něm tvrdí, že stisknutí vede tam, kde
   už stojíte. Nese tedy `true` a `page` říká ten potomek, který sem opravdu
   odkazuje. Chytil to až ovladač, na skutečném menu workbenche.

---

## 3. Část A — sub-navigace záznamu

Dnes se z edit stránky nedá přejít na view stránku jinak než zpátky přes seznam.
Záložky nad obsahem stránky to řeší, a **seznam záložek už je deklarovaný** —
`pages()`.

### 3a. Co se čím stane

```php
// resource, beze změny:
public static function pages(): array
{
    return [
        'index'  => ListInvoices::class,
        'create' => CreateInvoice::class,
        'view'   => ViewInvoice::class,
        'edit'   => EditInvoice::class,
        'history'=> RoutePage::make(InvoiceHistory::class)
            ->uri('{record}/history')
            ->icon('outline:clock')
            ->permission('invoices.audit'),
    ];
}
```

Záložky jsou stránky, které berou záznam: `SHAPES[$kind]['record']`, u vlastní
stránky `str_contains($uri, '{record}')`. `index` a `create` tedy nikdy.

`RoutePage` dostane **label, ikonu a pořadí z kanonických concernů**
(`HasName`, `HasLabel`, `HasIcon`, `HasSortOrder`) — ne vlastní vlastnosti, což
je přesně ten rozchod, který u `permission` už jednou nastal (§1b). Jméno je
klíč z `pages()`, takže `HasLabel` spadne na `Str::headline('history')`; čtyři
známé druhy mají překlad (`wire-panels::messages.page_kind.{view,edit,…}`).
Holý `pages()` bez jediného `RoutePage` tedy funguje a je pojmenovaný.

### 3b. Kdo to staví

Nový concern `packages/panels/src/Resources/Concerns/LinksToRecordPages.php`
(skládá ho `BelongsToResource`), vracející **`NavigationItem[]`** — tentýž tvar,
v jakém už stránka vrací drobečky. To není úspora, to je ta správná odpověď:
„label plus URL plus ikona plus viditelnost" má v tomhle repu jednoho vlastníka.

```php
/** @return array<int, NavigationItem> */
public function subNavigation(): array;
```

- URL: existující `pageUrl($kind, $record)`;
- viditelnost: `Gate::allows(RoutePage::getPermission())`, když je deklarované;
- aktivní záložka: `$this->currentPage` — **public property naplněná v
  `mountBelongsToResource()`** vedle `$breadcrumbZone` (ADR 0027);
- pořadí: `sort()`, jinak pořadí v `pages()`.

### 3c. Kde se to vykreslí

`packages/panels/resources/views/pages/partials/sub-nav.blade.php`, vložený z
`partials/header.blade.php` pod titulek. Jeden partial, protože stránky jsou
čtyři a dvě kopie záložek se rozejdou.

Vodorovný pruh podtržených záložek; aktivní nese `aria-current="page"`; přetečení
na telefonu **roluje vodorovně uvnitř sebe**, nezalamuje se (`overflow-x-auto`,
`scroll-snap`), protože zalomená druhá řada záložek posune celou stránku.

### 3d. Pasti

1. **Míň než dvě záložky = nevykreslí se nic.** Stejné pravidlo, jaké už mají
   drobečky (`Breadcrumbs::shouldRender()` při jedné stopě): jedna záložka je
   nadpis napsaný podruhé.
2. **Create a index nemají záznam.** Sub-navigace se tam nevykresluje vůbec —
   ne prázdný pruh, ne záložky bez odkazu.
3. **`{record}` v URL může chybět.** `pageUrl()` vrací null (resource na doméně
   s parametrem, nerealizovaná routa). Záložka bez URL se **vynechá**, ne
   nakreslí mrtvá — na rozdíl od menu, kde nerozsvícený řádek nese informaci
   „registrováno, nerouteno". Tady je to jen rozbitá záložka.
4. **Dvě `aria-current="page"`.** Řeší krok 0: řádek v menu klesne na `true`.
5. **Drobečky to nenahrazuje ani nezdvojuje.** Drobeček je cesta nahoru,
   záložka je pohyb do strany. Obojí nad sebou je v pořádku — ale titulek stránky
   mezi nimi musí zůstat, jinak je záhlaví tři řádky odkazů.
6. **`wire:navigate` na záložkách**, jinak se při přepnutí přenačte celý shell.

### Co skutečně vzniklo (2026-09-11)

| Soubor | Co |
|---|---|
| `packages/core/src/Foundation/Routing/RoutePage.php` | `HasName`/`HasLabel`/`HasIcon`/`HasSortOrder` + `EvaluatesClosures`; jméno je prázdné, protože klíč z `pages()` objekt nevidí — a proto pojmenovává čtenář |
| `packages/panels/src/Routing/ResourceRoutes.php` | `uriFor()`, `takesRecord()`; `SHAPES` ztratil sloupec `record`, který lhal, jakmile stránka přebila `uri()` |
| `packages/panels/src/Resources/Navigation/RecordPages.php` | nový — které stránky jsou o jednom záznamu, jak se jmenují, v jakém pořadí |
| `packages/panels/src/Resources/Concerns/LinksToRecordPages.php` | nový trait — `$currentPage` (public, ADR 0027) a `subNavigation()` |
| `packages/panels/resources/views/pages/partials/sub-nav.blade.php` | pruh záložek, vložený z `partials/header.blade.php` |
| `packages/panels/lang/{en,cs}/messages.php` | `page_kind.view`, `page_kind.edit`, `record_pages` |
| `ViewPage` / `EditPage` | skládají trait; záznam se resolvuje **jednou** a předává (jinak dotaz na záložku) |
| `workbench/app/Livewire/Resources/InvoiceHistory.php` + view | vlastní stránka záznamu — fixture pro ovladač a živý příklad z docs |

Měření proti běžícímu preview serveru opravilo zadání jednou: demo uživatel
workbenche **nemá** `invoices.update`, takže `edit` záložka správně vypadává a
zbývá jedna — a jedna se nekreslí. Fixture `view` + `edit` by tedy o záložkách
nedokázala nic; proto `history`, která je zároveň tím dokumentovaným případem
„vlastní stránka záznamu".

---

## 4. Část B — vodorovné (top) menu

Druhý tvar shellu, volba aplikace.

### 4a. Konfigurace

```php
// packages/admin/config/wire-admin.php — blok `layout`, tentýž, který zakládá
// admin-chrome-placement.md. Přistane-li ten plán dřív, je tohle třetí klíč.
'layout' => [
    /*
     * Tvar hlavní navigace: 'sidebar' (sloupec vlevo, dnešek) nebo 'top'
     * (vodorovný pruh pod hlavičkou). Pod `lg` je v obou případech šuplík —
     * vodorovný pruh je tvar pro širokou obrazovku, ne pro telefon.
     */
    'navigation' => env('WIRE_ADMIN_NAVIGATION', 'sidebar'),
],
```

Enum `NyonCode\WireAdmin\Enums\NavigationShape` (`Sidebar`, `Top`); překlep
skončí výjimkou při vykreslení, ne menu, které se někam ztratilo. Přebití na
komponentě: `<x-wire-admin::layout navigation="top">`.

### 4b. Co vznikne

```text
packages/admin/src/View/TopNav.php                        # zrcadlo Sidebar.php: čte Workspace, zónu a klíč jednou
packages/admin/resources/views/top-nav.blade.php
packages/admin/resources/views/partials/top-nav-item.blade.php
```

`TopNav` čte **tentýž** `Workspace::navigation($zone, $linkedOnly)`. Sémantika
(label, ikona, badge, aktivní stav, viditelnost) je celá z kroku 0 a z
`NavigationItem` — vlastní je jen vykreslení, což je přesně ta čára, kterou
CLAUDE.md drží: sdílená sémantika, per-povrch vykreslování.

| Ve sloupci | V pruhu |
|---|---|
| skupina = nadpis nad seznamem | skupina = **rozbalovací tlačítko** se jménem skupiny |
| položka bez skupiny = řádek | odkaz přímo v pruhu |
| potomci = složený seznam pod rodičem | **odsazený seznam uvnitř téhož panelu**, ne druhý flyout |
| co se nevejde = roluje se svisle | co se nevejde = panel **„Další"** na konci pruhu |

Panel je `wireFlyout({ placement: 'bottom-start' })` — existující primitiv,
teleport do `<body>`, stejný prstenec a stín jako každý jiný plovoucí panel.

### 4c. Pasti

1. **Přetečení je ta těžká část.** Pruh s patnácti položkami se **nesmí
   zalomit** (druhá řada posune stránku o 40 px při každém načtení). Mechanismus:
   pruh je `overflow-hidden` už ve statickém dokumentu; po `alpine:init` změří
   `ResizeObserver` šířky a co se nevejde, přesune do panelu „Další" — přesune,
   ne zkopíruje, takže v dokumentu nevzniknou dva stejné řádky. Před prvním
   měřením není vidět nic navíc, jen ořez — to je ta bezpečná chyba.
2. **Na telefonu zůstává šuplík.** `<aside>` se vykresluje dál a nad `lg` se
   celý skryje. To znamená **dvě navigace v jednom dokumentu** — proto má pruh
   vlastní jména (`admin-topnav-item`, `admin-topnav-group`, `admin-topnav-more`).
   Sdílet `admin-nav-item` je přesně ta chyba, před kterou varuje `sidebar.blade.php:11–15`.
3. **Rail partial se vkládá dál.** Je vlastníkem pravidla
   `[data-rail-only] { display: none; }` — bez něj vyskočí badge tečky vedle
   pilulek. Jen se u tvaru `top` nikdy nezapne a **sbalovací tlačítko ani `⌘B`
   se nevykreslí** (v pruhu není co sbalovat).
4. **Bez stromu po straně je titulek osamocený.** Vodorovné menu neukazuje, kde
   v hierarchii člověk je — proto se část A doporučuje dodat **dřív**, ne potom.
5. **Skupina, která má jediný prvek**, nemá být tlačítko s panelem o jedné
   položce. Vykreslí se jako přímý odkaz — stejná úvaha jako tooltip vs. popover
   v railu.
6. **Hloubka.** Skupina → položka → potomek jsou v panelu dvě úrovně. Třetí se
   nekreslí, přesně jak to `NavigationItem::children()` už má napsané.

---

## 5. Část C — filtr, oblíbené, nedávné

Tři věci, tři různé váhy. **C1 je klientská a levná, C2 potřebuje úložiště a
jednu Livewire komponentu.** Dodávají se odděleně.

### 5a. C1 — filtr v menu

Textové pole nad seznamem skupin; skrývá řádky, jejichž label neodpovídá.

```php
'navigation' => [
    // 'auto' = pole se objeví od `filter_threshold` položek výš
    'filter' => env('WIRE_ADMIN_NAV_FILTER', 'auto'),   // auto | always | never
    'filter_threshold' => 12,
],
```

- `/` fokusuje pole (odmítnuto, když je kurzor v jiném poli — stejné pravidlo
  jako `⌘B`), Escape maže a vrací fokus do seznamu;
- **skládané skupiny se při aktivním filtru vykreslí otevřené** (`x-show="open || filtering || $store.wireAdmin?.railed"`).
  Bez toho shoda uvnitř složené skupiny prostě není vidět, což vypadá jako
  „nic nenalezeno";
- pole je `data-rail-hide` — v 64pixelovém railu není co filtrovat, tam je
  odpovědí ⌘K;
- `aria-live="polite"` s počtem shod a řádek „nic neodpovídá";
- **maže se na `livewire:navigated`** — filtr, který přežije přechod, je menu,
  ze kterého polovina zmizela a nikdo neví proč.

**Co to není:** druhý vyhledávač. ⌘K hledá napříč záznamy, příkazy i menu;
tohle zužuje **prohlížený** seznam a nechává strukturu vidět. Musí to tak stát
i v docs, jinak je to zdvojená funkce.

### 5b. C2 — oblíbené a nedávné

Úložiště: **`PreferenceDriver`**, ne nové. Surface klíč `wire-admin.navigation`,
dimenze zóny v klíči (`…:business`), pytlík:

```php
['pinned' => ['invoices', 'orders'], 'recent' => ['customers', 'invoices']]
```

Vlastník: `Core\Resources\Navigation\NavigationMemory` (L1 — jmenuje
`NavigationItem`; driver je L0, takže směr sedí). Umí `pinned(zone)`,
`pin/unpin(key, zone)`, `recent(zone)`, `remember(key, zone)`, a
`stores(): bool` — poslední jmenovaná je ta důležitá.

**Připínání se nesmí nabízet, když se nic neukládá.** Výchozí driver je `null`
(§1d), takže v aplikaci, která o preferencích nic neřekla, by špendlík byl
tlačítko, které tiše nic nedělá. `stores()` je `! $driver instanceof NullPreferenceDriver`,
a bez něj se špendlíky ani sekce nevykreslí. Docs řeknou, který klíč to zapíná.

**Vykreslení.** Sidebar je Blade, připnutí potřebuje round trip → jediná malá
Livewire komponenta `wire-admin-nav-pins`, která vykresluje **jen sekci
Oblíbené a Nedávné** v hlavičce navigace. Špendlík na běžném řádku je obyčejné
tlačítko, které vyšle prohlížečovou událost; komponenta ji poslouchá, zapíše
přes `NavigationMemory` a překreslí sebe. Zbytek menu zůstává Blade a překreslí
se při příštím načtení stránky — což je v pořádku, protože originál řádku
zůstává ve své skupině.

**Připnutá položka je nahoře kopií, ne přesunem.** Přesun by znamenal, že se na
tom musí shodnout Livewire sekce a Blade zbytek v jednom renderu. Kopie má
vlastní jména (`admin-nav-pinned-item`), aby testy a ovladače dál počítaly
správně.

**Nedávné** zapisuje posluchač na `Hook::PageMounting`, registrovaný
`wire-admin`em. Strop 5, aktuální stránka se do seznamu nekreslí (je to řádek,
který vede tam, kde stojím), připnuté se v nedávných neopakují. Stránka, která
hook nedispatchuje, se nezapamatuje — viditelné, ne tiché.

### 5c. Pasti

1. **Připnutý klíč, který zmizel** (resource odinstalován, skryt, v jiné zóně):
   `NavigationMemory` protíná uložené klíče s tím, co `Workspace` právě vrátil.
   Nikdy se nekreslí z uloženého seznamu.
2. **Guest.** Výchozí `guest` driver je `session` — připnutí přežije do konce
   session a pak zmizí. To je správné chování, ale musí být v docs.
3. **Zóna.** Menu je v každé zóně stejné, ale připnutí ne — `invoices`
   připnuté v `admin` nepatří do `business`. Proto je zóna v klíči.
4. **Rail.** Sekce Oblíbené má v railu stejný problém jako nadpis skupiny:
   `data-rail-hide` na nadpisu, řádky zůstanou (jsou to ikony), oddělovač
   stejným slovníkem jako mezi skupinami.
5. **Zápis při GET renderu.** Nedávné se **nesmí** zapisovat z view komponenty
   sidebaru. `page.mounting` je vlastník; sidebar jen čte.

---

## 6. Část D — klávesnice a přístupnost

Dvě vady a jedno rozšíření. Vady napřed, jsou levné.

### 6a. Vady

1. **Šuplík nedrží fokus.** Otevřený šuplík dnes nechá fokus na tlačítku v
   hlavičce a Tab chodí po stránce za ztmavením. Oprava: při otevření fokus na
   první řádek menu, Tab je uvnitř šuplíku uzavřený, při zavření (Escape, klik
   do ztmavení, výběr položky) se fokus vrací na hamburger. Tohle je ta část,
   kterou uvidí jen ovladač — Pest vidí markup, ne kde je fokus.
2. **`<main>` nemá `tabindex="-1"`.** Skip link tak v části prohlížečů fokus
   nepřesune. Jeden atribut na `layout.blade.php:378`.

### 6b. Pohyb v menu

`role="menu"` se **vědomě nepoužije.** Navigace je seznam odkazů; přepnutí na
menu roli sebere čtečce slovo „odkaz" a zavede model, který v navigaci nikdo
nečeká (WAI-ARIA APG má pro navigaci seznam odkazů, ne menubar).

Proto ani **roving tabindex**: řádky zůstanou všechny v tab pořadí (jinak Tab
menu přeskočí a to je horší než to, co se opravuje). Přidá se jen pohyb:

- ↑/↓ posun fokusu po viditelných řádcích (přes skupiny, ne uvnitř jedné);
- Home/End na první a poslední;
- →/← na řádku s potomky rozbalí/složí (v railu otevře/zavře popover);
- psaní písmen skočí na první řádek, který jím začíná (type-ahead, okno 500 ms);
- ↓ z filtračního pole (C1) vstoupí do seznamu — to je ta věta, která z filtru
  dělá ovladatelnou věc a ne jen textové pole.

Ovladač jménem `wireNavKeys`, registrovaný na `<nav>`; `wire-admin` dnes
**nemá vlastní JS bundle**, takže buď vznikne (`packages/admin/resources/js/`
+ `Bundle::make()` podle ADR 0024), nebo to zůstane inline Alpine u store.
**Rozhodnutí: bundle.** Inline skript v layoutu už jeden je (store) a druhý,
tentokrát se smyčkami a type-ahead, je přesně ta hranice, za kterou ADR 0024
chce soubor a `@wireStackScripts`.

### 6c. Vodorovné menu (závisí na části B)

←/→ po pruhu, ↓ otevře panel a dá fokus prvnímu odkazu, Escape zavře a vrátí
fokus na tlačítko, Tab z otevřeného panelu ho zavře. To už **je** menu button
pattern a `aria-expanded`/`aria-controls` na něm platí — `wireFlyout` polovinu
z toho už umí (`nav-item.blade.php` to používá v railu).

### 6d. Než se cokoli přidá

Ověřit v ovladači, zda řádky mají viditelný fokus z výchozího obrysu prohlížeče
(§1e). Pokud ano, nepřidávat `focus-visible:` třídy jen pro jistotu; pokud ne,
přidat je jedním párem tříd na řádek a držet se slovníku, který snese nejnižší
podporovaný Tailwind (ADR 0005).

---

## 7. Pořadí a závislosti

| # | Krok | Závisí na | Proč tady |
|---|---|---|---|
| 0 ✅ | Vlastník aktivního stavu (§2) | — | **hotovo 2026-09-11** — tři ze čtyř částí se ho ptají; jinak vznikne počtvrté |
| 1 ✅ | A — sub-navigace záznamu (§3) | 0 | **hotovo 2026-09-11** — největší chybějící kus, nejmíň pohyblivých částí |
| 2 | D1 — šuplík a `<main>` (§6a) | — | dvě vady, ~20 řádků, nemá smysl je držet za featurami |
| 3 | C1 — filtr (§5a) | — | klientské, samostatné, hned viditelné |
| 4 | B — vodorovné menu (§4) | 0, doporučeně 1 | největší povrch; bez sub-navigace působí shell mělce |
| 5 | C2 — oblíbené a nedávné (§5b) | 0 | první krok, který zavádí úložiště a Livewire seam |
| 6 | D2 — pohyb v menu a v pruhu (§6b, §6c) | 3, 4 | type-ahead má smysl nad filtrem, klávesy v pruhu až když pruh je |

Každý krok je samostatně vratný a samostatně dodatelný. Kroky 1–6 nesahají na
`Workspace` ani na `NavigationGroup`.

---

## 8. Kritéria hotového

Platí pro každý krok zvlášť, ne až na konci.

**Testy.** Nové soubory, ne přírůstky do 324řádkového `SidebarTest`:

| Krok | Soubor | Co drží |
|---|---|---|
| 0 ✅ | `packages/core/tests/Unit/Core/Resources/ActiveNavigationTest.php` (14) | pravidla a jejich pořadí, relativní vs. absolutní URL, `activeWhen` přebíjí i zamítá, `isExactly` vs. `isActive`; plus dva testy v `SidebarTest` (`aria-current`, rozbalovací tlačítko) |
| 0 ✅ | `ZoneTest` | `pageOf()` na zónované i nezónované routě, `livewire.update` dál null — i pro druh stránky |
| 1 ✅ | `packages/panels/tests/Unit/RecordSubNavigationTest.php` (14) | jen stránky se záznamem; `permission` schová záložku; míň než dvě = nic; create/index = nic; aktivní podle druhu stránky, ne URL; překlad i lokalizace; deklarace se nemutuje |
| 3 | `packages/admin/tests/Unit/NavigationFilterTest.php` | práh `auto`, `always`, `never`; pole je `data-rail-hide` |
| 4 | `packages/admin/tests/Unit/TopNavTest.php` | `navigation => top`: pruh je v dokumentu, sloupec je jen šuplík; **počet** `[data-testid="admin-topnav-item"]` i `admin-nav-item` je přesně jeden na položku; sbalovací tlačítko ani `⌘B` se nevykreslí; neplatná hodnota vyhodí |
| 5 | `packages/core/tests/Unit/Core/Resources/NavigationMemoryTest.php` + `packages/admin/tests/Feature/NavigationPinsTest.php` | `stores()` je false nad null driverem a špendlíky se nevykreslí; připnutý klíč, který zmizel z `Workspace`, se nekreslí; nedávné bez aktuální stránky a bez připnutých; zóny se nemíchají |

**Ovladače** (`npm run verify:drivers`) — všechno, co jen prohlížeč vidí:

- `verify-workspace-nav.mjs`, `verify-admin-rail.mjs`, `verify-chrome-user.mjs`,
  `verify-spa-navigate.mjs` **zelené beze změny** ve výchozí konfiguraci;
- ✅ `verify-record-subnav.mjs` (25/25): záložky jsou stránky záznamu a jen ony,
  stránka bez oprávnění vypadne (a routa témuž prohlížeči vrátí 403), přepnutí
  přes `wire:navigate` posune označenou záložku, v menu nic netvrdí, že je
  aktuální stránkou, a seznam nemá pruh vůbec;
- nový `verify-topnav.mjs`: přetečení se po resize přesune do „Další" a **nikde
  není dvakrát**, panel se otevírá dolů, pod `lg` je šuplík a pruh není;
- nový `verify-nav-keys.mjs`: šuplík drží fokus a vrací ho; skip link doskočí do
  `<main>`; ↑/↓/Home/End/type-ahead; ↓ z filtru vstoupí do seznamu; ← → v pruhu;
- nový `verify-nav-memory.mjs`: připnutí se projeví bez přenačtení, přežije
  `wire:navigate`, nedávné se plní a neobsahuje aktuální stránku.

**Dokumentace** (EN i CS, strukturálně shodné — AI_DOCS_STANDARD.md):

- `docs/panels/pages.md` — sekce *Record Sub-Navigation*: `pages()` jako zdroj,
  `RoutePage` label/ikona/pořadí/permission, past „create nemá záznam";
- `docs/panels/navigation.md` — `activeWhen()` a pravidla aktivního stavu;
- `docs/admin/sidebar.md` — filtr (a **proč není druhé ⌘K**), oblíbené a
  nedávné včetně věty „bez nastaveného preference driveru se nenabízí";
- `docs/admin/layout.md` — `layout.navigation`, tabulka sloupec vs. pruh,
  past s telefonem;
- `docs/start/configuration.md` — nové klíče;
- gates: `npm run docs:check`, `docs:standard`, `docs:api`, `docs:examples`.

**Jména hooků:** `npm run hooks:names` po každém kroku, který přidá `@wireEl`
(`admin-nav-filter`, `admin-nav-pinned-item`, `admin-topnav-*`, `panels-sub-nav-*`).
Ledger smí růst, ne se zmenšovat.

**Pokrytí:** `composer coverage:verify` — každý přidaný řádek pokrytý. Podlahy
`admin` a `panels` jsou **100** a `core` 96 (`scripts/coverage-floors.json`),
takže tady není prostor pro „doplníme test potom".

---

## 9. Co to vědomě nedělá

- **Relation managery jako záložky.** Jsou vložené, ne routované
  (`pages/{view,edit}-page.blade.php`) — udělat z nich záložky znamená klientský
  stav místo URL, tedy jinou třídu rozhodnutí než §3, který jen odkazuje na
  routy, co už existují. Až bude, bude to vlastní plán.
- **Třetí úroveň zanoření.** `NavigationItem::children()` má napsáno proč, a
  vodorovný panel to nemění.
- **Per-uživatelskou volbu tvaru navigace** (sloupec vs. pruh). Mění layout,
  takže by se musela číst před prvním paintem jako rail — a to je stejná
  úvaha, kterou `admin-chrome-placement.md` §7 už jednou zamítl pro umístění.
- **Spodní lištu na telefonu.** Šuplík je hotová a funkční odpověď; druhá
  navigace na telefonu je třetí kopie stejných řádků.
- **Přetahování položek menu myší.** Pořadí vlastní aplikace přes `sort()`;
  per-uživatelské pořadí je připínání (§5b), ne druhý mechanismus.
- **Fluent `Panel` builder**, ve kterém by tyhle volby seděly jako metody.
  ADR 0028 §1b, a `Layout` to má napsané ve svém docblocku.
