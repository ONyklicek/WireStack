---
title: Implementační plán — výběr a klávesové zkratky tabulky
date: 2026-07-26
scope: packages/table, packages/core (2 seamy), packages/sortable (1 nález), workbench
status: ověřeno čtyřmi nezávislými sondami, připraveno k provedení
parent: architecture/plans/table-selection-gestures.md
analysis: architecture/plans/table-selection-gestures-implementation.md
---

# Implementační plán

Plán drží **co** (`table-selection-gestures.md`), analýza **proč tak**
(`…-implementation.md`), tenhle dokument **v jakém pořadí, čím se to ověří a jak
se to vrátí zpátky**.

Všechna fakta níže jsou ověřená proti kódu. Kde něco ověřit nešlo, je to
označené.

## Tři pravidla, ze kterých je plán odvozený

1. **Nejdřív síť, potom kód.** Coverage gate nevidí ani JS, ani Blade — diff
   i floors filtrují `packages/*/src/*.php` (`scripts/verify-coverage.php:117`,
   `:79`) a JS test runner v repu není. Pest na `index.blade.php` sáhne, ale vidí
   z něj zhruba pětinu; `selection.js` neuvidí vůbec. Bez CDP driveru, který
   popisuje výchozí chování, je refaktor slepý.
2. **Nikdy nemíchat přesun se změnou chování.** Extrakce je vlastní fáze a její
   kritérium je, že se nezmění žádná aserce popisující *chování*.
3. **Každý krok končí zeleně a jde vydat.** Rollback je vždy revert jednoho
   commitu.

---

## Přehled kroků

| # | Krok | Blokuje | Riziko |
|---|---|---|---|
| 0 | Dokončit a zacommitovat rozpracovanou práci na aktivním řádku | vše | — |
| 1 | Drift test na `wire-table-records.js` + 4. driver do brány | 3 | nízké |
| 2 | Preview infrastruktura (3 varianty, 40 řádků) | 3, 7 | nulové |
| 3 | CDP driver — charakterizace výchozího chování | 12 | nulové |
| 4 | `onKey()` rezervovanou klávesu odmítne hlasitě | 5 | nízké |
| 5 | Rozšířit rezervovaný seznam | 18 | BC |
| 6 | Grid semantika — role, tabindex, ARIA | 18, 25, 26 | **BC, viditelné** |
| 7 | Grid semantika — mount pointer controlleru | — | **BC, viditelné** |
| 8 | `matching` přestane být zapečené | — | oprava chyby |
| 9 | Serverová normalizace `selection.mode` | 15 | nízké |
| 10 | Oprava `toggleAll()` v `all` módu | 12 | **oprava vážné chyby** |
| 11 | Sjednotit sémantiku „vybrat stránku" | 15 | střední |
| 12 | Extrakce `wireRecordSelection` | 14, 16 | **nejvyšší** |
| 13 | Testy po extrakci | — | — |
| 14 | Přesun kotvy + verzovací značka markupu | 15, 16 | střední |
| 15 | `selectRange`/`selectPage` přestanou přepisovat `mode` | 16 | střední |
| 16 | `base ∪ rozsah` | 17, 18 | střední |
| 17 | Myš: `Shift`/`mod`/`mod`+`Shift` klik | — | nízké |
| 18 | Klávesnice | — | nízké |
| 19 | `Backspace`, `Shift`+`F10`, fokus v kontextovém menu | — | nízké |
| 20 | `[data-select-cell]` do markupu | 22, 27 | nulové |
| 21 | Promoce `autoscroll` + `rowAtY` do core `support/` | 22 | cross-package |
| 22 | Sweep | — | vysoké |
| 23 | Core seam: modal otevíratelný z JS | 25 | cross-package |
| 24 | Legenda zkratek — tři vrstvy vlastnictví | 25 | nízké |
| 25 | Nápověda `?` | — | nízké |
| 26 | ARIA, `aria-live`, refaktor `$from` | — | střední |
| 27 | Nebarevný marker, klikatelná plocha, `INTERACTIVE` | — | střední |
| 28 | Docs, CHANGELOG, upgrade guide, i18n, boost, screenshoty | — | — |

---

## 0 — Dokončit a zacommitovat práci na aktivním řádku

Tvrdý předpoklad, ne administrativa.

Celý plán popisuje „výchozí" chování, jenže to v HEAD (`74da9c8`) neexistuje.
Necommitnutými přírůstky jsou `anchorFor()` (`record-actions.js:227-247`), guard
fokusu `:141`, `dialogOpen()` `:148`, `markActive`, `rowClass`, `rowTabindex`
i pravidlo „klik na checkbox nastaví kotvu" (`onPointer:399-404`). V HEAD nastavuje
`moveActive` kotvu natvrdo na výchozí řádek, takže `Shift`+`↓` po `mod`+`A` výběr
**zhroutí z N na 2**, nezmenší ho o jedna.

Untracked je i `workbench/scripts/verify-record-active-row.mjs` a odstavec
`docs/table/record-actions.md:112-118`, který dokumentuje dnešní sémantiku.

Baseline je zelený — 1606 table / 1704 core / 39 sortable testů, driver 18/18 —
ale zelený je **rozpracovaný strom**.

**Po commitu přepočítat souřadnice v tomto dokumentu i v analýze.**

---

## Fáze I — síť (kroky 1–3, žádná produkční změna)

### 1 — Drift test a čtvrtý driver do brány

Na `packages/table/dist/wire-table-records.js` neexistuje drift test
(`grep ASSETS_PATH packages/table/tests/` je prázdný). Zapomenuté
`npm run build:table-assets` nechá všechny PHP testy zelené a driver bude
charakterizovat starý bundle. Doplnit podle `DropdownAssetTest.php:116-133`.

`verify-record-actions.mjs` je **čtvrtý** CDP konzument selection komponenty —
sahá na `Alpine.$data([data-selection-root]).selected` (`:157`, `:163`, `:170`)
a `ctrl().anchorKey` (`:157`). Doplnit do brány.

### 2 — Preview infrastruktura

| Soubor | Co |
|---|---|
| `workbench/app/Models/GestureRow.php` | nový model (precedens: `Task`, `Invoice`, `InvoiceItem`) |
| `workbench/database/migrations/*_create_gesture_rows_table.php` | migrace |
| `workbench/database/seeders/DatabaseSeeder.php` | 40 řádků, **`users` nesahat** |
| `workbench/app/Livewire/Previews/TablePreview.php` | nová větev v `table()` před `:136` + private `gestureTable()` |
| `workbench/routes/web.php` | slugy do **anonymního `foreach` na `:288`** |

Tři varianty: `selection-gestures` (40 řádků, `paginated(false)`),
`selection-gestures-paged` (perPage 20) a **`selection-only`** — `selectable()`
**bez** record actions. Bez třetí je ověření kroků 6 a 7 bezobsažné: jediná
dnešní selectable preview má record actions taky, takže by check prošel, i kdyby
se `isSelectable()` nikdy nezapojilo.

`$screens` (`:23-264`) je **jen galerie**, routy neregistruje — důkaz:
`table-paginated` je pouze ve druhé mapě a vrací 200. `WorkbenchServiceProvider`
měnit netřeba, registruje třídy a varianta je mount argument.
`SortablePreview` doplnit `->selectable()` (potřeba v kroku 22).

Databáze je persistentní a `serve` ji nepřestavuje:

```bash
vendor/bin/testbench workbench:build
```

### 3 — CDP driver, charakterizace výchozího chování

`workbench/scripts/verify-selection-gestures.mjs`, vzor
`verify-record-active-row.mjs` (boot, `waitForDevtools`, raw WebSocket,
`Emulation.setDeviceMetricsOverride` místo `--window-size`, `check()`, cleanup
ve `finally`).

Chybí v repu úplně: modifikátorová vrstva (`grep "modifiers" workbench/scripts/`
je prázdný) a tažení myší.

```js
const MOD = { alt: 1, ctrl: 2, meta: 4, shift: 8 }   // ověřeno, aditivní
const modBit = isMac ? MOD.meta : MOD.ctrl
// tažení: mousePressed → N× mouseMoved s buttons:1 → mouseReleased
```

Čtyři vlastnosti prostředí ověřené proti reálnému Chromu:

- **Viewport připnout na `1400×1200`.** Rozteč řádku je 64,5 px, tabulka nemá
  vertikální scroll kontejner, takže `pageStep` vychází z `window.innerHeight`
  a při zděděném viewportu by byl napříč presety nedeterministický.
- **40 řádků poprvé způsobí, že dokument scrolluje.** Žádný existující driver
  s tím nepočítá — souřadnice měří jednou. `activate()` navíc volá `focus()` bez
  `preventScroll`. Souřadnicové checky přeměřovat po každém pohybu.
- **`mod`+klik nejde na macOS testovat jako `ctrl`+klik** — `ctrl`+klik je tam
  sekundární klik a Chrome `click` spolkne úplně.
- **`Shift`+`F10` v headless Chromu nativní `contextmenu` negeneruje.** Reálný
  `Input.dispatchKeyEvent` vystřelí jen `keydown`. Viz krok 19.

Sekce, které musí projít proti stavu po kroku 0:

| # | Charakterizuje |
|---|---|
| C1 | klik na checkbox toggluje a nastaví kotvu |
| C2 | `Shift`+`↓` dělá rozsah od kotvy |
| C3 | `Shift`+`↓` po `mod`+`A` blok zmenší o jedna |
| C4 | `mod`+`A` vybere stránku |
| C5 | `Space` toggluje aktivní řádek |
| C6 | šipka výběr nemění |
| C7 | `Enter` / dvojklik spustí akci, `Delete` spustí `onKey` |
| C8 | pravý klik otevře menu |
| C9 | v `all` módu `Shift`+`↓` shodí výběr na stránku — **chyba, obrací se v 15** |
| C10 | `toggleAll()` v `all` módu invertuje výběr — **chyba, obrací se v 10** |
| C11 | mobilní karty: toggle, select-all pruh, počet |
| C12 | bulk bar: počet a čtyři akce |
| C13 | klávesy nefungují při fokusu uvnitř řádku (`record-actions.js:141`) |

C3, C9 a C10 popisují chování, které se **záměrně** změní. Charakterizovat je teď
znamená, že se aserce později obrátí vědomě.

---

## Fáze II — PHP předpoklady (kroky 4–11)

### 4 — `onKey()` odmítne rezervovanou klávesu hlasitě

`RecordActionResolver::shortcuts()` (`:145`) rezervované klávesy dnes **tiše
zahazuje** — `$reserved = ['enter','return','space','']`. Krok 5 by tak byl tichá
BC změna: aplikace s `->onKey('Home')` by o něj přišla bez jediného signálu.

Nejdřív tedy výjimka nebo `trigger_error`, s testem.

### 5 — Rozšířit rezervovaný seznam

Přidat `arrowup`, `arrowdown`, `home`, `end`, `pageup`, `pagedown`,
`contextmenu`, `f10`, `?`.

**`backspace` do seznamu ne** — kolidovalo by s krokem 19, kde je platformním
aliasem v JS; rezervace v PHP by `->onKey('Backspace')` navždy znemožnila.

Do CHANGELOGu a upgrade guide.

**Ověření:** `RecordActionTest.php:400-407`.

### 6 — Grid semantika: role, tabindex, ARIA

Bez tohohle jsou kroky 18, 25 a 26 na tabulce bez record actions inertní a ARIA
neplatná: `role="grid"`, `role="row"`, tabindex i `@keydown` visí na
`keyboardNavEnabled()` (`Table.php:1686`) = `recordActionKeyboard ?? hasRecordActions()`,
a guard `record-actions.js:141` vyžaduje fokus přímo na `<tr>`.

- nový `Table::usesGridSemantics()` jako jediný vlastník rozhodnutí „je to grid"
- `keyboardNavEnabled()` → `recordActionKeyboard ?? (hasRecordActions() || isSelectable())`

**Netýká se to jen `->selectable()` tabulek.** `isSelectable()`
(`Table.php:849-852`) je `selectable || ! empty($bulkActions)`, takže se zgriduje
**každá tabulka s bulk akcemi**.

**Ověření:**
- `RecordActionRenderTest.php:110-114` zůstává zelený (`NoRecordActionComponent`
  dědí `$selectable = false`, `CtxNoMenuComponent` také není selectable) —
  ověřeno, žádný existující PHP test nezčervená
- `RecordActionTest.php:430-435` — explicitní `recordActionKeyboard(false)` dál vyhrává
- **nový render test pro `selectable()` bez record actions** — dnes žádný neexistuje
- CDP na variantě `selection-only` z kroku 2

### 7 — Grid semantika: mount pointer controlleru

`keyboardNavEnabled()` krmí i `$recordActionsRootEnabled` (`index.blade.php:76`),
takže krok 6 by jinak naráz přinesl celý `wireRecordActions` na `<tbody>` —
`@click`, `@dblclick`, `@focusin`, `record-actions-assets` bundle,
`...rowClass(%key%)` v `:class` a `focus-visible:ring-*`.

**Viditelná změna vzhledu u všech stávajících konzumentů:** řádky se stanou
fokusovatelnými, klik jim sebere fokus (`activate()` → `rows[i].focus()`),
dostanou marker `bg-primary-100 dark:bg-primary-900/30` a vypne se jim
`hover:bg-*`, dokud jsou označené.

Do CHANGELOGu a upgrade guide.

### 8 — `matching` přestane být zapečené

`index.blade.php:267` má `matching: {{ $recordCount }}` v `x-data`, ale root má
`wire:key="table-wrapper"` (`:255`), takže ho Livewire morphuje a `x-data` se už
nevyhodnotí. Projev: vyfiltruj na 7 řádků → „Vybrat všech 7" → bulk bar ukáže
původní počet. Server počítá správně, rozchází se jen klient.

Oprava vzorem, který v souboru už je (`pageKeys`, `:257` + getter `:269`):
`data-matching` + getter.

### 9 — Serverová normalizace `selection.mode`

`TableStateSynthesizer::hydrate()` (`:76-77`) dělá `array_intersect_key` jen na
top-level klíčích; vnořené hodnoty neprochází ničím a `set()` (`:107-110`) je
syrový zápis.

`mode: 'all'` s neprázdným `records` je **legitimní tvar** — přesně tak vypadají
výjimky. Právě v tom je problém: o významu `records` rozhoduje sám `mode`, a nic
je nesvazuje. Každá cesta, která přehodí `mode` na `'keys'`, zatímco `records`
drží výjimky, výběr invertuje.

`TableStateSchema` navíc nemá verzi a `selection.mode` nemá legacy alias
(`legacyPropertyMap():96-126` mapuje jen `selectedRecords → selection.records`).

Minimum: normalizovat mód (`in_array($mode, ['keys','all'], true) ?: 'keys'`)
a při změně tvaru vyprázdnit `records`, serverově v `CanSelectRecords`.

### 10 — Oprava `toggleAll()` v `all` módu

`index.blade.php:281-291`: v `all` módu drží `selected` výjimky a `clear` je tam
vždy `true`, takže **jeden klik na hlavičkový checkbox** nastaví `mode='keys'`
a nechá v `selected` právě klíče, které uživatel odškrtl. Výběr se invertuje na
opačnou množinu a bulk akce z toho čtou (`InteractsWithTableModals.php:30-53`).

Server to dělá správně: `CanSelectRecords.php:76-78` a `:98-100`
(`selectsAllMatching() ? [] : $records`).

Musí se opravit **před** krokem 12, jinak se chyba beze změny přestěhuje do
nového modulu.

**Ověření:** obrátit C10.

### 11 — Sjednotit sémantiku „vybrat stránku"

V repu existují tři různé odpovědi:

| Kde | Chování |
|---|---|
| blade `toggleAll()` | v `all` módu maže |
| JS `selectPage()` (`record-actions.js:266-273`) | nahrazuje (`selected = [...pageKeys]`) |
| PHP `selectAllRecords()` | podle `CanSelectRecordsTest:108` sjednocuje, podle `:263` z `all` módu zužuje na stránku |

Rozhodnout jednu kanonickou, srovnat na ni všechna tři místa a doplnit PHP test,
který ji **opravdu** připíná.

Bez tohohle kroku je verifikační kritérium kroku 15 bezcenné: staré aserce
v `CanSelectRecordsTest` jsou čistě PHP a zůstanou zelené bez ohledu na to, co se
v JS stane — a dvě z nich chystanou změnu navíc přímo popírají.

---

## Fáze III — extrakce (kroky 12–14)

### 12 — `wireRecordSelection`

Nejrizikovější krok plánu: čtyři konzumenti a Pest z toho vidí pětinu.

**Opatření, které to riziko srazí: `x-data` zůstane na tomtéž elementu.** Změna
je doslova `x-data="{ …54 řádků… }"` → `x-data="wireRecordSelection({…})"`.
Element, `data-selection-root`, `data-page-keys` i Alpine scope chain zůstávají,
takže se žádný konzument nedotkne.

**Tři závazná pravidla kvůli `entangle`** (ověřeno ve zdroji: `injectDataProviders`
váže `this` factory na magic kontext s `$wire` — `livewire.esm.js:2937`,
`:3647-3649`; `initInterceptors` běží jednou na `:3655`, jeden řádek před `init()`
na `:3656`):

```js
// function, NE arrow — this v těle factory je magic kontext s $wire
window.Alpine.data('wireRecordSelection', function (config = {}) {
    return {
        // MUSÍ být v návratovém literálu, ne v init(): tam by se uložil syrový
        // interceptor objekt a výběr by tiše přestal fungovat ($wire je do
        // objektu injektovaný zvlášť, takže by nic nespadlo)
        selected: this.$wire.entangle(config.statePath + '.records'),
        mode: this.$wire.entangle(config.statePath + '.mode'),
    }
})
```

Config předává PHP (`statePath`, `syncLive`, `commitDelay`), aby sémantika
zůstala v PHP a testy měly na co assertovat.

**Doručení:**

- `package.json` → druhý esbuild příkaz s vlastním `--outfile`. **Ne `--outdir`** —
  ověřeno spuštěním, emituje `record-actions.js` místo `wire-table-records.js`.
- Route měnit netřeba, `{asset}` je volný parametr — **ale výstup se musí jmenovat
  `wire-table-*.js`**, provider skládá cestu jako
  `ASSETS_PATH.'/wire-table-'.basename($asset).'.js'`.
- Nový partial `selection-assets.blade.php` (mtime cache-bust).
- Include **uvnitř `@if($isSelectable)`, ne uvnitř `<tbody>`** — to se nerendruje
  bez viditelných sloupců, ale výběr je aktivní i v kartách.
- **`dist/wire-table-selection.js` commitnout.** Dist je verzovaný, CI ho
  negeneruje.

**Fallback na chybějící bundle je povinný.** Route vrací
`abort_unless(is_file($file), 404)` a selection `x-data` sedí na **vnějším
wrapperu** (`:253`), který obsahuje vyhledávání, filtry, dropdowny, bulk bar,
stránkování, mobilní karty i hosty modalů (`:1386`, `:1389`). Jedna 404 tedy
rozbije Alpine pro **celé UI tabulky**, a pro uživatele tiše: chyba v konzoli,
trvale viditelný bulk bar, mrtvé checkboxy. Totéž platí pro `wireRecordActions`,
který je taky odkaz na factory. Partial už `is_file()` volá kvůli cache-bustu —
stejná kontrola může fallbacknout na inline `x-data`.

### 13 — Testy po extrakci

| Soubor | Změna |
|---|---|
| `WithTablePerformanceTest.php:589` | `entangle('tableState.selection.records')` → `wireRecordSelection(` + `statePath` v configu |
| `WithTablePerformanceTest.php:588, :590, :591` | `data-page-keys=`, `x-show="selectedCount > 0"`, `not->toContain('wire:click="toggleRecordSelection')` |
| `MobileControlsRenderTest.php:122` | tri-state ternár se stěhuje do getteru |
| `MobileControlsRenderTest.php:110, :115` | `assertDontSee('table-card-select-all')` |
| `RecordActionRenderTest.php:120`, **`:125`** | `assertSee`/`assertDontSee('data-selection-root')`; `:125` připíná umístění includu |
| `RecordActionRenderTest.php:148` | `toContain('isSelected(')` — název metody musí zůstat |
| nový `SelectionRenderTest.php` | komponenta právě jednou; bez `selectable()` ani komponenta ani bundle |
| nový drift test | bundle obsahuje `wireRecordSelection` |

**Kritérium fáze III:** celá síť z kroku 3 projde beze změny jediné aserce
popisující **chování**. Změnit se smí jen aserce na doslovné stringy, které se
přesunem nutně mění (`entangle(…)`, tri-state ternár) — to jsou artefakty
přesunu. Cokoli jiného je signál k revertu, ne k úpravě testu.

### 14 — Přesun kotvy a verzovací značka

`anchorKey` je deklarovaný v `record-actions.js:57`, **ne** v blade `x-data`,
který krok 12 stěhuje. Jeho přesun do `wireRecordSelection` je změna vlastnictví
a stěhuje s sebou i `moveActive:199-210`, `anchorFor:234-247`,
`selectRange:250-264`, `selectPage:266-273`, `onPointer:399-404` a větev `Space`
`:177-181`. Proto vlastní krok, ne součást 12.

Dotkne se `verify-record-active-row.mjs:245` a `verify-record-actions.mjs:157`.

**Zároveň zavést `data-selection-version` do markupu.** `wire-table::views` je
dokumentovaný publish tag (`docs/theming.md:126`): JS se veze s balíčkem
a aktualizuje se, publikovaný Blade ne. Od tohohle kroku dál čte dodávaný
`record-actions.js` stav, který poskytuje jen nová komponenta — a protože
`[data-selection-root]` pořád existuje a pořád vrátí objekt, **nespadne nic, jen
se rozsahy budou vybírat špatně.** `wireRecordSelection` musí starší nebo
chybějící značku odmítnout hlasitě.

---

## Fáze IV — chování (kroky 15–19)

### 15 — `mode` se přestane přepisovat

- `selectRange` (`:261`) — zápis `mode` úplně pryč, místo něj sjednocení
- `selectPage` (`:270`) — zápis `mode` pryč **a přidat `if (sel.selectsAll) return`**
  (dnes tam žádný takový guard není, `selectsAll` se v `record-actions.js`
  nevyskytuje). `mod`+`A` není rozsahové gesto: v `all` módu je vybráno všechno
  a sjednocení `pageKeys` do `selected` (= výjimek) by stránku odznačilo.

**Ověření:** obrátit C9 + nový PHP test z kroku 11.

### 16 — `base ∪ rozsah`

Snapshot je povinný: sjednocení je monotónní, takže z aktuálního `selected` se
zmenšení rozsahu odvodit nedá.

```
base = selected \ blockAround(kotva)
```

Ověřeno na obou scénářích — po `mod`+`A` je blok celý výběr, base prázdná
a zmenšování funguje; u výběru 2–6 a pak 8–12 je blok kolem kotvy `{8}`, base
`{2..6}` a sjednocení dá `{2..6, 8..12}`.

- faktorizovat smyčku z `anchorFor:241-245` do `blockAround(rows, idx)`
- `snapshotBase(rows)` — **`[...sel.selected]`, ne reference** (entangle proxy by
  se pod rukama změnila při prvním zápisu)
- `baseSelection = null` všude, kde se nuluje `anchorKey`, **plus** `selectPage()`
  a `MutationObserver` (`:65-71`) — tam **jen když kotevní řádek zmizel**, jinak
  by se base s klíči z jiné stránky sjednotila zpátky a vzkřísila neviditelné řádky
- **plus na `resetSelectionScope()`** (`WithTable.php:391-395`), které se volá při
  každé změně state path kromě sortu a perPage; invalidace jen přes DOM nestačí
- **`onRowFocus` do toho seznamu NESMÍ** — `activate()` volá `focus()`, což
  vystřelí `focusin`, a čištění base by ji smazalo hned po nastavení

### 17 — Myš

`Shift`+klik, `mod`+klik (i mimo checkbox), `mod`+`Shift`+klik. Sdílí
`blockAround` i `snapshotBase` s krokem 16.

### 18 — Klávesnice

Pořadí guardů v `onKeydown` je závazné (`:135` → `:141` → `:148` → `:151`).
Nová klávesa před `:141` znamená, že `Backspace` v editované buňce spustí mazací
akci; před `:148`, že `PageDown` hýbe markerem pod otevřeným modalem.

- `mod`+`Shift`+`↑`/`↓` dovnitř existujících `case`ů
- `Home`/`End`, `PageUp`/`PageDown` nové `case`y, všechny přes tentýž
  `moveActive(rows, idx, target, event.shiftKey)`
- `Shift`+`Home`/`End` vychází na identické indexy jako `mod`+`Shift`+šipka →
  jedna implementace
- `pageStep` z **rozteče**, ne z výšky řádku, s guardem
  `if (! pitch || ! viewport) return 1` (`display:none` tabulka na mobilu vrací
  nuly a `Math.floor(x/0)` = `Infinity` → throw v `activate()`)
- **viewport hledat u nejbližšího scrollujícího předka, ne z `window`** — balíček
  sám scroll kontejner nemá, ale tabulky se rendrují v modalech
  (`modals/modal.blade.php:18` je `fixed inset-0 overflow-y-auto`, `:55` a `:115`
  přidávají `overflow-y-auto` tělo při `maxHeight`) a konzument si tabulku může
  obalit `max-h-96 overflow-y-auto`
- `mod`+`PageUp`/`PageDown` nevázat (Chrome, přepínání panelů)
- opravit `:155`, kde `mod`+`A` větev nekontroluje `shiftKey`

### 19 — `Backspace`, `Shift`+`F10`, fokus v menu

- `Backspace` jako ekvivalenční třída `['delete','backspace']` ve dvou průchodech
  `matchShortcut` (přesná shoda vyhrává). **V JS, ne v PHP** — v PHP by rozbil
  `RecordActionTest.php:406` a `:417` a v legendě by se vypsal jako samostatný
  řádek místo „`Delete` / `⌫`".
- `Shift`+`F10` **nemůže jít matcherem** — `kb.shortcuts` mapuje na jméno akce
  a otevření menu není akce. Vlastní `case 'F10'` s `if (! event.shiftKey) return`
  a fallthrough do `case 'ContextMenu'`.
- **Headless Chrome neověří, jestli po `Shift`+`F10` přijde nativní `contextmenu`**
  (vystřelí jen `keydown`), takže check by byl falešně zelený. Buď headed Chrome,
  nebo obranu (flag `_menuFromKey` zahozený v `setTimeout(…, 0)`) napsat rovnou
  a v kódu označit jako netestovanou.
- **Fokus do kontextového menu** patří sem, ne až do fáze VII. `openMenuForRow`
  (`:342-347`) fokus nepřesouvá a `dialogOpen()` (`:313-317`) panel nevidí, protože
  je `role="menu"`, ne `role="dialog"` — šipky tedy hýbou markerem za otevřeným
  menu. `Shift`+`F10` tu vadu zpřístupní klávesnicí.

---

## Fáze V — sweep (kroky 20–22)

### 20 — `[data-select-cell]` do markupu

Atribut **v repu neexistuje** a stojí na něm kroky 22 i 27. Vlastní malý krok bez
chování: přidat na checkboxovou `<td>` (`index.blade.php:948`), na mobilní
protějšek **a na poziční placeholdery** v `summary-footer.blade.php:47-50`
a `group-subtotal.blade.php:13-16`, jinak se sloupce rozjedou.

### 21 — Promoce sdílených helperů do core

`createAutoScroller` (`fill/autoscroll.js`) a `bodyRows`/`rowAtY`
(`fill/grid.js:17-23`, `:100-115`) do `core/resources/js/support/`.

Jsou bundlované do `wire-core-dropdown.js` přes `dropdown.js:3`, takže krok
vyžaduje **`npm run build:core-assets` a commit core distu**.
`DropdownAssetTest.php:116-133` zastaralý bundle nechytí — grepuje na stringy,
které se přesunem mezi soubory nemění.

### 22 — Sweep

Precedens je `wireFillHandle`, ale ve třech bodech se odchyluje:

- **Žádný `preventDefault()` na `pointerdown`** — zabilo by fokus na
  `<button role="checkbox">`. Dvoufázově **arm → engage**: pointerdown jen
  zapamatuje řádek, teprve první `pointermove` měnící řádek gesto nastartuje.
- **`click` po tažení zabít capture listenerem** na selection rootu. Ověřeno, že
  se trailing `click` retargetuje na společného předka (`BODY`), takže listener
  musí být na předkovi obou — `[data-selection-root]` jím je. Pointer capture
  tohle neřeší. Pojistka `setTimeout(…, 0)` ve `stopSweep()`.
- **Dotyk mimo:** `pointerType !== 'mouse'`, `button !== 0`, `! isPrimary`.
  `touch-action` **neměnit** — fillí `touch-action: none` by zablokovalo scroll
  v celém checkboxovém sloupci.

Buňku hledat výhradně přes `[data-select-cell]` — sortable `addRowDragHandles()`
(`:206-215`) prependuje `<td>` a posune sloupec z indexu 0 na 1. Řádkový Sortable
je gatovaný `handle: '.wire-sortable-handle'` a instancuje se jen v reorder módu,
takže tažení z checkboxové buňky drag nespustí.

Morph guard po vzoru `controller.js:38-50`, sdílený s fillem.

Teleportované dropdowny opouštějí DOM podstrom selection rootu, takže na ně
capture listener nedosáhne — pro sweep neškodné, ale nedá se na něj spoléhat
u teleportovaných povrchů.

**Známý přilehlý problém:** `reorderBodyColumns()`
(`sortable/…/scripts.blade.php:319-334`) je poziční bez offsetu na selection
buňku, takže přeřazování sloupců je u selectable+sortable tabulky **rozbité už
dnes**. Krok 2 přidává `->selectable()` do `SortablePreview`, takže to vyplave
jako zdánlivá regrese. Buď na to vyhradit čas, nebo vědomě odložit a poznamenat.

**Ověření:** sweep 2→6 přidá; sweep mimo sloupec nevybere nic a
`getSelection().toString()` je neprázdný (ověřeno, že nativní označování textu
při tažení skutečně nastává); po `mouseReleased` žádný toggle navíc; koexistence
na `SortablePreview`; `prefers-reduced-motion: reduce` → `transitionDuration === '0s'`.

---

## Fáze VI — nápověda (kroky 23–25)

### 23 — Core seam: modal otevíratelný z JS

Všechny tři shelly mají natvrdo `x-data="{ show: @entangle($modelBinding) }"`
(`modals/modal.blade.php:13`, `confirmation:15`, `slide-over:13`).

Rozšířit **kanonický shell**, ne udělat lokální variantu: volitelný `openOn:`
→ `x-data="{ show: false }" x-on:{event}.window="show = true"`, když
`$wireModel === null`. Seam se mění před downstream callery.

`ModalHtmlObjectTest.php:45/53` a `ConfirmationObjectTest.php:52/97` assertují
`wireModel:` config, ne doslovný `x-data` string, takže aditivní `openOn:` je
bezpečný. Ověřit CDP, že Livewire update s otevřenou nápovědou ji nezavře.

### 24 — Legenda zkratek

Tři vlastnictví, ne jedna třída:

| Kam | Co |
|---|---|
| `core/Foundation/Support/ShortcutLabelFormatter` | formátování `mod+d` → `⌘D`/`Ctrl+D`; dnes `protected` metoda v traitě (`HasKeyboardShortcut.php:133`) mapující `mod → 'Ctrl'` natvrdo, což odporuje §2 plánu |
| `core/Foundation/ValueObjects/ShortcutHint` | řádek přehledu — chtějí ho i palette, wizard, forms |
| `table/src/Support/TableShortcutLegend` | co za gesta tabulka má; table-only |

Pozor: `HasKeyboardShortcut.php` existuje dvakrát — `core/src/Concerns/` je
12řádkový deprecated `class_alias` shim, skutečná je `core/src/Actions/Concerns/`.

Legenda je data, ne markup — žádný `toHtml()`.

**Coverage:** nový soubor v `src/`, tedy 100 % změněných řádků. Test musí pokrýt
prázdnou legendu, jen `selectable()`, jen record actions, promítnutí `onKey()`,
lokalizaci a deduplikaci.

### 25 — Nápověda `?`

`Modals\Html\Modal(heading:, bodyView: 'wire-table::tables.partials.shortcut-help',
bodyData: […])`.

Dvě pasti: matchovat `event.key === '?'` (na CZ je to `Shift`+`,`, `code` by
zkratku zabil) a zopakovat guard „fokus je na řádku", jinak otazník napsaný do
vyhledávání otevře nápovědu.

---

## Fáze VII — přístupnost (kroky 26–27)

### 26 — ARIA a `aria-live`

| Atribut | Kde | Binding? |
|---|---|---|
| `aria-multiselectable` | `:710`, uvnitř `@if($tableRole)` | statický |
| `aria-selected` | `:916-926` | **binding** — statická hodnota by se po morphu vrátila na serverovou pravdu |
| `aria-rowcount` | `:710` | statický |
| `aria-rowindex` | `:714`, `:815` (hlavičky = 1, 2), `:916-926` (tělo od `headerRowCount + 1`) | statický |
| `aria-live` | `:314-315`, hned za otevřením selection rootu | **binding** |

**Refaktor:** `$from`/`$to`/`$total` se počítají až v patičce (`:1337-1341`), pro
`aria-rowindex` je nutné je vyzvednout do preambule; `$headerRowCount` musí
odrážet `$hasColumnFilters` (`:142`), jinak indexy ujedou.

**`aria-live` nesmí do bulk baru** (`:615`) — je pod `x-show`, a skrytý live
region neoznamuje; navíc by zmizel při nulovém výběru, takže „odznačeno vše" by
se neohlásilo nikdy. Region musí být v DOM od začátku a prázdný, s jedním
`x-text`. Plurály nejdou řešit třemi `x-show` spany jako v bulk baru — potřebují
hotové hlášky z PHP.

Mobilní karty `aria-selected` nedostanou (nejsou `role="row"`).

### 27 — Marker, klikatelná plocha, `INTERACTIVE`

`[data-select-cell]` do `INTERACTIVE` (`record-actions.js:25-38`) musí jít **týmž
commitem** jako zvětšení klikatelné plochy na celou buňku — jinak klik do
paddingu propadne do `onPointer` a spustí record action.

Doslovné třídy hlídá pět míst: `RecordActionRenderTest.php:130-149`,
`RecordActionTest.php:454-472` (`getActiveRowConfig()` s hover variantami),
`RecordActionTest.php:85-86` a `:326-365`, `TableTest.php:518-576`, a v CDP
`verify-record-active-row.mjs:71` **i** `verify-record-actions.mjs:141`
(obojí zadrátované `classList.contains('bg-primary-100')`).

**CDP:** kontrast ≥ 3:1 z luminance, `MutationObserver` nad live regionem,
klikatelná plocha ≥ 24×24.

---

## 28 — Dokumentace a doprovod

- `docs/table/record-actions.md` + `docs/cs/…` — EN i CZ v synchronu
- **`CHANGELOG.md`** — nové veřejné API, změna chování u každé selectable tabulky
  (6, 7), obrácené chování v `all` módu (15), rezervované klávesy (5)
- **`docs/upgrade.md` + `docs/cs/upgrade.md`** — kroky 5, 6, 7, 15 a seznam kroků,
  po kterých je nutné přepublikovat view
- **i18n** — `packages/table/lang/{en,cs}/messages.php`: klíče pro `?` modal
  (krok 25) a hlášení live regionu (krok 26)
- **`packages/boost/…/guidelines/wire-table.blade.php:95-105`** popisuje sémantiku
  gest ručně; `boost:sync-docs` synchronizuje `docs/`, ne guidelines
- **Screenshoty** — `capture-previews.mjs:32` snímá `table-selection`, `:39-40`
  sortable; krok 2 přidává checkboxový sloupec do `SortablePreview`, krok 27 mění
  marker. `npm run docs:refresh`.

---

## Konzumenti, na které je potřeba myslet průběžně

| Co | Kde | Proč |
|---|---|---|
| `$colSpan` aritmetika | `index.blade.php:150` → `group-header:4`, `sub-rows:10,53`, `summary-footer:23,95` | počítá selection sloupec |
| `$selectionSyncLive = $isSelectable && $hasSummaries` | `index.blade.php:116` | **na tabulce bez summary se výběr na server necommituje vůbec**; bulk akce hned po sweepu může jet na zastaralém stavu → flush před bulk akcí |
| `queueCommit()` debounce 350 ms | `index.blade.php:307-311` | 30řádkový sweep queueuje commity uprostřed tažení |
| `getSelectedRecordKeys()` vrací `[]` v `all` módu | `CanSelectRecords.php:235-240` | konzument, kterého nová gesta dostanou do `all` módu, dostane prázdné pole → upgrade note |
| výběr není v query stringu | `WithTableQueryString.php` | „vybrat vše odpovídající → změnit filtr" výběr tiše zahodí |

---

## Brána po každém kroku

```bash
composer test:table
vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"
composer analyse && composer lint
npm run build:table-assets                              # kdykoli se sáhne na JS

vendor/bin/testbench serve --host=127.0.0.1 --port=8085 &
node workbench/scripts/verify-selection-gestures.mjs
node workbench/scripts/verify-record-active-row.mjs     # regrese
node workbench/scripts/verify-record-actions.mjs        # regrese
node workbench/scripts/verify-record-actions-dual.mjs   # regrese
node workbench/scripts/verify-mobile-selection.mjs      # regrese
pkill -f "testbench serve"
```

Coverage jen když se sáhlo do `src/*.php`:

```bash
php -d memory_limit=-1 vendor/bin/pest --coverage-clover=build/clover.xml
php scripts/verify-coverage.php build/clover.xml --diff=origin/1.x
php scripts/verify-coverage.php build/clover.xml        # floors
```

Navíc: krok 21 a 22 — `npm run build:core-assets`, commit `packages/core/dist/`,
`composer test:core`, `composer test:sortable`,
`node workbench/scripts/verify-fill-handle.mjs`. Krok 23 — `composer test:core`
**i `composer test:forms`** (shelly konzumuje i wire-forms).

DB matice nikde — žádné nové SQL. Jedinou výjimkou by byl `aria-rowcount`, kdyby
sáhl po počtu jinak než přes existující `$recordCount`; pak ověřit
`MobilePerformanceTest.php:190` a `CanSelectRecordsTest.php:407`.

---

## Rozhodnutí do ADR 0024

1. `mod`, nikdy doslovný `Ctrl` — `Ctrl`+klik je na Macu pravý klik (a Chrome
   `click` spolkne), `Ctrl`+šipka je Mission Control, systémová a nepotlačitelná.
2. `base ∪ rozsah`, kde **base = snapshot mínus souvislý blok kolem kotvy**.
3. Rozsahová gesta se zapisují jako sjednocení do `selected`; `mode` se nikdy
   nepřepisuje. **V `all` módu tedy rozsah odznačuje** a „souvislý blok" znamená
   blok nevyloučených řádků.
4. `mod`+`A` není rozsahové gesto → guard na mód je tam správně.
5. Kotva je jednorázová a bez vizuálu.
6. Rozsah gest = jedna stránka.
7. Sweep jen aditivní, jen v checkboxovém sloupci, jen myší; buňka výhradně přes
   `[data-select-cell]`.
8. `Backspace` je platformní alias v JS, ne položka v PHP mapě zkratek.
9. Grid semantika patří `selectable()` tabulkám (a tím i tabulkám s bulk akcemi)
   stejně jako tabulkám s record actions.
10. Markup výběru je verzovaný a starší publikovaný view se odmítá hlasitě.
11. Cross-package JS import core → table (a s ním povinnost rebuildovat oba disty).
