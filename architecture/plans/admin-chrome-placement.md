---
title: Umístění uživatelského menu a sbalovacího tlačítka v admin shellu
date: 2026-09-07
scope: packages/admin (layout, sidebar, config, lang, tests), docs/admin/overview.md + docs/cs, workbench/scripts
status: PLÁN — nezahájeno. Zadání vlastníka („roadmap 2.x"), měřeno proti stromu 2026-09-07
owner_request: |
  modul admin layout — volitelně nastavit uživatele vpravo nahoře nebo vlevo
  dole v menu; tlačítko pro sbalení nahoře, nebo dole pod uživatelem v menu
decision: |
  Volbu vlastní aplikace (config `wire-admin.layout`), ne přihlášený člověk.
  Per-uživatelská preference je vědomě mimo rozsah — viz §7.
adrs:
  - architecture/decisions/0028-optional-panel-shell.md   # §1b — sloty, ne fluent panel builder
  - architecture/decisions/0030-hook-surface.md           # PageChrome regiony (USER_MENU, TOPBAR)
parent: architecture/plans/v3-optional-admin-and-module-packages.md
---

# Umístění uživatelského menu a sbalovacího tlačítka

Dvě nezávislé volby, obě aplikace, obě s dnešním chováním jako výchozím:

| Volba | Hodnoty | Dnes |
|---|---|---|
| kde sedí účet | `topbar` (vpravo nahoře) · `sidebar` (vlevo dole, pod navigací) | napevno `topbar` |
| kde sedí sbalovací tlačítko | `topbar` · `sidebar` (úplně dole, **pod** účtem) | napevno `topbar` |

Kombinace jsou volné: účet dole a tlačítko nahoře je legitimní nastavení, stejně
jako obrácené. „Pod uživatelem" ze zadání je pořadí uvnitř patičky menu, ne
podmínka — když je účet v topbaru, je tlačítko v patičce samo.

---

## 1. Co měření našlo (proti stromu, ne proti plánům)

| Fakt | Kde | Co z toho plyne |
|---|---|---|
| Účet je napevno na konci `<header>` — trigger, avatar, jméno, dropdown, slot `$userMenu` i `PageChrome::USER_MENU` | `packages/admin/resources/views/layout.blade.php:283–328` | ~45 řádků markupu, které musí umět stát na dvou místech → **partial**, ne druhá kopie |
| Sbalovací tlačítko je napevno v `<header>`, včetně `⌘B` jako `x-on:keydown.window` **na tlačítku** | `layout.blade.php:178–202` | zkratka dnes visí na prvku, který se má stěhovat; §4 ji odsud odpojuje |
| `<aside>` končí `</nav>` — patička v menu **neexistuje** | `packages/admin/resources/views/sidebar.blade.php:65–170` | nový element, ne úprava stávajícího; `flex flex-col` + `flex-1` na navu ho drží dole zadarmo |
| Sidebar dostává jen `linkedOnly`, `zone`, `activeKey`; žádné sloty | `packages/admin/src/View/Sidebar.php:38–52` | slot `user` / `userMenu` z layoutu se musí do sidebaru **předat** — §3 |
| `config/wire-admin.php` má jediný klíč `brand` | `packages/admin/config/wire-admin.php` | `layout` je druhý klíč, ne přepis souboru |
| Rail je celý CSS nad `<html data-rail>`; slovník je `data-rail-hide`, `data-rail-only`, `data-rail-row` | `packages/admin/resources/views/partials/rail.blade.php` | patička dostane úzký tvar **stejným** slovníkem — žádný nový mechanismus, žádný Alpine před prvním paintem |
| `PageChrome::USER_MENU` je to, čím moduly (profil z users, odhlášení z auth) plní menu účtu | `packages/core/src/Foundation/View/PageChrome.php:60–78`, `layout.blade.php:325` | přesunuté menu **musí** renderovat tentýž registr, jinak přesun umístění tiše zahodí odhlášení |
| `<x-wire::dropdown position>` je volný string předaný Floating UI (`flip`, `shift`), panel se teleportuje do `<body>` | `packages/core/src/Foundation/View/Dropdown.php:20–30`, `packages/core/resources/js/dropdown.js:76–171` | `top-start` funguje; panel `w-56` kotvený v 64pixelovém railu se vysune doprava přes obsah a nic ho neořízne |
| `Placement` má čtyři případy, `origin-*` třídy v šabloně řeší jen dva | `packages/core/src/Foundation/Enums/Placement.php:18–21`, `foundation/dropdown.blade.php:49–50` | `top-start` je platná hodnota; dopadá jen původ scale-transformu, ne pozice |
| `ChromeTest` ověřuje **přítomnost** `admin-rail-toggle` v celém dokumentu | `packages/admin/tests/Unit/ChromeTest.php:124` | projde i tehdy, kdyby se tlačítko vykreslilo dvakrát — §5 přidává počet |
| Tři ovladače sahají na `[data-testid="admin-user"]` / `admin-rail-toggle` selektorem bez ohledu na místo | `workbench/scripts/verify-chrome-user.mjs:135,137`, `verify-profile.mjs:118`, `verify-admin-rail.mjs:104,109` | ve výchozím nastavení musí zůstat zelené beze změny; druhé umístění chce vlastní běh — §5 |

Dvě měření, která mění zadání:

1. **Menu účtu není markup, je to čtyři zdroje v jednom.** Slot `$user` (celý
   trigger si píše aplikace), fallback na `auth()->user()`, slot `$userMenu`
   a registr `USER_MENU`. Přesun umístění je proto přesun *partialu se čtyřmi
   vstupy*, ne přesun `<div>`u — a všechny čtyři musí fungovat na obou místech,
   jinak je druhé umístění tiše chudší.
2. **`⌘B` dnes patří tlačítku.** Window listener je zaregistrovaný na prvku,
   který se stěhuje — takže bez §4 by zkratka záležela na konfiguraci umístění,
   což je přesně ta vazba, kterou zkratka nemá mít.

---

## 2. Tvar konfigurace

```php
// packages/admin/config/wire-admin.php — druhý klíč vedle 'brand'
'layout' => [
    /*
     * Kde sedí účet přihlášeného člověka: 'topbar' (vpravo nahoře) nebo
     * 'sidebar' (vlevo dole, pod navigací). Menu je v obou případech totéž
     * — stejný slot, stejný registr, jen jiné místo a jiný směr otevírání.
     */
    'user' => env('WIRE_ADMIN_USER_MENU', 'topbar'),

    /*
     * Kde sedí tlačítko, které sbalí menu do railu: 'topbar' nebo 'sidebar'
     * (úplně dole, pod účtem, je-li tam). `⌘B` platí v obou případech.
     */
    'rail_toggle' => env('WIRE_ADMIN_RAIL_TOGGLE', 'topbar'),
],
```

Přebití na komponentě, pro aplikaci, která má víc než jeden shell:

```blade
<x-wire-admin::layout user-menu="sidebar" rail-toggle="sidebar">
```

**Proč config, a ne fluent builder.** ADR 0028 §1b odmítá třídu, která drží
konfiguraci shellu, a `Layout` to říká ve svém docblocku. Dvě enumerované
hodnoty ve `config/` tu čáru nepřekračují — nedrží je objekt, nedědí se, nikam
se nepropagují. Čára, která tady platí: **do `layout` patří jen to, co se nedá
napsat slotem.** Umístění se slotem napsat nedá; barvy, značka a obsah menu ano,
a proto tam nepatří.

Neplatná hodnota **není tichý fallback.** Layout ji přeloží enumem
`NyonCode\WireAdmin\Enums\ChromeSlot` (`Topbar`, `Sidebar`) a překlep skončí
výjimkou při vykreslení, ne menu, které se „někam ztratilo".

---

## 3. Co se kde vykreslí

Dva nové partialy — jedna definice, dvě místa vložení:

```text
packages/admin/resources/views/partials/user-menu.blade.php   # z layout.blade.php:283–328
packages/admin/resources/views/partials/rail-toggle.blade.php # z layout.blade.php:178–202
```

Oba dostanou `place` (`topbar` | `sidebar`) a liší se v tom jediném, v čem se
lišit musí:

| | topbar | sidebar |
|---|---|---|
| dropdown účtu | `position="bottom-end"` | `position="top-start"` — otevírá se **nahoru** |
| trigger účtu | avatar + jméno + šipka dolů | avatar + jméno + šipka nahoru, `data-rail-row`, jméno v `data-rail-hide` |
| sbalovací tlačítko | `hidden lg:inline-flex` v hlavičce | totéž, plus `data-rail-row`; text „Sbalit menu" jako `data-rail-hide` label vedle ikony |

Předání slotů do menu (layout je dnes self-closing `<x-wire-admin::sidebar />`):

```blade
<x-wire-admin::sidebar :linked-only="$linkedOnly" :user-menu="$userMenu ?? null" :user="$user ?? null" />
```

`Sidebar` je použitelný i sám o sobě (docs § *The Sidebar On Its Own*), takže obě
hodnoty jsou `null`-ovatelné a partial spadne na `auth()->user()` úplně stejně
jako dnes layout. **Registr `USER_MENU` čte partial sám**, ne layout — to je to,
co zaručí, že odhlášení nezmizí přesunem umístění.

Patička menu vznikne v `sidebar.blade.php` až za `</nav>`:

```blade
@if ($userPlace->isSidebar() || $railTogglePlace->isSidebar())
    <div data-testid="admin-sidebar-footer" class="shrink-0 border-t border-gray-200 p-2 dark:border-gray-800">
        @if ($userPlace->isSidebar())    @include('wire-admin::partials.user-menu', ['place' => 'sidebar'])   @endif
        @if ($railTogglePlace->isSidebar()) @include('wire-admin::partials.rail-toggle', ['place' => 'sidebar']) @endif
    </div>
@endif
```

Patička se nevykreslí vůbec, když ji nic neplní — prázdný `border-t` nad
posledním řádkem menu je viditelná chyba.

---

## 4. Pasti

1. **`⌘B` se musí odpojit od tlačítka.** Dnes `x-on:keydown.window` sedí na
   prvku v hlavičce (`layout.blade.php:182–188`). Přesune-li se s ním, závisí
   zkratka na konfiguraci; zůstane-li v obou partialech, mají dokumenty s oběma
   umístěními dva posluchače na jeden chord. Řešení: listener patří **jednou**,
   na `<body>` v layoutu, vedle store — kde ho žádné umístění nevidí.
2. **Jeden `data-testid` na dokument.** `sidebar.blade.php:11–15` už tuhle lekci
   má napsanou o dvou kopiích menu: „každý test a ovladač, který počítá položky,
   by tiše počítal dvojnásobek". Invariant: právě jeden `[data-testid="admin-user"]`
   a právě jeden `[data-testid="admin-rail-toggle"]` v dokumentu, ať config říká
   cokoli — a je to **aserce na počet**, ne na přítomnost (§5).
3. **Telefon nemá rail, ale má šuplík.** `<aside>` je pod `lg` vysunovací šuplík.
   Účet v patičce menu tedy na telefonu znamená účet **za klepnutím na hamburger**
   — topbar zůstane bez toho, kdo je přihlášený. To je legitimní volba (tak to
   dělá řada shellů), ale musí být vědomá a napsaná v docs; sbalovací tlačítko
   tenhle problém nemá, protože je pod `lg` stejně skryté.
4. **Dropdown v 64pixelovém railu.** Panel `w-56` se teleportuje do `<body>` a
   Floating UI ho odklopí přes obsah — funguje, ale trigger v railu musí být
   `data-rail-row` (vycentrovaný, bez inline paddingu), jinak sedí účet o 16
   pixelů vedle osy, na které stojí všechny ikony nad ním. Přesně ta chyba, kterou
   `brand.blade.php` popisuje u loga.
5. **Patička nesmí ukrojit navigaci.** `nav` má `flex-1 overflow-y-auto`, takže
   patička musí být `shrink-0` — jinak se při dlouhém menu smrskne místo toho, aby
   nav odroloval.
6. **Cestou k opravě: tlačítko má `title` dvakrát.** `x-bind:title` (`:191–193`)
   a hned pod ním statické `title="{{ collapse_menu }}"` (`:195`) — takže než
   Alpine naváže, nabízí sbalený rail „Sbalit menu". Extrakce partialu je jediná
   chvíle, kdy se to opravuje zadarmo; statický `title` pryč, `sr-only` páry
   nesou přístupné jméno pro obě stavy už teď.
7. **Slot `$user` si píše aplikace celý.** Aplikace, která si předá vlastní
   trigger, dostane svůj markup vlevo dole v 64pixelovém sloupci a nemá jak vědět,
   že je v railu. Docs to musí říct: kdo si píše vlastní trigger a chce
   `user => 'sidebar'`, píše si i jeho úzký tvar (`data-rail-hide` / `data-rail-only`).

---

## 5. Kritéria hotového

**Testy** (`packages/admin/tests/Unit/`, nový `ChromePlacementTest.php`):

- výchozí config vykreslí účet i tlačítko v `<header>` — dnešní chování, doslova;
- `user => 'sidebar'` je vykreslí v `[data-testid="admin-sidebar-footer"]` a **ne**
  v hlavičce;
- v obou umístěních obsahuje menu účtu obsah slotu `userMenu` **i** view
  registrované přes `PageChrome::USER_MENU` (to je aserce, která hlídá past č. 1
  z §1 — přesun nesmí zahodit odhlášení);
- počet `[data-testid="admin-user"]` a `[data-testid="admin-rail-toggle"]` je právě
  jeden v každé ze čtyř kombinací;
- `⌘B` handler je v dokumentu právě jednou, ve všech čtyřech kombinacích;
- neplatná hodnota configu vyhodí, nespadne tiše na `topbar`;
- patička se nevykreslí, když do ní nic nepatří.

**Ovladače** (`npm run verify:drivers`):

- `verify-chrome-user.mjs`, `verify-profile.mjs`, `verify-admin-rail.mjs` zelené
  **beze změny** — výchozí nastavení je dnešek;
- nový `verify-admin-placement.mjs` nad preview s `user => 'sidebar'`,
  `rail_toggle => 'sidebar'`: menu se otevírá nahoru a je celé vidět, v railu je
  trigger vycentrovaný a panel se vysune přes obsah, sbalení funguje z patičky a
  `⌘B` funguje taky, a účet je dosažitelný ze šuplíku na šířce telefonu.

**Dokumentace:** `docs/admin/overview.md` a `docs/cs/core/admin-shell.md`
strukturálně shodné (AI_DOCS_STANDARD.md) — nová sekce mezi *Who Is Signed In*
a *Slots*, tabulka kombinací, past č. 3 (telefon) a č. 7 (vlastní trigger)
napsané, ne zamlčené; `docs/start/configuration.md` dostane klíč `layout`.
`npm run docs:check`, `docs:standard`, `docs:api`.

**Pokrytí:** `composer coverage:verify` — každý přidaný řádek pokrytý, žádný pád
podlahy `packages/admin`.

---

## 6. Odhad

| Krok | Rozsah |
|---|---|
| enum + config + `Layout`/`Sidebar` props | ~80 řádků |
| extrakce dvou partialů (přesun, ne přepis) | ~50 řádků přesunutých, ~20 nových |
| patička v `sidebar.blade.php` | ~25 řádků |
| odpojení `⌘B` od tlačítka | ~10 řádků |
| testy | ~140 řádků |
| ovladač | ~90 řádků |
| docs EN + CS + configuration.md | ~120 řádků |

Bez BC breaku: výchozí hodnoty jsou dnešní chování, `<x-wire-admin::sidebar />`
zůstává platné self-closing i bez slotů.

---

## 7. Co to vědomě nedělá

- **Per-uživatelskou preferenci** (volba jako téma a rail, v localStorage,
  přepínatelná z menu účtu). Vlastník zvolil config; kdyby to jednou mělo
  přijít, tvar je hotový — `partials/rail.blade.php` je přesně ten vzor, a
  umístění je stejná třída rozhodnutí jako šířka menu: mění layout, takže se
  musí přečíst před prvním paintem, ne z Alpine store.
- **Stěhování ostatního chrome** (přepínač tématu, zvonek, paleta). Zadání mluví
  o dvou prvcích. Zobecňovat teď na „libovolný prvek chrome kamkoli" znamená
  vymýšlet druhého konzumenta — pravidlo z `v2-progress.md` §5: extrakci
  ospravedlňuje až druhý skutečný konzument.
- **Vodorovné menu / topbar navigaci.** Jiná featura, jiný layout, není v zadání.
