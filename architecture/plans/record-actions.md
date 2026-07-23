# Record Actions (celořádkové akce) — revidovaný návrh

Vlastnící balíček: **wire-table** (runtime + API + rendering). Trigger→akce
binding je table koncept; klávesové zkratky **delegují** na kanonický
`wire-core` `HasKeyboardShortcut`. Kontextové menu **rozšiřuje** existující
`Table::rowContextMenu()`, nezakládá druhé.

Klasifikace dle `AI_CHANGE_PROTOCOL.md`: `table-runtime` (+ tenký dotek
`core-foundation` pouze pokud přidáme sdílený keyboard scope; viz §6).

Cíl: interakce nad **celým řádkem** (klik / dvojklik / pravé tlačítko /
klávesnice) spustí akci nad daným záznamem — jako v desktopové aplikaci. Akce
smí **být totožná** s row action (sdílená), nebo existovat **jen jako chování
bez tlačítka**, takže sloupec akcí lze zmenšit či úplně vypustit („více appka
než tabulka" — viz §2 a §11).

---

## 0. Co v původním návrhu neodpovídá repozitáři (audit)

Původní zadání je dobrý *požadavek*, ale několik bodů koliduje s architekturou
nebo zdvojuje existující kanonické vlastníky. Revize je narovnává:

| # | Původní návrh | Problém v tomto repu | Revize |
|---|---|---|---|
| A | `->onKey('Enter')` jako nové API na akci | `wire-core` už má kanonický `HasKeyboardShortcut` (`keyboardShortcut('mod+s')`, Alpine keydown, Mac detekce, label). Nové `onKey()` = paralelní mini-API — porušuje „jeden kanonický vlastník" (CLAUDE.md). | `->onKey()` je **cukr** delegující na `keyboardShortcut()`. Žádná druhá vrstva mapování kláves. |
| B | `->onContextMenu()` „pro budoucí kontextové menu" | Kontextové menu **už existuje**: `Table::rowContextMenu([...])` + Alpine `wireContextMenu()` + floating assets (`index.blade.php:860`, `Table.php:1632`). | `->onContextMenu()` **plní** existující row context menu záznamem/akcí, nestaví druhý mechanismus. |
| C | „listener pouze na `<tr>`" | Per-`<tr>` `x-data` komponenta na *každém* řádku je přesně to, proti čemu je render-audit (`render-optimization-audit-2026-07-17.md`: minimalizovat per-row práci; per-row `x-data` context menu je uvedeno jako náklad). | **Event delegation**: jeden controller na kořeni tabulky (`wireRecordActions`), listener na `<tbody>`, řádek se najde přes `closest('[data-row-key]')`. 0 per-row Alpine komponent, 0 per-row registrací. Lehčí než původní návrh. |
| D | „interaktivní prvky musí `stopPropagation()`" | Vyžadovalo by editovat každé tlačítko/checkbox/link/dropdown. Křehké, snadno se zapomene. | Delegovaný handler **sám** ignoruje interakci uvnitř interaktivního elementu přes `event.target.closest(INTERACTIVE_SELECTOR)`. Nulová změna existujících buněk. |
| E | „každý typ akcí má vlastní **lifecycle** a vlastní **rendering**" | Record action nemá žádný viditelný výstup (není to buňka ani tlačítko) a nesmí vzniknout druhá exekuční pipeline. | Record action **nemá vlastní lifecycle** — resolvuje trigger→jméno akce a volá **existující** `executeTableAction()` / `openActionModal()` (`InteractsWithTableActions`). Vlastní má jen *resolver triggeru* a *DOM kontrakt na `<tr>`*. |
| F | Klávesnice hardcoduje `Delete`→Delete, `Ctrl+C`→Copy, `Ctrl+D`→Duplicate podle jmen | Hardcoded jména akcí = skrytá konvence, nefunguje pro lokalizované/jiné názvy. | Zkratky se **berou z akcí** (`HasKeyboardShortcut`), ne z jmen. `Delete` funguje, protože daná akce má `keyboardShortcut('Delete')`, ne protože se jmenuje „delete". |
| G | `Action::make('edit')->onDoubleClick()` — triggery jako metody na `Action` | `Action` žije v `wire-core` a sdílí ho `wire-forms`. Trigger řádku tabulky je table koncept; nepatří do **třídy** sdíleného jádra (CLAUDE.md: „nesměšovat surface-specific chování do sdíleného vlastníka"). | Fluent na `Action` **zůstává** — přes **makro** registrované z `wire-table` (`BaseAction` je `Macroable`). Makro `Action` nemění stav, jen ji **povýší** na `RecordAction`. Stav i trigger vocabulary drží `RecordAction` (`HasRecordTriggers`); soubor `wire-core/Action.php` netknutý. Viz §2.3. |
| H | Jednoklik = record action i při `selectable()` | V appce se jednoklik na řádek běžně rovná **výběru**; kolidovalo by. | Při `selectable()` je **výchozí** primární trigger **dvojklik** (jednoklik zůstává výběru). Přepínatelné. Viz §5. |
| I | Accessibility: „žádná degradace oproti HTML tabulce" + dvojklik jako hlavní vstup | Dvojklik/klik na `<tr>` není nativně fokusovatelný ani oznamovaný; funkce jen na dvojklik je WCAG problém. | Klávesnicová/AT cesta k akci **vždy existuje jinudy** (sloupec akcí nebo context menu). Record action je **enhancement**, ne jediná cesta. Grid-pattern roving tabindex, ne přepis table sémantiky. Viz §8. |

Zbytek zadání (oddělení od row/bulk/header akcí, více record actions, výkon,
rozšiřitelnost, testy, docs) revize **zachovává** — jen je ukotvuje do
existujících seamů.

---

## 1. Architektura: binding, ne nová pipeline

Record action = **binding `(trigger) → (akce nad záznamem)`**. Není to nový druh
akce, je to nový *způsob spuštění* akce.

```text
Trigger (click | dblclick | contextmenu | key)
    │  wireRecordActions (1 controller / tabulka, event delegation)
    ▼
RecordActionResolver  (trigger → jméno akce)
    ▼
executeTableAction() / openActionModal()   ← EXISTUJÍCÍ seam
    ▼
executeActionPipeline()                    ← EXISTUJÍCÍ lifecycle
```

Oddělení od ostatních akcí (požadavek zadání) je tím splněné na úrovni
**deklarace a resolveru**, ne duplikací exekuce:

```text
headerActions()  → getHeaderActions()   → vlastní resolver, vlastní render (tlačítka v hlavičce)
bulkActions()    → getBulkActions()     → vlastní resolver, render v bulk baru
recordActions()  → getRecordActions()   → vlastní resolver, ŽÁDNÝ vlastní render (chování na <tr>)
actions()        → getActions()         → vlastní resolver, render ve sloupci akcí
```

Record actions sdílí s row actions jen jedno: **kanonické nalezení a spuštění
akce** (`findAction()` + `executeActionPipeline()`). To je záměr, ne únik —
zabraňuje to druhé kopii lifecyclu.

---

## 2. Veřejné API

### 2.1 Registrace

```php
$table->recordActions([
    RecordAction::make('view')->onClick(),         // jednoklik → view
    RecordAction::make('edit')->onDoubleClick(),   // dvojklik → edit (výchozí primární)
    RecordAction::make('menu')->onContextMenu(),   // pravé tl. → context menu
]);

// jedna akce – zkratka
$table->recordAction('edit');                      // → dvojklik na akci 'edit'
$table->recordAction(RecordAction::make('edit')->onDoubleClick()->action(fn (User $r) => ...));
```

`recordAction(string|RecordAction|Action)` i `recordActions(array)`:

- **`string`** — reference na akci už definovanou v `->actions()` (přes
  `findAction()`); binding dostane výchozí trigger (dvojklik). Tohle je „pouze
  referencovat" ze zadání.
- **`RecordAction`** — plný fluent binding (trigger + volitelně vlastní
  `->action()`, `->icon()`, `->label()`, delegované na obalený `Action`).
- **`Action`** — obalí se do `RecordAction` s výchozím triggerem.

### 2.2 Vztah k row actions — jádro „appka místo tabulky"

Binding nese **jak se akce chová vůči sloupci akcí**:

```php
RecordAction::make('edit')
    ->onDoubleClick()
    ->alsoInRowActions();   // akce je i tlačítko ve sloupci (sdílená)

RecordAction::make('edit')
    ->onDoubleClick()
    ->behaviorOnly();       // NIKDY se nerenderuje jako tlačítko – jen chování řádku
```

Tím se plní uživatelská poznámka *„mapovat na record action danou akci a mít
možnost se z části vyhnout row action"*:

- Řádek se ovládá gestem (dvojklik = edit, pravé tl. = menu, klávesy = zkratky).
- Sloupec akcí lze zúžit na jedno „…" menu nebo `->actions([])` úplně vypustit.
- Přesto zůstává **jedna** definice akce (`edit`), jen s více vstupními body.

`behaviorOnly()` je důležitý: umožní tabulku, kde je viditelných tlačítek
minimum a hlavní interakce je celořádková — bez ztráty přístupnosti, protože
context menu (pravé tl. **i** Context-Menu klávesa) zůstává plnou cestou k akci.

### 2.3 `RecordAction` obal + fluent na `Action` přes makro

`RecordAction` je hodnotový obal ve `wire-table` (`Support/RecordAction.php`)
s traitou `HasRecordTriggers`. Přes `__call` deleguje `->action()`, `->icon()`,
`->label()`, `->visible()`, `->authorize()` (a zbytek Action API) na vnitřní
`Action`, takže po `->onDoubleClick()` má řetěz plnou ergonomii akce — ale
**trigger vocabulary drží obal, ne třída jádra**.

Fluent přímo na `Action` **je podporovaný** (požadavek uživatele). `BaseAction`
je `Macroable`; `wire-table` v service provideru zaregistruje makra, která
`Action` **povýší** na `RecordAction`:

```php
// WireTableServiceProvider::boot() — cross-package seam (jako PluginManager/macra)
foreach (['onClick', 'onDoubleClick', 'onContextMenu'] as $trigger) {
    BaseAction::macro($trigger, fn () => RecordAction::make($this)->{$trigger}());
}
BaseAction::macro('onKey', fn (string $key) => RecordAction::make($this)->onKey($key));
```

Tím platí **oboje syntaxe, jeden držitel stavu**:

```php
Action::make('edit')->onDoubleClick()->action(fn (User $r) => ...);  // makro → RecordAction
RecordAction::make('edit')->onDoubleClick();                          // přímý zápis
```

`wire-core/Action.php` se **nemění** — jediný table dotek je registrace makra.
Makro neukládá žádný stav na `Action` (žádná dynamická property), pouze vrací
`RecordAction`; „dvě API pro totéž" tak nevzniká — je jeden stav (`RecordAction`)
a dvě vstupní syntaxe.

> **Pozor (do testů a docs):** výsledek `Action::make()->onDoubleClick()` je
> `RecordAction`, ne `Action`. Patří do `recordAction()/recordActions()`, ne do
> `->actions()`. `recordAction()` přijímá `string|Action|RecordAction`;
> `->actions()` vloženou `RecordAction` odmítne s jasnou chybou (guard v `actions()`).

---

## 3. Trigger vocabulary a rozšiřitelnost

`HasRecordTriggers` drží **seznam triggerů** (jeden binding smí mít víc, např.
Enter i dvojklik na tutéž akci):

```php
->onClick()            // 'click'
->onDoubleClick()      // 'dblclick'   – výchozí primární
->onContextMenu()      // 'contextmenu' → plní row context menu
->onKey('Enter')       // deleguje na keyboardShortcut('Enter'), scoped na aktivní řádek
->onKey('mod+d')       // → keyboardShortcut('mod+d')
->on('triple-click')   // obecný registr – rozšiřitelnost bez změny API
```

Trigger je **řetězec v otevřeném registru**, ne enum s pevným výčtem. Budoucí
`triple-click`, `long-press`, `swipe`, `drag`, `hover` se přidají jako nový
klíč + handler v JS controlleru, **beze změny veřejného API** (požadavek
zadání „rozšiřitelnost"). Value object `RecordTrigger` (typ + payload) drží
mapování; neznámý typ JS controller bezpečně ignoruje.

---

## 4. JS: jeden delegovaný controller (`wireRecordActions`)

Sdílený bundle (dle ADR 0002 / `build:core-assets` konvence; buď `wire-core`
dropdown bundle, nebo table-specific — viz §12). Jediná Alpine komponenta na
**kořeni tabulky**, ne na řádku:

```text
tbody
 ├── @click        → resolve('click', event)
 ├── @dblclick     → resolve('dblclick', event)
 ├── @contextmenu  → resolve('contextmenu', event)   (jen pokud binding existuje)
 └── @keydown      → keyboard nav + zkratky (§6)

resolve(triggerType, event):
 1. el = event.target.closest('[data-row-key]')            // který řádek
 2. if !el || el mimo hlavní tbody: return                 // sub-rows/group header nemají data-row-key
 3. if event.target.closest(INTERACTIVE_SELECTOR): return  // klik na tlačítko/checkbox/link/input/dropdown
 4. name = bindings[triggerType]                            // trigger → jméno akce
 5. if !name: return
 6. $wire.executeTableAction(el.dataset.rowKey, name)       // nebo openActionModal, dle akce
```

`INTERACTIVE_SELECTOR` (jediné místo pravdy):

```
a[href], button, input, select, textarea, label,
[role="checkbox"], [role="button"], [role="menuitem"], [contenteditable],
[data-record-key] /* editovatelná buňka */, [x-data]
```

Vlastnosti (plní „výkon"):

- **0 per-row listenerů, 0 per-row `x-data`.** Jeden kořenový handler.
- **Žádná změna existujících buněk** — guard je centralizovaný, `stopPropagation`
  na tlačítkách není potřeba (řeší `closest`).
- **DOM kontrakt už existuje**: `<tr data-row-key>` a `data-record-key` na
  editovatelných buňkách jsou v šabloně dnes (`index.blade.php:863`).
- Sub-rows (vnořená `<table>`) a group headery `data-row-key` nemají → čistá
  enumerace, stejně jako u fill-handle (viz `excel-fill-handle.md §1`).

**Modal-safe**: `executeTableAction`/`openActionModal` jsou stávající Livewire
endpointy; stack modalů (`InteractsWithTableActions`) funguje beze změny.

---

## 5. Integrace se selection a bulk

- **Checkbox** je `<button role="checkbox">` → spadá pod `INTERACTIVE_SELECTOR`,
  record action se z něj nespustí. Bulk stav se nemění (požadavek splněn nulovou
  změnou).
- **Kolize klik vs. výběr** (audit H): když `isSelectable()`, výchozí primární
  trigger je **dvojklik**; jednoklik zůstává výběru. `->onClick()` jde nastavit
  explicitně, ale docs varují před kolizí. Bez selection může být klik primární.
- **Bulk actions** se nedotýkáme — record action běží přes `executeTableAction`
  (single-record pipeline), nikdy nesahá na `selection.*` state.

---

## 6. Keyboard navigation (grid pattern)

Roving tabindex na kořenovém controlleru — **žádný per-row `x-data`**:

| Klávesa | Chování |
|---|---|
| `↑` / `↓` | posun `activeRowKey` (roving `tabindex=0`, ostatní `-1`), scroll-into-view |
| `Enter` | primární record action nad aktivním řádkem |
| `Shift+Enter` | sekundární record action (druhý binding), pokud existuje |
| `Space` | při `selectable()` = toggle výběru; jinak primární akce |
| Context-Menu klávesa | otevře row context menu nad aktivním řádkem |
| ostatní zkratky | **z akcí**: každá akce s `keyboardShortcut()` (Delete/mod+c/mod+d/…) se vyhodnotí proti aktivnímu řádku |

`Delete`/`Ctrl+C`/`Ctrl+D` **nejsou hardcoded** (audit F): fungují, protože
`DeleteAction` má `keyboardShortcut('Delete')`, copy/duplicate akce mají své
zkratky. `->onKey('Delete')` na bindingu je jen zkratka, která tuto zkratku
nastaví. Vyhodnocení kláves = **kanonický `HasKeyboardShortcut`** (Mac detekce,
label), controller jen dodá „aktivní záznam" jako scope.

`activeRowKey` je čistě klientský stav (Alpine) — nejde do Livewire, žádný
round-trip při pohybu kurzoru.

---

## 7. UX a styling (vše přepisovatelné)

Třídy resolvuje kanonický vlastník řádku `Table::getRowClasses()` (rozšíří se,
ne nová větev v Blade), aby se korektně skládaly s `rowColor()`/zebra/hover:

- Kurzor: `cursor-pointer` jen když má řádek primární klik/dvojklik trigger.
- Hover: existující `hover:bg-gray-50 dark:hover:bg-gray-700/30` (zadání chce
  `primary` — nabídneme `Table::recordActionHover('primary')`, default = neutrální
  jako dnes, aby se nezměnilo chování existujících tabulek).
- Aktivní řádek: `bg-primary-100 dark:bg-primary-900/30` (klávesnicová navigace).
- Focus: `focus-visible:ring-2 focus-visible:ring-primary-500`.

Vše přebijitelné přes `rowClass()` / `rowColor()` a nové settery
(`recordActionHover()`, `activeRowClass()`).

---

## 8. Accessibility (nedegradovat tabulku)

- **Žádná funkce jen na dvojklik** (WCAG): každá record action má vždy druhou
  cestu — sloupec akcí *nebo* context menu (pravé tl. **i** Context-Menu klávesa
  **i** Enter na aktivním řádku). `behaviorOnly()` proto **vyžaduje** aspoň jeden
  klávesnicově dostupný trigger; jinak build/test warning.
- **Grid pattern**: kořen `role="grid"` opt-in až při zapnuté keyboard nav,
  řádky `role="row"` (nativní), roving `tabindex`. Bez keyboard nav zůstává čistá
  data-tabulka — žádné falešné ARIA.
- **Screen reader**: aktivní řádek `aria-selected` jen pokud existuje výběr;
  jinak jen fokus. Nezavádět `role="button"` na `<tr>` (rozbilo by tabulkovou
  sémantiku) — proto grid pattern, ne „klikací div".
- Aktivní/hover je čistě vizuální; oznámení nese fokus + label akce.

---

## 9. Oddělení a seamy (kam co přijde)

| Vrstva | Soubor | Změna |
|---|---|---|
| API na tabulce | `Table.php` | `recordAction()`, `recordActions()`, `getRecordActions()`, `hasRecordActions()`, `recordActionHover()`, `activeRowClass()` |
| Binding + triggery | `packages/table/src/Support/RecordAction.php` + `Concerns/HasRecordTriggers.php` | value object; deleguje na `Action` |
| Resolver | `packages/table/src/Actions/RecordActionResolver.php` | trigger → jméno akce; reuse `findAction()` |
| Runtime | `Concerns/InteractsWithTableActions.php` | **beze změny exekuce** — jen případný `resolveRecordAction()` helper; spouští `executeTableAction`/`openActionModal` |
| Context menu | `Table::getRowContextMenuActions()` | `onContextMenu()` binding do něj přispěje záznam/akci (sjednocení, ne duplikát) |
| Render | `resources/views/tables/index.blade.php` | atributy + `wireRecordActions()` na kořeni `<tbody>`; `<tr>` beze změny (`data-row-key` už tam je) |
| Row třídy | `Table::getRowClasses()` + `HasColor` | `cursor-pointer` / active / focus třídy |
| JS | `resources/js/record-actions.js` → bundle | delegovaný controller + keyboard nav |
| Klávesy | `wire-core` `HasKeyboardShortcut` | **reuse**, `onKey()` deleguje |

Žádný nový Livewire endpoint — record action jede přes stávající
`executeTableAction`/`openActionModal`.

---

## 10. Testy (mapováno na požadavky zadání)

Pest (PHP) — resolver a binding:

- record action přes `recordAction('edit')` reference vyřeší tutéž akci jako
  `->actions()`;
- `behaviorOnly()` akce se **nerenderuje** ve sloupci akcí, ale existuje v
  `getRecordActions()`;
- `alsoInRowActions()` se objeví v obou;
- `onContextMenu()` binding přispěje do `getRowContextMenuHtml()`;
- výchozí trigger = dvojklik; při `selectable()` primární = dvojklik (ne klik);
- `Action::make('edit')->onDoubleClick()` (makro) vrátí `RecordAction` se
  správným triggerem a delegovaným `->action()`/`->label()`; vložení do
  `->actions()` vyhodí jasnou chybu (guard);
- `onKey('Delete')` nastaví `keyboardShortcut('Delete')` na akci (delegace);
- víc record actions současně; kolize row × record action (sdílená instance);
- `executeTableAction` volaná record cestou projde `canExecute`/authorize.

Browser (Pest browser / CDP — dle memory „SQLite zelená není důkaz"):

- klik / dvojklik / pravé tlačítko spustí správnou akci;
- klik na tlačítko, checkbox, odkaz, editovatelnou buňku a dropdown **nespustí**
  record action (INTERACTIVE_SELECTOR guard);
- ↑/↓ mění aktivní řádek, Enter spustí, Shift+Enter druhou, Context-Menu klávesa
  otevře menu;
- integrace se selection (checkbox jen vybere), bulk beze změny;
- **render výkon**: 0 per-row `x-data` / listenerů — assert jako u
  `TableIconRenderTest` (render-audit §1), počet Alpine komponent na řádcích = 0.

---

## 11. Doporučené vzory (do docs)

1. **App-like tabulka** — `recordAction('edit')->behaviorOnly()` + `recordAction('view')->onClick()->behaviorOnly()` + zúžený/vypnutý sloupec akcí, context menu na pravé tlačítko. Řádek se chová jako položka v Exploreru.
2. **Klasická tabulka + zkratka** — plný sloupec akcí, `recordAction('view')->onDoubleClick()->alsoInRowActions()` jako pouhé zrychlení.
3. **Read-heavy** — dvojklik = detail (`ViewAction` infolist modal), pravé tl. = menu s edit/delete.

Nejčastější chyby (do „common mistakes"):

- Jednoklik jako primární u `selectable()` (krade výběr) — použij dvojklik.
- `behaviorOnly()` bez klávesnicové/context cesty — WCAG; návrh to hlásí.
- Očekávat record action na sub-row nebo group headeru — ty `data-row-key`
  nemají záměrně.

---

## 12. Rozhodnutí (potvrzená 2026-07-23)

| # | Rozhodnutí | Volba |
|---|---|---|
| 1 | Umístění JS controlleru | **Nový `wire-table` bundle** — `packages/table/resources/js/record-actions.js` → vlastní esbuild entry → `packages/table/dist/wire-table-records.js`, injekce přes Livewire `@assets`. Core dropdown bundle zůstává štíhlý. |
| 2 | `role="grid"` | **Jen se zapnutou keyboard nav** — bez record actions/nav zůstane nativní data-tabulka bez ARIA; grid pattern (roving tabindex) se aktivuje podmíněně. |
| 3 | Klávesové zkratky | **`onKey()` je cukr** delegující na kanonický `HasKeyboardShortcut`; žádná druhá vrstva mapování kláves. |
| 4 | Default hover | **Neutrální default** (dnešní `hover:bg-gray-50 dark:hover:bg-gray-700/30`) + opt-in `->recordActionHover('primary')`. Nemění vzhled existujících tabulek (BC). |
| 5 | Trigger API | **`RecordAction` obal jako kanonický držitel stavu + fluent na `Action` přes makro.** `wire-table` registruje `onClick`/`onDoubleClick`/`onContextMenu`/`onKey` jako makra na `Macroable` `BaseAction`, která `Action` povýší na `RecordAction`. Obě syntaxe fungují, stav je jeden, `wire-core/Action.php` netknutý. Viz §2.3. |

### Důsledky pro rozsah práce

- **#1** → nová esbuild entry v `package.json` (`build:table-assets` nebo
  rozšíření stávajícího) + `@assets` injekce v table view (precedent:
  `wire-forms` JS delivery přes route + `@assets`). Table service provider
  registruje asset.
- **#2** → `Table::getTableRole()` / podmíněný atribut ve `index.blade.php`;
  `role="grid"` + roving `tabindex` jen když `hasRecordActions()` s klávesovou
  nav. Bez nav se nepřidává nic.
- **#4** → nový setter `Table::recordActionHover(?string)`; `getRowClasses()`
  přidá `cursor-pointer` jen u řádku s primárním klik/dvojklik triggerem, hover
  třídu jen když je opt-in.
- **#5** → `RecordAction` + `HasRecordTriggers` ve `wire-table`; makra na
  `BaseAction` registruje `WireTableServiceProvider::boot()`. `wire-core`
  `Action` se **nemění**. `recordAction()` přijímá `string|Action|RecordAction`;
  `->actions()` dostane guard proti vložené `RecordAction`.

Návrh je tímto uzavřený a připravený na rozpad do implementačních kroků.

---

## 13. Implementační kroky

Pořadí dle `AI_CHANGE_PROTOCOL` „Cross-Package Change Checklist": **seam dřív než
konzumenti**, úzké testy vlastnícího balíčku první, browser/integration až po
nich. Každá fáze je samostatně mergovatelná a nechá repo zelené. Fáze 0–2 jsou
čisté PHP (žádný JS/Blade), takže je lze psát a testovat bez browseru; fáze 3–5
zapojují runtime.

Coverage gate platí průběžně: každý přidaný řádek pokrytý (`composer
coverage:verify`), žádný floor pod `scripts/coverage-floors.json`.

### Fáze 0 — API na `Table` (seam, bez chování) — **HOTOVO (2026-07-23)**

Cíl: deklarovat a číst record actions; nic se ještě nespouští ani nerenderuje.

| Soubor | Změna |
|---|---|
| `Support/RecordAction.php` | **minimální** VO — `make(string\|Action)`, `getName()`, `getAction()`, `isReference()`. Seam ho type-hintuje, proto vzniká už zde; triggery/`__call`/`behaviorOnly` přidá F1. |
| `Table.php` | `recordAction(string\|Action\|RecordAction)`, `recordActions(array)`, `getRecordActions(): array`, `hasRecordActions(): bool` |
| `Table.php` | `recordActionHover(?string)` + `getRecordActionHover()`, `activeRowClass(?string)` + getter |
| `Table.php` `actions()` | guard: vložená `RecordAction` → `TableConfigurationException::recordActionInRowActions()` (SPL `InvalidArgumentException` báze) |

Reference `string` se **zatím neresolvuje** (jen uloží jméno); resolve řeší fáze 2.
`recordAction()` **appenduje**, `recordActions()` **nahrazuje** celý seznam.

Testy: `packages/table/tests/Unit/RecordActionTest.php` — 11 zelených (VO wrap i
reference, registrace append/replace, guard v `actions()`, hover/active settery
včetně `''→null` větví). PHPStan max čistý; 179 souvisejících table testů zelených.

### Fáze 1 — `RecordAction` + triggery + makro

Cíl: fluent binding a obě syntaxe (`RecordAction::make()` i `Action::make()->…`).

| Soubor | Změna |
|---|---|
| `Support/RecordAction.php` | value object; `make(string\|Action)`; `__call` deleguje na vnitřní `Action`; nese seznam triggerů + `behaviorOnly()`/`alsoInRowActions()` |
| `Concerns/HasRecordTriggers.php` | `onClick`/`onDoubleClick`/`onContextMenu`/`on(string)`/`onKey(string)`; `getTriggers()`; `onKey` deleguje na `keyboardShortcut()` vnitřní akce |
| `Support/RecordTrigger.php` | drobný VO (typ + payload); otevřený registr (rozšiřitelnost §3) |
| `WireTableServiceProvider` | v `bootedPackage`: `BaseAction::macro(...)` pro 4 triggery → povýší na `RecordAction` |

Testy (Unit):
- `RecordAction::make('x')->onDoubleClick()` má trigger `dblclick`;
- `__call` delegace: `->label()/->icon()/->action()/->visible()` prochází na Action;
- makro `Action::make('x')->onDoubleClick()` vrátí `RecordAction` (instanceof);
- `onKey('Delete')` nastaví `keyboardShortcut('Delete')` na vnitřní akci;
- `behaviorOnly()` vs `alsoInRowActions()` flag stav.

### Fáze 2 — Resolver + runtime (bez nové pipeline)

Cíl: trigger → jméno akce → **existující** exekuce; `onContextMenu` sjednotit s row context menu.

| Soubor | Změna |
|---|---|
| `Actions/RecordActionResolver.php` | mapa `trigger → jméno`; primární/sekundární; selection-aware default (dvojklik když `isSelectable()`); reuse `findAction()` |
| `Table.php` | `getRecordActionBindings(): array` (pro Blade `data-*`/x-data); merge `onContextMenu` bindingů do `getRowContextMenuActions()` |
| `Concerns/InteractsWithTableActions.php` | (jen pokud nutné) tenký `resolveRecordAction()` → volá `executeTableAction()`/`openActionModal()`; **žádná nová pipeline** |

Testy (Unit + Feature):
- resolver: `dblclick→'edit'`; primární = dvojklik při `selectable()`, klik jinak;
- `onContextMenu('menu')` se objeví v `getRowContextMenuHtml()`;
- `behaviorOnly()` NENÍ v seznamu renderovaných row actions, ale je v `getRecordActions()`;
- reference `recordAction('edit')` vyřeší tutéž instanci jako `->actions()`;
- exekuce record cestou projde `canExecute`/authorize (`executeTableAction`).

### Fáze 3 — JS controller + bundle + delivery

Cíl: delegovaný listener (klik/dvojklik/pravé tl.), jeden na tabulku.

| Soubor | Změna |
|---|---|
| `resources/js/record-actions.js` | `wireRecordActions()`: `resolve(type,event)` → `closest('[data-row-key]')` + `INTERACTIVE_SELECTOR` guard → `$wire.executeTableAction/openActionModal`; otevřený registr triggerů |
| `package.json` | esbuild entry `build:table-assets` → `packages/table/dist/wire-table-records.js` (vzor `build:core-assets`) |
| `WireTableServiceProvider` | `->hasAssets('dist')` + asset route (vzor `wire-forms`); nebo partial s `@assets` |
| `resources/views/tables/partials/record-actions-assets.blade.php` | `@assets <script src=…>` (vzor `floating-assets.blade.php`) |

Testy (Pest browser / CDP):
- klik/dvojklik/pravé tl. spustí správnou akci;
- klik na tlačítko, checkbox, `<a>`, editovatelnou buňku, dropdown **nespustí** akci;
- sub-row / group header (bez `data-row-key`) akci nespustí.

### Fáze 4 — Blade zapojení + styling

Cíl: napojit controller na `<tbody>`, řádkové třídy přes kanonický vlastník.

| Soubor | Změna |
|---|---|
| `index.blade.php` | root `x-data="wireRecordActions(@js($bindings))"` + delegované `@click/@dblclick/@contextmenu`, **podmíněně** `@if($hasRecordActions)`; `<tr>` beze změny (`data-row-key` už je) |
| `index.blade.php` | render row actions přeskočí `behaviorOnly()` bindingy |
| `Table::getRowClasses()` + `HasColor` | `cursor-pointer` u řádku s primárním klik/dvojklik; hover třída jen když opt-in `recordActionHover()` |
| mobilní karty | rozhodnout: klik/dvojklik na kartě je desktop-pointer koncept; touch používá existující akce/menu — **zdokumentovat**, na kartách record action neaktivovat |

Testy:
- Blade smoke: `data-row-key` + controller root emitován **jednou**, ne per-row;
- **render výkon**: 0 per-row `x-data`/listenerů (assert jako `TableIconRenderTest`);
- `cursor-pointer`/hover třídy jen dle konfigurace.

### Fáze 5 — Keyboard navigation + a11y

Cíl: grid pattern (roving tabindex), zkratky z akcí, přístupnost.

| Soubor | Změna |
|---|---|
| `resources/js/record-actions.js` | `activeRowKey`, ↑/↓ (roving `tabindex`), Enter/Shift+Enter, Space (selection-aware), Context-Menu klávesa; per-akce `keyboardShortcut` proti aktivnímu řádku |
| `index.blade.php` | podmíněně `role="grid"` + roving `tabindex` **jen** při zapnuté keyboard nav (rozhodnutí #2); aktivní řádek přes `activeRowClass()` |
| `Table.php` | `getTableRole()` / flag, zda je keyboard nav aktivní |

Testy (Pest browser + a11y):
- ↑/↓ mění aktivní řádek; Enter primární, Shift+Enter sekundární; Context-Menu klávesa otevře menu;
- `Delete`/`mod+d` fungují přes `keyboardShortcut` akce (ne přes jméno);
- fokus stavy, `role="grid"` jen když nav zapnutá, žádná degradace bez nav;
- `behaviorOnly()` bez klávesnicové/context cesty → build/test warning (WCAG guard).

### Fáze 6 — Docs + boost + katalog

| Soubor | Změna |
|---|---|
| `docs/table/record-actions.md` (+ CZ mutace) | základní použití, více record actions, kombinace s row/bulk, UX, zkratky, best practices, časté chyby (§11) — bilingválně (memory `docs_bilingual_en_cz`) |
| `packages/boost/resources/boost/{guidelines,skills}` | aktualizovat pro nové veřejné API (staleness hook) |
| `AI_COMPONENT_CATALOG.md` | `RecordAction`, `HasRecordTriggers`, `RecordActionResolver`, `wireRecordActions` |
| previews | pokud přidán preview, `npm run build:table-assets` + `verify-preview` (CDP) |

### Závislosti a integrační běhy

```text
F0 ─► F1 ─► F2 ─┬─► F3 ─► F4 ─► F5 ─► F6
                └── F3 lze psát paralelně s F2 (JS proti hotovým endpointům)
```

Po fázích měnících runtime/rendering spustit i `vendor/bin/pest … --testsuite
Integration` (state/render/macra) a browser sadu; docs/preview refresh jen když
se dotknou views/preview (checklist body 6–7).
