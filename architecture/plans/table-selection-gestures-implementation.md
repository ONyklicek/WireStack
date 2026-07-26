---
title: Implementační analýza — kontrakt výběru a klávesových zkratek tabulky
date: 2026-07-26
scope: packages/table, packages/core (dva seamy), workbench (preview + CDP driver)
status: analýza — podklad k implementaci, ne kontrakt
parent: architecture/plans/table-selection-gestures.md
---

# Implementační analýza

Doprovodný dokument k `table-selection-gestures.md`. Ten drží **co** se má stát
(rozhodnutí), tenhle **jak** to udělat, aby to nerozbilo, co už funguje.

Vše níže je ověřené čtením kódu. Kde je něco neověřené, je to označené.

---

## 1. Co analýza změnila na plánu

Tři věci, které plán nepředpokládal, a každá mění pořadí práce.

### 1.1 Klávesová sada dnes nefunguje na tabulce, která je jen `selectable()`

`role="grid"`, `role="row"`, roving tabindex i `@keydown` visí na
`Table::keyboardNavEnabled()` (`Table.php:1686`), což je
`recordActionKeyboard ?? hasRecordActions()`. Bez record actions nejsou řádky
fokusovatelné, a guard `event.target !== this.row(event)`
(`record-actions.js:141`) navíc vyžaduje fokus přímo na `<tr>` — což bez
tabindexu nikdy nenastane.

Důsledky:

- `Space`, šipky, `mod`+`A` ani žádná nová klávesa nemají na čem viset.
- Všechny čtyři ARIA doplňky z §4 plánu by byly **neplatné ARIA**, protože
  tabulka nemá grid semantiku.
- §5b plánu řeší jen `?`. Tatáž věta ale platí na všechny ostatní klávesy, které
  plán nechává v `record-actions.js`. Přesunout jen `?` problém nevyřeší, jen
  zúží: nápověda by vypsala zkratky, které tam nefungují.

**Oprava je jedna a je výš** — viz etapa 0.

### 1.2 `base ∪ rozsah` si odporuje s dokumentovaným chováním

Plán §2 říká `base ∪ rozsah`, kde base = výběr před gestem. Jenže dnes platí, že
první `Shift`+šipka po `mod`+`A` blok **zmenší** (5 řádků → 4). Je to záměr:

- komentář v `anchorFor()` (`record-actions.js:227-233`),
- dokumentace `docs/table/record-actions.md:112-118`,
- CDP check `workbench/scripts/verify-record-active-row.mjs:231-233`.

Sjednocení je monotónní. Když base = celý předchozí výběr, rozsah už nikdy nic
neubere → CDP test spadne a dokumentované chování přestane platit.

**Řešení: base = snapshot mínus souvislý blok, který gesto přebírá.**

```
base = selected \ blockAround(anchorRow)
```

Ověřeno na obou scénářích:

| Scénář | `blockAround(kotva)` | base | Výsledek |
|---|---|---|---|
| `mod`+`A` na 5 řádcích, aktivní r0 | r0..r4 (celý výběr) | ∅ | `Shift`+`↓` → 4 řádky, **zmenšení funguje** |
| výběr 2–6, `Space` na 8, `Shift`+`↓`×4 | `{8}` | `{2..6}` | `{2..6} ∪ {8..12}`, **nesouvislý výběr funguje** |

Splňuje to obojí — „nezahodit výběr, který jsem neudělal" i „blok jde zmenšit".
Je to rozhodnutí do ADR, ne implementační detail.

### 1.3 `?` modal je blokovaný na změně v core

Všechny tři modal shelly mají natvrdo Livewire entangle:
`modals/modal.blade.php:13`, `modals/confirmation.blade.php:15`,
`modals/slide-over.blade.php:13` — `x-data="{ show: @entangle($modelBinding) }"`.
Bez Livewire property neexistuje cesta, jak modal otevřít z JS.

Etapa 5 tedy není table-only, je to cross-package změna → podle
`CLAUDE.md` Cross-Package Change Checklist se **mění seam před downstream
callery**.

---

## 2. Etapa 0 — předpoklady (nová)

Nic z toho nemění chování z pohledu uživatele, ale všechno ostatní na tom stojí.

| # | Změna | Soubor | Proč |
|---|---|---|---|
| 0.1 | `usesGridSemantics(): bool` jako jediný vlastník rozhodnutí „tahle tabulka je grid" | `packages/table/src/Table.php:1696` | dnes to rozhoduje `$keyboardNav`, které míchá JS chování a ARIA semantiku |
| 0.2 | `keyboardNavEnabled()` → `recordActionKeyboard ?? (hasRecordActions() \|\| isSelectable())` | `Table.php:1688` | bez toho je §1.1 |
| 0.3 | `matching` z `x-data` do `data-matching` + getter | `index.blade.php:267` | zapečené číslo pod `wire:key="table-wrapper"` se po morphu nikdy nepřepočítá |
| 0.4 | `'[data-select-cell]'` do `INTERACTIVE` | `record-actions.js:25-38` | musí jít **současně** se zvětšením klikatelné plochy (etapa 6), jinak klik do paddingu buňky spustí record action |
| 0.5 | rozšířit `$reserved` o navigační klávesy | `RecordActionResolver.php:144` | dnes rezervuje jen `enter`/`return`/`space`, takže `->onKey('Home')` projde a nový `case` ji tiše zastíní |

K 0.2 — BC je ověřená: `NoRecordActionComponent`
(`RecordActionRenderTest.php:58-61`) dědí `$selectable = false`, takže test
„leaves a plain table ungridded" (`:110-114`) zůstane zelený. Explicitní
`recordActionKeyboard(false)` musí dál vyhrát (`RecordActionTest.php:430-435`).
Přibude test pro `Table::make()->selectable()` → `'grid'`.

K 0.3 — projev dnešního bugu: vyfiltruj na 7 řádků, klikni „Vybrat všech 7",
bulk bar ukáže **původní** počet. Server počítá správně
(`CanSelectRecords::getSelectedRecordsCount()`), rozchází se jen klient.
Vzor pro opravu už v souboru je — `pageKeys` (`:257` + getter `:269`).

---

## 3. Etapa 1 — extrakce `wireRecordSelection`

### 3.1 Past s `entangle` (nejdůležitější věc celé etapy)

Alpine rozbaluje `entangle` přes interceptor, který běží **jednou**, hned po
vytvoření datového objektu (`livewire.esm.js:3642-3662`, `initInterceptors` na
`:3655`). Z toho plynou tři závazná pravidla:

1. **Factory musí být `function`, ne arrow.** V jejím těle je `this` = magic
   kontext s `$wire`. Dnešní `wireRecordActions` je arrow
   (`record-actions.js:50`) a nevadí mu to jen proto, že `$wire` používá až
   v metodách.
2. **`selected` a `mode` musí vzniknout v návratovém literálu, ne v `init()`.**
   Přiřazení v `init()` uloží syrový interceptor objekt a výběr tiše přestane
   fungovat. Tohle je nejtišší možný způsob, jak si rozbít celou funkci.
3. **Config přichází argumentem.** Na jiné properties vytvářeného objektu se
   v těle factory sáhnout nedá.

Gettery jsou v pořádku — `initInterceptors` čte deskriptory a accessor deskriptor
přeskočí, takže se getter při initu nezavolá.

**V repu zatím žádný JS modul neentangluje** (ověřeno grepem přes
`packages/*/resources/js/`). `wireRecordSelection` bude první, takže tohle nemá
kdo odchytit — a Pest to nechytí vůbec.

### 3.2 API kontrakt, který musí zůstat 1:1

Konzumenti jsou **čtyři**, ne tři jak píše plán — plán zapomíná na
`record-actions.js`.

| Konzument | Kde | Co používá |
|---|---|---|
| desktop řádky | `index.blade.php:918` (`:class` skládaný v PHP na `:95-104`), `:952-960` | `isSelected`, `toggle` |
| header checkbox | `:721-732` | `toggleAll`, `allSelected`, `someSelected` |
| bulk bar | `:616-686` | `selectedCount`, `selectsAll`, `deselectAll`, `selectAllMatching`, `selectOnlyPage` |
| mobilní karty | `:1139-1159`, `:1192-1202` | `toggleAll`, `allSelected`, `someSelected`, `selectedCount`, `isSelected`, `toggle` |
| `record-actions.js` | `:216-273` přes `[data-selection-root]` + `Alpine.$data()` | čte `isSelected`, `pageKeys`; **zapisuje** `mode`, `selected`; volá `toggle`, `queueCommit?.()` |

Pozor na `$rowClassBinding` (`index.blade.php:95-104`): PHP skládá `:class`
objekt jako **string**, protože dva `:class` atributy by se tiše přebily.
Extrakce nesmí tuhle kompozici rozbít; `isSelected()` se resolvuje po Alpine
scope chainu do selection rootu, takže funguje i po přesunu.

Falešné shody, které nezaměnit: `@click="toggle()"` na `:347` a `:483` patří
`wireDropdown`, `wire:click="toggleAllRowExpansion"` na `:575` jsou sub-rows.

### 3.3 Doručení modulu

Pipeline je zavedená, stačí ji zrcadlit:

1. `packages/table/resources/js/selection.js` + registrace na `alpine:init`.
2. `package.json` → `build:table-assets` rozšířit o druhý esbuild příkaz
   s vlastním `--outfile`. **Ne `--outdir` s víc entry pointy** — přejmenovalo by
   to existující výstup.
3. Route měnit netřeba — `{asset}` v `WireTableServiceProvider.php:88-102` je
   volný parametr, `wire-table-selection.js` se namapuje sám.
4. Nový partial `views/tables/partials/selection-assets.blade.php` jako kopie
   `record-actions-assets` (mtime cache-bust).
5. Include **uvnitř `@if($isSelectable)`, ne uvnitř `<tbody>`** — dnešní
   record-actions include je v tbody, které se nerendruje bez viditelných
   sloupců, ale výběr je aktivní i v kartách.
6. **`dist/wire-table-selection.js` commitnout.** Dist je v repu verzovaný, CI ho
   negeneruje. Bez něj vrátí route 404 a Alpine spadne na celé komponentě — na
   rozdíl od record actions, kde chybějící skript zabije jen gesta, tady zmizí
   výběr úplně.
7. Drift test proti dist (vzor `packages/core/tests/Feature/DropdownAssetTest.php:100-130`).
   Pro `wire-table-records.js` dnes žádný není, takže je to i díra k zaplácnutí.

---

## 4. Etapa 2 — kotva a rozsahy

### 4.1 `baseSelection` snapshot

`anchorKey = null` nestačí (viz §1.2). Přibude druhý stav a smyčka z `anchorFor`
se faktorizuje, ať ji sdílí kotva i base:

```js
// Souvislý (indexově, ne vizuálně) blok vybraných řádků kolem idx.
blockAround(rows, idx) { … }          // vytaženo z anchorFor:241-245

// Snímek pro base ∪ rozsah: všechno kromě bloku, který gesto přebírá.
snapshotBase(rows) {
    // POZOR: [...sel.selected], ne reference — je to entangle proxy
    // a uložená reference by se pod rukama změnila při prvním zápisu.
}
```

**Kde `baseSelection` zahodit** (všude, kde se dnes nuluje `anchorKey`, plus dvě
navíc):

| Místo | Dnes | Doplnit |
|---|---|---|
| `moveActive` else větev `:207` | `anchorKey = null` | `baseSelection = null` |
| `Space` toggle `:179-181` | `anchorKey = activeKey` | `baseSelection = null` |
| klik na checkbox `:402-404` | `anchorKey = row.key` | `baseSelection = null` |
| `selectPage()` (`mod`+`A`) | netknuto | `baseSelection = null` |
| `MutationObserver` `:65-71` | netknuto | **oboje, ale jen když kotevní řádek zmizel** |

Ten poslední řádek je důležitý: base s klíči z předchozí stránky nebo filtru by
se při dalším `Shift`+šipce sjednotil zpátky a **vzkřísil neviditelné řádky**.

**Živá mina: `onRowFocus` do toho seznamu NESMÍ.** `activate()` (`:329-336`) volá
`rows[i].focus()`, což vystřelí `focusin` → `onRowFocus` (`:277-281`). Kdyby ten
čistil kotvu nebo base, smazal by je hned po tom, co je `moveActive` nastavil.
Dnes to nevadí (nastavuje jen `activeKey`), po přidání base je to past.

### 4.2 Oprava `mode`

`selectRange()` (`:261`) i `selectPage()` (`:270`) dnes natvrdo dělají
`sel.mode = 'keys'`. V `all` módu tím shodí výběr celé filtrované sady na
stránku. Obojí je nasazená chyba, ne nová práce.

- `selectRange` — **žádný zápis `mode`**, jen
  `sel.selected = [...new Set([...base, ...keys])]`.
- `selectPage` — `mod`+`A` **není** rozsahové gesto, takže tady jeden `if` na mód
  patří: v `all` módu je vybráno všechno a sjednocení `pageKeys` do `selected`
  (= výjimek) by stránku naopak odznačilo. Tedy `if (sel.selectsAll) return`.

Serverové protějšky, které definují správnou sémantiku, jsou v
`CanSelectRecordsTest.php` — `:108` union místo replace, `:197` zúžení z `all` na
stránku, `:263`, `:275`. JS je musí kopírovat.

### 4.3 Nedodefinovaný `all` mód

Z rozhodnutí „sjednotit do `selected`" plyne, že **`Shift`+šipka v `all` módu
odznačuje**. Je to konzistentní, ale z tabulky §2 („blok od kotvy") to nikdo
nevyčte. Navíc `blockAround` používá `isSelected()`, které je v `all` módu
invertované — „souvislý blok" tedy znamená blok *nevyloučených* řádků.

Do ADR 0024 explicitně, jinak to bude překvapení.

---

## 5. Etapa 3 — klávesnice

### 5.1 Kam co patří v `onKeydown`

Pořadí guardů (`:135` → `:141` → `:148` → `:150`) je závazné:

| Chyba | Následek |
|---|---|
| nová klávesa před `:141` | `Backspace` v editované buňce spustí mazací akci místo smazání znaku |
| nová klávesa před `:148` | `PageDown` posune marker pod otevřeným modalem; `Backspace` spustí druhou destruktivní akci proti **jinému** záznamu, než na který se modal ptá |
| nová klávesa před `:150` | `rows[0]` na prázdné stránce → `undefined.dataset` v `activate()` |

Umístění:

- `mod`+`Shift`+`↑`/`↓` → dovnitř existujících `case 'ArrowDown'`/`'ArrowUp'`,
  cíl `rows.length - 1` / `0`.
- `Home`/`End`, `PageUp`/`PageDown` → nové `case`y, všechny přes tentýž
  `moveActive(rows, idx, target, event.shiftKey)`. `Shift`+`Home`/`End` vychází
  na **identické cílové indexy** jako `mod`+`Shift`+šipka → jedna implementace.
- `PageUp`/`PageDown` vyžadují `preventDefault()` (nativně scrollují stránku).
- `mod`+`PageUp`/`PageDown` **nikdy nevázat** — v Chrome je to přepínání panelů,
  které `preventDefault` nezachytí.

Drobnost k opravě po cestě: `mod`+`A` větev (`:155`) nekontroluje `shiftKey`,
takže `mod`+`Shift`+`A` dnes taky vybere stránku.

### 5.2 `Backspace` — alias v JS, ne v PHP

Ekvivalenční třída `['delete', 'backspace']` ve dvou průchodech `matchShortcut`
(přesná shoda vyhrává, ať výsledek nezávisí na pořadí `Object.keys()`).
`eventMatchesShortcut` se mění jen na posledním řádku, modifikátorová logika
zůstává.

Proč ne v PHP: zdvojení mapy rozbije `RecordActionTest.php:406` a `:417`
(`->toBe(['Delete' => 'remove'])`), prosákne do veřejného kontraktu
`getRecordActionKeyboardConfig()`, a v legendě by se `Backspace` vypsal jako
samostatný řádek místo „`Delete` / `⌫`". Alias je platformní prezentace, patří
do JS.

### 5.3 `Shift`+`F10` — vlastní `case`, ne matcher

Matcherem mechanicky projde, ale jít tudy nemůže: `kb.shortcuts` je mapa
`klávesa → jméno akce` a volající dělá `run(name)` → `openActionModal`. Otevření
kontextového menu není akce a nemá jméno. Menu je řízené flagem `this.contextMenu`.

Tedy `case 'F10'` s `if (! event.shiftKey) return` a fallthrough do
`case 'ContextMenu'`. Pořadí `case`ů je závazné.

**Neověřené riziko:** prohlížeče na `Shift`+`F10` generují i nativní `contextmenu`
DOM event, který je na `<tbody>` navázaný (`:440-457`) a pro klávesnicově
vyvolané menu má `clientX/Y` = `0,0` — přepozicoval by panel hned po
`openMenuForRow`. `preventDefault()` na `keydown` by to potlačit měl, ale napříč
prohlížeči to není jisté. Ověřit CDP; defenziva je flag `_menuFromKey` zahozený
v `setTimeout(…, 0)`.

**Díra, kterou `Shift`+`F10` zviditelní:** `openMenuForRow` (`:342-347`)
nepřesouvá fokus do panelu. Fokus zůstává na `<tr>`, takže s otevřeným menu
šipky pořád posouvají marker za menu, a `dialogOpen()` panel nezachytí (je to
`role="menu"`, ne `role="dialog"`). U myši to nevadí, u klávesnice je to porušení
APG. Patří do etapy 6.

### 5.4 `PageUp`/`PageDown` — odkud měřit

Ověřený layout: jediný obal je `<div class="relative overflow-x-auto">`
(`index.blade.php:700`), **žádné `max-height`, žádné `overflow-y-auto`, žádný
sticky header** v celém `packages/table/resources/views/`. Reálně tedy scrolluje
stránka → `window.innerHeight`. Implementovat ale přes hledání nejbližšího
vertikálně scrollujícího předka s fallbackem na okno, ať to přežije budoucí
`stickyHeader()`.

**Měřit rozteč, ne výšku řádku.** Mezi navigovatelnými řádky můžou sedět
skupinové hlavičky, mezisoučty a rozbalené sub-rows — ty zabírají obraz, ale
v `navRows()` nejsou. Průměrná rozteč `(last.bottom - first.top) / rows.length`
je započítá, `rows[0].height` ne.

Povinný guard: `if (! pitch || ! viewport) return 1`. Není to teoretické —
`$tableHiddenClass` (`:184`) skrývá desktopovou tabulku na mobilu přes
`display:none`, kde `getBoundingClientRect()` vrací nuly, a `Math.floor(x/0)` =
`Infinity` → `rows[Infinity]` → throw v `activate()`.

### 5.5 `navRows()` beze změny

Ověřeno, co z něj vypadává a proč je to správně: skupinová hlavička
(`group-header.blade.php:3`), mezisoučet (`group-subtotal.blade.php:12`), wrapper
sub-rows (`sub-rows.blade.php:9`), samotné sub-rows (zanořená `<table>`, navíc
bez `data-row-key`), empty-state řádek (`index.blade.php:1058`).

Nové klávesy musí operovat nad **tímtéž** polem `rows`, které `onKeydown` už
spočítal na `:150` — předat ho, nikdy neznovudotazovat.

---

## 6. Etapa 4 — sweep

Precedens je `wireFillHandle` — plnohodnotný Excel fill handle v core, s vlastním
ADR 0023: `packages/core/resources/js/fill/{controller,grid,autoscroll,range}.js`.
Sweep z něj vychází, ale ve třech bodech se odchyluje.

### 6.1 Co znovupoužít

| Kus | Sweep? |
|---|---|
| `createAutoScroller` (`fill/autoscroll.js`) | **ano, 1:1** — je plně generický; promovat do `core/resources/js/support/autoscroll.js` |
| `bodyRows()` + `rowAtY()` (`fill/grid.js:17-23, :100-115`) | **ano** — vytáhnout do `support/table-rows.js`; dnes jsou uvězněné v `createGrid`, které si navíc parsuje `data-fill-columns` |
| tvar `startDrag/onMove/stopDrag` + window listenery + Escape → cancel | ano jako vzor |
| morph guard (`controller.js:38-50`) | **ano, nutně** — bez něj polling uprostřed tažení přemorfuje řádky pod kurzorem |
| `createGrid`, `range.js`, `paint()`, `write()` | ne — fill je 2D s optimistic lockem, sweep je 1D sjednocení klíčů |

Fill během tažení **nepoužívá `elementFromPoint` ani `event.target`** — mapuje
`clientY` na index řádku přes `rowAtY()`. Po `setPointerCapture` se totiž pointer
eventy retargetují a `target` je bezcenný. To převzít.

⚠️ Import z core do table bude **první cross-package JS import v repu**
(`record-actions.js` dnes nemá jediný `import`). Esbuild to vyřeší, ale znamená
to zkopírovaný autoscroller v obou distech a rebuild obou bundlů při změně core.
Do ADR 0024.

### 6.2 Tři odchylky od fillu

**Žádný `preventDefault()` na `pointerdown`.** Fill si to dovolí (táhne
z dekorativního tlačítka mimo tabulku), sweep ne — zabilo by to fokus na
`<button role="checkbox">` (`index.blade.php:950-957`) a rozbilo klávesovou
dostupnost checkboxu.

Místo toho **dvoufázově arm → engage**: `pointerdown` jen zapamatuje výchozí
řádek a navěsí window listenery; teprve první `pointermove`, který změní řádek
pod kurzorem, gesto nastartuje (`body.classList.add('wire-sweeping')`,
`getSelection().removeAllRanges()`, `suppressClick = true`). Do té doby zůstává
klik klikem.

**`click` po tažení musí zabít capture listener.** Fill to neřeší, protože
nemusí — jeho úchyt je absolutně pozicovaný sourozenec `<table>`
(`index.blade.php:1101-1106`), takže click po tažení přistane mimo `<tbody>`.
Sweep tuhle únikovou cestu nemá a dostane dvě rány: `x-on:click="toggle(key)"` na
tlačítku (`:952`) a `@click="onPointer('click', $event)"` na tbody
(`record-actions.js:396`).

```js
// jednou v init(), na selection rootu — capture fáze běží před target fází,
// takže stopPropagation zabije i listener navěšený přímo na tlačítku
this.$el.addEventListener('click', (e) => {
    if (! this.suppressClick) return
    this.suppressClick = false
    e.stopPropagation()
    e.preventDefault()
}, true)
```

Pointer capture tohle neřeší — pokrývá pointer stream, ne kompatibilní `click`.
Použít ho jde (v `try/catch`, jako `controller.js:224-228`), ale jako enhancement.
Pojistka pro `pointerup` mimo dokument: `setTimeout(() => suppressClick = false, 0)`
ve `stopSweep()`; `click` se dispatchuje synchronně, takže se stihne dřív.

**Dotyk mimo.** Tři guardy na `pointerdown`: `pointerType !== 'mouse'`,
`button !== 0`, `! isPrimary`. A hlavně **`touch-action` neměnit** — fillí
`touch-action: none` by na mobilu zablokovalo vertikální scroll v celém
checkboxovém sloupci. Protože sweep dotyk vůbec nezpracuje, není co nastavovat.

### 6.3 Sortable nekoliduje, ale nechává past

Ověřeno ze zdroje (`packages/sortable/resources/views/partials/scripts.blade.php`):
řádkový Sortable má `handle: '.wire-sortable-handle'` (`:88`), **`filter` ani
`draggable` nastavené nejsou**, takže gating dělá výhradně handle — pointerdown
v checkboxové buňce instanci nespustí. Dvojitá pojistka: instancuje se jen
v reorder módu (`:70-71`, entanglované `isReordering`).

**Past:** `addRowDragHandles()` (`:206-215`) **prependuje** `<td>` do každého
řádku z JS po renderu → checkboxový sloupec se posune z indexu 0 na 1. Sweep
proto nikdy nesmí hledat buňku podle pozice (`cells[0]`, `:first-child`,
`nth-child`), výhradně přes `[data-select-cell]`.

### 6.4 Mobilní karty

Nic navíc. Karty nemají checkboxový sloupec (je to `<label>` + `<input>`
v hlavičce karty, `:1198-1206`), `wireRecordActions` na nich vůbec není
(komentář `:1180-1184`). Zároveň desktopová tabulka **zůstává v DOMu** i pod
breakpointem (`$tableHiddenClass`, jen `display:none`), takže listenery existují
dál a `rowAtY()` by se choval nedefinovaně — guard `pointerType === 'mouse'` to
řeší úplně, na mobilu žádný mouse pointer nepřijde.

---

## 7. Etapa 5 — nápověda `?`

### 7.1 Rozdělit legendu na tři vrstvy

Plán má jednu třídu `table/src/Support/ShortcutLegend.php`. Podle
`CLAUDE.md` §Architectural Invariants jsou to tři různá vlastnictví:

| Kam | Co | Proč |
|---|---|---|
| `core/Foundation/Support/ShortcutLabelFormatter.php` | formátování `mod+d` → `⌘D` / `Ctrl+D` | dnes je to **`protected` metoda v traitě** (`HasKeyboardShortcut.php:133`) — business logika v traitě, a nedostupná komukoli mimo objekty s tou traitou; legenda přitom popisuje i `Shift`+`↑`, což žádná `Action` není |
| `core/Foundation/ValueObjects/ShortcutHint.php` | řádek přehledu (`keys`, `label`, `group`) | není to nic table-specifického — command palette, wizard i forms to budou chtít stejně |
| `table/src/Support/TableShortcutLegend.php` | **co** za gesta tabulka má | table-only, plán to sám říká ve `scope:` |

Věcná chyba, kterou to odhalilo: `formatShortcutLabel()` mapuje `'mod' => 'Ctrl'`
natvrdo (`HasKeyboardShortcut.php:140`), což odporuje §2 plánu. Server platformu
nezná → label musí být buď platform-neutral, nebo se `mod` nechá jako token
a přeloží se na klientovi.

Legenda je **data, ne markup** — žádný `render()`/`toHtml()`, ten vlastní modal.
`RecordActionResolver::shortcuts()` (`:143`) vrací jen `klávesa → jméno`, bez
labelů, takže mu přibude bohatší metoda; resolver už `Action` instance drží
(`instancesFor()`, `:174`) a legenda by je jinak resolvovala podruhé.

### 7.2 Modal

Konvence platí a je doložená (`AI_CODING_STANDARD.md:360-397`, reálná použití
`{{ new … }}` v `modal-host.blade.php:65,92,113`, `action-modal.blade.php:53,79,101`,
`select-option-modals.blade.php:26,41`). Správný tvar je
`Modals\Html\Modal(heading:, bodyView: 'wire-table::tables.partials.shortcut-help', bodyData: [...])`.

Bloker z §1.3 se řeší rozšířením kanonického shellu o event-driven otevření
(`openOn:` → `x-data="{ show: false }" x-on:{event}.window="show = true"` jako
alternativa k `@entangle`, když `$wireModel === null`). Je to změna v core, tedy
seam první.

Scope funguje: `action-modal` i `halt-modal` se includují na `index.blade.php:1386`
a `:1389`, tedy uvnitř selection rootu (`:253`–`:1392`), a Alpine `x-teleport`
zachovává scope místa deklarace.

Pozor na zaznamenaný gotcha „stabilní show flag proti Alpine morph-reset" —
ověřit CDP, že Livewire update s otevřenou nápovědou ji nezavře.

### 7.3 Dvě pasti u klávesy

- **`event.key === '?'`, nikdy `code`.** Na CZ klávesnici je to `Shift`+`,`;
  matchování přes `code` by zkratku na neanglických rozloženích tiše zabilo.
- **Zopakovat guard „fokus je na řádku".** Jinak otazník napsaný do vyhledávání,
  inline-edit buňky nebo filtru otevře nápovědu místo toho, aby se napsal.

---

## 8. Etapa 6 — přístupnost

Rozhodovací pravidlo, které v repu platí (a `index.blade.php:919-921` ho má
napsané v komentáři): **binding potřebuje jen atribut, který mezi dvěma
serverovými rendery mění JS.**

| Atribut | Kde | Binding? |
|---|---|---|
| `aria-multiselectable="true"` | `:710`, uvnitř `@if($tableRole)` | **statický** — na holé `<table>` bez `role="grid"` je to neplatné ARIA |
| `aria-selected` na řádku | `:916-926` | **binding** — výběr žije jen v Alpine, statická hodnota by se po morphu vrátila na serverovou pravdu a smazala klik |
| `aria-rowcount` | `:710` | statický |
| `aria-rowindex` | `:714`, `:815` (hlavičkové řádky = 1, 2) a `:916-926` (tělo od `headerRowCount + 1`) | statický, **ale vyžaduje refaktor** |
| `aria-live` region | `:314-315`, hned za otevřením selection rootu | **binding** |

**Refaktor, který plán nezmiňuje:** `$from`/`$to`/`$total` se počítají až
v patičce na `:1337-1341`, tedy po vykreslení tabulky. Pro `aria-rowindex` je
nutné je vyzvednout do preambule k `$recordCount` (`:189`). A `$headerRowCount`
musí odrážet, jestli se rendruje řádek sloupcových filtrů (`$hasColumnFilters`,
`:142`) — jinak indexy o jedna ujedou, jakmile někdo zapne header filtry.

**`aria-live` nesmí být v bulk baru** (`:615`), a to ze tří důvodů: bulk bar je
pod `x-show="selectedCount > 0"` a **skrytý live region neoznamuje**; zmizí při
nulovém výběru, takže „odznačeno vše" by se neohlásilo nikdy; a plurály jsou tam
řešené třemi `x-show` spany, což by ve live regionu udělalo nesmyslné hlášení.
Region musí být v DOM od začátku a prázdný, s jedním `x-text`.

Mobilní karty `aria-selected` **nedostanou** — karta není `role="row"` a bez
zavedení `role="listbox"`/`option` by to bylo neplatné. Mimo scope.

---

## 9. Testovací a verifikační strategie

### 9.1 V repu není žádný JS test runner

Ověřeno: `package.json` nemá vitest ani jest, žádné `*.test.js`/`*.spec.js`.
A coverage gate (`scripts/verify-coverage.php:117`) počítá diff **jen z
`packages/*/src/*.php`**. Tedy:

| Co | V bráně? |
|---|---|
| `selection.js`, `record-actions.js` | **ne** |
| `index.blade.php`, partialy | **ne** |
| `Table.php`, `TableShortcutLegend`, `ShortcutHint`, `ShortcutLabelFormatter` | ano, 100 % změněných řádků |

Největší část téhle práce tedy neprojde žádnou automatickou branou. **CDP driver
není nice-to-have, je to primární testovací nástroj etap 2–4** a musí se pouštět
po každé etapě.

Zavádět vitest v rámci tohohle plánu nedoporučuju — `wireRecordSelection` závisí
na `$wire.entangle` a Alpine reaktivitě, takže by šlo mockovat jen kostru
a reálné bugy (morph reset, deferred commit) by to nechytlo. Za zvážení stojí
jediné: napsat `pageStep`, `rowPitch`, `blockAround` a `snapshotBase` jako čisté
funkce bez `this.$el` — pak by šly otestovat i bez prohlížeče, a je to jediná
netriviální matematika v celé změně.

### 9.2 Testy, které se rozbijí

| Soubor | Co a kdy |
|---|---|
| `WithTablePerformanceTest.php:584-591` | hlídá `entangle('tableState.selection.records')` v HTML — po extrakci literál zmizí. **Etapa 1** |
| `MobileControlsRenderTest.php:118-123` | doslovný tri-state ternár — rozbije se, jakmile se přesune do getteru |
| `verify-record-active-row.mjs:231-233` | „`Shift`+šipka po select-all zmenší blok o jedna" — viz §1.2; s návrhem base-minus-block **projde beze změny**, s doslovným sjednocením spadne |
| `verify-record-active-row.mjs:245` | `ctrl().anchorKey` → kotva se stěhuje; helper `window.sel` (`:70`) extrakci přežije |
| `RecordActionRenderTest.php:130-149` | doslovné `bg-primary-100 dark:bg-primary-900` a `substr_count(':class="{')` — **etapa 6** mění class objekt nebarevným markerem |
| `RecordActionTest.php:400-417` | `->toBe(['Delete' => 'remove'])` — rozbije se **jen** kdyby se `Backspace` řešil v PHP; další argument pro JS alias |

Regresní guard, který se rozbít nesmí: `CanSelectRecordsTest.php` (524 ř.)
definuje serverovou sémantiku, kterou JS kopíruje, a `MobileControlsRenderTest`
hlídá tři konzumenty selection komponenty.

### 9.3 CDP — co v repu chybí

Vzor je `workbench/scripts/verify-record-active-row.mjs` (boot, `waitForDevtools`,
raw WebSocket bez puppeteeru, `Emulation.setDeviceMetricsOverride` místo
`--window-size`, `check()` + exit kód, cleanup ve `finally`).

**Ale: `grep "modifiers" workbench/scripts/` vrací prázdno.** Ani jeden existující
skript nepředává CDP modifikátory a **tažení myší v repu neexistuje vůbec**.
K dopsání je tedy celá modifikátorová vrstva:

```
Alt = 1, Ctrl = 2, Meta/⌘ = 4, Shift = 8      // bitmaska, sčítá se
modBit = isMac ? 4 : 2                         // "mod" z plánu
```

- Klávesy přes `Input.dispatchKeyEvent` s `modifiers`, ne přes syntetický
  `KeyboardEvent` — syntetický nespustí nativní chování, takže nedokáže ověřit,
  že `preventDefault()` opravdu zabránil scrollu u `PageDown` ani že
  `Shift`+`F10` neotevřel prohlížečové menu.
- Tažení: `mousePressed` → N× `mouseMoved` **s `buttons: 1`** → `mouseReleased`.
  Bez `buttons: 1` stránka vidí hover, ne drag; existující skripty posílají
  `button: 'none'`, což je přesně opak.
- Ověřit obě větve `mod` (⌘ i Ctrl), jinak se Mac/Win rozjede.

### 9.4 Preview — 4 řádky nestačí

`TablePreview::usersTable()` má `selectable()`, bulk akce, record actions
i context menu, ale `paginated(false)` (`:511`) a **4 řádky**
(`DatabaseSeeder.php` vytváří přesně čtyři uživatele).

Nesouvislý výběr potřebuje ≥ 16 řádků, `PageUp`/`PageDown` ≥ 30 (jinak se skok
clampne a je nerozlišitelný od `Home`/`End`), a „hranice stránky, ne datové sady"
potřebuje paginovanou variantu.

**Uživatele do seederu nepřidávat.** Workbench má persistentní SQLite, takže by
to rozbilo `verify-mobile-selection.mjs:122` (natvrdo očekává 4 matching),
`verify-record-active-row.mjs:260-264` a docs screenshoty. Správně je nový model
+ migrace + vlastní tabulka (~40 řádků) a dvě varianty
(`selection-gestures`, `selection-gestures-paged`) v `TablePreview`.

Provider měnit netřeba — `TablePreview` už je ve `WorkbenchServiceProvider`
zaregistrovaná a nová *varianta* existující třídy 419 nezpůsobí.

Pro koexistenci se sortable je potřeba `SortablePreview` doplnit `->selectable()`.

### 9.5 DB matice není potřeba

Změna je frontend + markup + in-memory PHP konfigurace, žádné nové SQL. Jediná
výjimka: kdyby `aria-rowcount` sáhlo po celkovém počtu jinak než přes existující
`$recordCount`, přibyl by count dotaz — pak DB matici spustit a ověřit
`MobilePerformanceTest.php:190` a `CanSelectRecordsTest.php:407`, které přesně
tenhle regres hlídají.

---

## 10. Do ADR 0024

1. `mod`, nikdy doslovný `Ctrl` — a **proč**: `Ctrl`+klik je na Macu pravý klik,
   `Ctrl`+šipka je Mission Control (systémová, nedá se `preventDefault`nout).
2. `base ∪ rozsah` kde **base = snapshot mínus souvislý blok kolem kotvy** — a že
   to je to, co smiřuje „nezahodit cizí výběr" se „zmenšit blok po `mod`+`A`".
3. Rozsahová gesta se zapisují jako sjednocení do `selected`; `mode` se nikdy
   nepřepisuje. **V `all` módu tedy rozsah odznačuje** a „souvislý blok" znamená
   blok nevyloučených řádků.
4. `mod`+`A` není rozsahové gesto → jeden `if` na mód je tam správně.
5. Kotva je jednorázová a bez vizuálu; kdyby přibylo gesto, které ji potřebuje
   napříč navigací, vrací se s ním i marker a slaďování s §4.
6. Rozsah gest = jedna stránka.
7. Sweep jen aditivní, jen v checkboxovém sloupci, jen myší; hledání buňky
   výhradně přes `[data-select-cell]` (sortable prependuje `<td>`).
8. `Backspace` je platformní alias v JS, ne položka v PHP mapě zkratek.
9. První cross-package JS import v repu (core → table).
10. Grid semantika (`role="grid"`, tabindex, ARIA) patří `selectable()` tabulkám
    stejně jako tabulkám s record actions.

---

## 11. Revidované pořadí etap

| # | Etapa | Blokuje |
|---|---|---|
| **0** | Předpoklady — grid semantika, `matching`, `INTERACTIVE`, rezervované klávesy | 3, 5, 6 |
| 1 | Extrakce `wireRecordSelection` (+ `entangle` past, dist, drift test) | 2, 4 |
| 2 | Kotva a rozsahy + oprava `mode` v `selectRange`/`selectPage` | 3, 4 |
| 3 | Klávesnice | — |
| 4 | Sweep (+ promoce `autoscroll` a `rowAtY` do `core/support/`) | — |
| **4b** | Seam v core: modal s event-driven otevřením | 5 |
| 5 | Nápověda `?` (+ rozdělení legendy na tři vrstvy) | — |
| 6 | Přístupnost (+ refaktor `$from` do preambule) | — |
| 7 | Docs EN+CZ, boost guidelines, `boost:sync-docs` | — |

Preview infrastruktura (§9.4) musí vzniknout před etapou 2 — bez ní není na čem
nic z toho ověřit.
