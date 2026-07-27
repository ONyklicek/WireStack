---
title: Rollout výběrových gest — stav provedení
date: 2026-07-27
plan: architecture/plans/table-selection-gestures-rollout.md
status: Fáze I–VII hotové (kroky 0–27), zbývá krok 28 (docs)
---

# Stav provedení rolloutu

Jeden krok = jeden commit. Každý krok prošel bránou (composer test:table,
Integration, analyse, lint, CDP drivery; coverage při zásahu do `src/*.php`).

## Hotové kroky → commity

| Krok | Commit | Poznámka |
|---|---|---|
| 0 | `f66181a` + `b2b4303` | WIP aktivního řádku + pint fixup (docblock core→forms bez importu) |
| 1 | `2fd9ff7` | drift test `wire-table-records.js` |
| 2 | `91f855f` | GestureRow (40 řádků), 3 preview varianty, `SortablePreview->selectable()` |
| 3 | `eef2a68` + `4662ce5` | `verify-selection-gestures.mjs` (C1–C13) + oprava očekávání mobilního driveru |
| 4 | `6d2d235` | `onKey()` rezervovanou klávesu vyhazuje (`TableConfigurationException`) |
| 5 | `c0879a2` | rozšířený rezervovaný seznam (bez `backspace`), CHANGELOG |
| 6 | `fb5b845` | `usesGridSemantics()` vlastní „je to grid“; mount přišpendlen přes `mountsRecordActionController()` |
| 7 | `19eb53a` | mount controlleru na každém gridu; CHANGELOG (BC) |
| 8 | `be28ee4` | `matching` z `data-matching` getterem (morph-safe) |
| 9 | `b9fb825` | serverová normalizace `selection.mode` (jen korupce; validní tvary nechává) |
| 10 | `b92f502` | `toggleAll()` v all módu neinvertuje |
| 11 | `491efa2` | kanonika: stránkové gesto v all módu edituje výjimky, mód neopouští (otočeny 2 aserce v `CanSelectRecordsTest`) |
| 12 | `1394504` | extrakce `wireRecordSelection` → `dist/wire-table-selection.js`; inline fallback ze zdrojáku |
| 13 | `e194511` | `SelectionAssetTest` + `SelectionRenderTest` |
| 14 | `8fde063` | kotva/rozsahy do selection komponenty; `data-selection-version="1"`; stale view → hlasitý console.error |
| 15 | `be9e38a` | rozsahy nepřepisují `mode`; `selectPage` = union + guard v all módu (C9 otočeno) |
| 16 | `5ccb981` | `base ∪ blok` se snapshotem; invalidace: setAnchor/clearAnchor pár, `$watch('mode')`, MutationObserver jen při zmizení kotvy |
| 17 | `193444a` | `Shift`/`mod`/`mod`+`Shift` klik (additive flag v `selectRange`) |
| 18 | `8cdffa6` | `Home`/`End`, `PageUp`/`PageDown` (pitch × skutečně scrollující předek), `mod`+`Shift`+šipky, fix `mod`+`A` shiftKey |
| 19 | `e674790` | `Backspace` alias v JS matcheru, `Shift`+`F10` (+ `_menuFromKey` obrana, headless neověřitelná), fokus do menu a zpět |
| 20 | `eb51ae4` | `data-select-cell` (td, karta, oba poziční spacery) |
| 21 | `a71ef61` | `createAutoScroller` + `bodyRows`/`rowAtY` → `core/resources/js/support/`; core dist rebuild |
| 22 | `351aeff` | sweep v `record-actions.js` (arm→engage, capture click-kill, jen myš, additive, morph guard, reduced-motion) |
| 23 | `5efaab0` + `4aad86c` | `openOn:` ve 3 shellech + Htmlable objektech + View komponentách; preview `core-open-on`; `verify-modal-open-on.mjs` 14/14; hardening proti injekci do jména atributu |
| 24 | `fd368fa` | `ShortcutLabelFormatter` + `ShortcutHint` (core Foundation) + `TableShortcutLegend` (table Support) + `Table::shortcutLegend()`; i18n EN+CS |
| 25 | `c7f8f2a` | `?` otevírá nápovědu (`shortcut-help` + `shortcut-help-modal` partial, `kb.help` v controlleru); driver 62/62 |
| 25b | `b217ca8` + `2c05921` | teleport `wire:key` z `$id` (dva Modal shelly v jedné komponentě); + předexistující díra ve forms select-option modalech |
| 26 | `a20e61e` | ARIA grid (`aria-rowcount`/`aria-rowindex` přes celou sadu, `aria-multiselectable`, bindnuté `aria-selected`) + `aria-live` region; driver 70/70 |

| 27 | `efce0ab` | klikatelná plocha = celá buňka, `[data-select-cell]` v `INTERACTIVE`, marker + pruh (kontrast 4.3/4.79 light, 3.98/7.95 dark); fix Shift+klik na checkbox; driver 77/77 |

## Rozhodnutí učiněná při provádění (nad rámec plánu)

- **Krok 6/7 split:** interim vlastník mountu `Table::mountsRecordActionController()`;
  v kroku 7 rozšířen o `usesGridSemantics()` a zůstává jako trvalý vlastník.
- **Krok 9:** records se maže JEN při korekci neznámého módu. Validní keys↔all
  přechody jdou z klienta párově (mode+records) a wipe by zápis rozbil.
- **Krok 11:** kanonika „stránkové gesto v all módu edituje výjimky a mód
  neopouští“ — `selectAllRecords()`/`deselectPageRecords()` v all módu zůstávají
  v all; zúžení na stránku zůstává jen explicitní `selectOnlyPageRecords()`.
- **Krok 12:** `record-selection.js` je záměrně **bez importů** — asset partial
  ho při chybějícím bundlu inlinuje doslovně (`SelectionAssetTest` to hlídá).
- **Krok 22:** sweep proto žije v `record-actions.js` (bundluje se s importy
  z core supportu), ne ve `wireRecordSelection`; listenery pointerdown/click-kill
  jsou na selection rootu, move/up na dokumentu.
- **Trailing click po sweepu:** flag musí přežít déle než `setTimeout(0)` —
  click může přijít v pozdějším tasku; backstop je 150 ms, primárně one-shot
  clear v capture handleru.
- **Krok 23:** `openOn` se ctí JEN při `wireModel === null` (jediný vlastník
  `show`); detekce „bez bindingu“ na component path musí jít přes
  `WireDirective::value()` — chybějící `wire:model` vrací directive s value
  `false` a `filled(false)` je `true`. `x-on:{event}` je pozice **jména
  atributu**, kde Blade escapuje jen uvozovky → mezera by vložila nový atribut;
  proto whitelist `[a-zA-Z][a-zA-Z0-9_-]*` (nález background security review).
- **Krok 24:** `formatShortcutLabel()` v `HasKeyboardShortcut` deleguje na
  `ShortcutLabelFormatter` — mění to i label akcí v `dropdown-item.blade.php`
  a `header-action.blade.php` (šipky nově glyfy). `Foundation/ValueObjects/`
  byl nový adresář, ale `AI_CODING_STANDARD.md:165` ho předepisuje.
- **Krok 25:** jméno `openOn` eventu je `wire-table-shortcut-help-` + prvních
  12 znaků `md5($component->getId())`. Hash **musí** být lowercase: listener
  sedí v **jménu atributu** (`x-on:{event}.window`) a DOM jména atributů
  lowercasuje → syrové Livewire ID s velkými písmeny by se nikdy netrefilo
  (CDP to odhalilo: modal v DOM, `show` zůstal false). Per-komponentní jméno
  je nutné, aby `?` na stránce s více tabulkami neotevřel všechny nápovědy.
  Mac/non-Mac labely: server rendruje Ctrl variantu, `x-text` ji na Macu
  přepíše (platforma je klientský fakt) — headless Chrome na macOS hlásí Mac,
  takže driver musí očekávat obě sady.
- **Krok 25 → oprava `b217ca8`:** shelly měly `wire:key` teleportu natvrdo
  (`wire-modal-modal`). Nápověda to porušila: tabulka s formulářovou akcí
  rendruje **dva Modal shelly v jedné Livewire komponentě** a morph klíčuje
  podle `wire:key` → záměna obsahu. Klíč teď bere `$id`, když je zadané
  (bez `$id` beze změny); nápověda posílá jako `id` jméno svého eventu.

- **Krok 26:** `aria-rowindex` je pozice v CELÉ sadě, ne na stránce → nutné
  vyzvednout `$from`/`$to`/`$total` z patičky do preambule (patička se rendruje
  až po těle) a `$headerRowCount` musí započítat řádek column filtrů.
  `aria-selected` MUSÍ být binding (`:aria-selected`) — statická hodnota se
  morphem vrátí na serverovou pravdu. Live region: v DOM od prvního renderu
  a **prázdný** (region oznamuje jen ZMĚNY obsahu; naplněný při bootu neřekne
  nic a při bootu s předvybranými řádky by četl výběr, který uživatel neudělal)
  → `announceReady` se zapíná až prvním `$watch('selected'/'mode')`. Hlášky
  chodí hotové z PHP (překlad je serverová věc), čísla se dosazují v JS.

- **Krok 27:** marker je `::before` overlay na `[&>td:first-of-type]`, NE border.
  Důvody: (a) border posouvá obsah a rezervace transparentním borderem nejde —
  `border-transparent` je v CSS ZA `border-primary-600`, takže by vždy vyhrála
  bez ohledu na pořadí ve `class`; (b) overlay nastavuje vlastnost, kterou
  klidové řádky vůbec nenastavují → žádný souboj v kaskádě. **`first-of-type`,
  ne `first-child`** — prvním dítětem `<tr>` je teleport `<template>`
  kontextového menu (jinak selektor nesedí na nic a vypadá to jako chyba
  Tailwindu). Klikatelná plocha: handler JEN na `<td>`, button vlastní nemá
  (klik i Enter/Space z něj bublají) — dva handlery = dvojí toggle, a `.stop`
  na buttonu by zabil nastavení kotvy v controlleru.

## Objevené gotchas (platí i pro další kroky)

- Blade `x-data` atribut: dvojité uvozovky v JS komentáři ukousnou atribut
  (Alpine Expression Error) — komentáře v x-data bez `"`.
- `overflow-x-auto` wrapper má computed `overflow-y: auto` (CSS páruje osy) —
  „scrollující předek“ musí splnit i `scrollHeight > clientHeight`.
- Mid-sweep se objeví bulk bar a posune layout → CDP drag musí přeměřovat
  waypointy (driver `drag()` to dělá).
- Perf testy v table sadě umí flaknout pod zátěží stroje (1× za rollout);
  composer `process-timeout` 300 s zabije pest na vytíženém stroji — spustit
  `vendor/bin/pest --configuration packages/core/phpunit.xml` přímo.
- `verify-mobile-selection.mjs`: stacked-selection preview má předseedovaný
  výběr → první tap na strip odznačuje (spravené očekávání, 13/13).
- CDP checky počítající prvky (backdropy, markery) musí filtrovat na
  **vykreslené**, ne na přítomné v DOM: zavřený modal si backdrop v dokumentu
  nechává (`display:none`). `verify-nested-modal.mjs` na tom spadl, jakmile na
  stránku přibyla nápověda (`painted=1 of 2`).

## Stav sítě

- `verify-selection-gestures.mjs` — **77/77** (C1–C13 + myš, klávesnice, sweep,
  selection-only, reduced-motion, sortable koexistence, `?` nápověda, ARIA +
  live region, kontrast markeru, velikost cíle)
- `verify-record-active-row.mjs` 18/18, `verify-record-actions.mjs` 14/14,
  `verify-record-actions-dual.mjs` 5/5, `verify-mobile-selection.mjs` 13/13,
  `verify-fill-handle.mjs` 26/26, `verify-modal-open-on.mjs` 14/14
- PHP: table 1668, core 1751, forms 908, sortable 39, Integration 39;
  analyse + lint OK; coverage diff 100 %, floors OK

## Zbývá

- **28** docs EN+CZ, upgrade, boost guidelines, screenshoty (CHANGELOG se psal
  průběžně u každého kroku)
- Známý předexistující bug: `reorderBodyColumns()` je poziční bez offsetu
  selection buňky (selectable+sortable přeřazování sloupců) — vědomě odloženo,
  vyplave na `SortablePreview`.
