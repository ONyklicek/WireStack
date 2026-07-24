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

### Fáze 1 — `RecordAction` + triggery + makro — **HOTOVO (2026-07-23)**

Cíl: fluent binding a obě syntaxe (`RecordAction::make()` i `Action::make()->…`).

| Soubor | Změna |
|---|---|
| `Support/RecordAction.php` | `use HasRecordTriggers`; `__call` deleguje na vnitřní `Action` (setter→wrapper přes `$result === $this->action`, getter passthrough); reference bez akce → `cannotConfigureReferencedRecordAction` |
| `Concerns/HasRecordTriggers.php` | `onClick`/`onDoubleClick`/`onContextMenu`/`on(string)`/`onKey(string)`; `getTriggers()`/`hasTrigger()`; `behaviorOnly()`/`alsoInRowActions()`/`isBehaviorOnly()`/`rendersInRowActions()`; `onKey` stampuje `keyboardShortcut()` vnitřní akce; `abstract getAction()` seam |
| `Support/RecordTrigger.php` | VO (`type` + `key`); konstanty `CLICK/DOUBLE_CLICK/CONTEXT_MENU/KEY`; `type` je volný string (otevřený registr §3) |
| `WireTableServiceProvider` | `registerRecordActionMacros()` v `bootedPackage`: `Action::macro()` pro `onClick`/`onDoubleClick`/`onContextMenu`/`onKey`/`on` → povýší na `RecordAction` |

Testy: `RecordActionTest.php` — 24 zelených (13 nových F1). Pokryto: triggery
včetně custom gesture, více triggerů, key na triggeru, `onKey` stamp i
name-reference větev, `behaviorOnly`↔`alsoInRowActions`, `__call` delegace +
guard, makro-povýšení všech 5 vstupů, promoted binding rovnou do
`recordActions()`. PHPStan level 6 čistý; 200 souvisejících table testů zelených.

> **Poznámka:** makra jsou na `Action` sdílená přes `Macroable` static store i s
> podtřídami (`BulkAction`/`HeaderAction`). `RecordAction::make(string|Action)`
> je odmítne TypeErrorem — record action je koncept řádkové akce, ne bulk/header.

### Fáze 2 — Resolver + runtime (bez nové pipeline) — **HOTOVO (2026-07-23)**

Cíl: trigger → jméno akce → **existující** exekuce; `onContextMenu` sjednotit s row context menu.

| Soubor | Změna |
|---|---|
| `Actions/RecordActionResolver.php` | normalizuje bindingy (`resolve()`); `pointerMap()` (click/dblclick/custom→jméno, bez contextmenu/key), `contextMenuActions()`, `rowActionButtons()`; selection-aware default (dvojklik když `isSelectable()`, jinak klik); memo `resolve()` |
| `Support/ResolvedRecordAction.php` | VO — `name`/`triggerTypes`/`action?`/`rendersInRowActions` |
| `Table.php` | `getRecordActionBindings()` (=pointerMap), `findRegisteredAction()` (kanonický name-lookup přes `getAllActions()`), `getRecordActionInstances()` (fallback pool), `getContextMenuActions()` (merge `rowContextMenu` + `onContextMenu`); `hasRowContextMenu()`/`getRowContextMenuHtml()`/`hasActions()`/`getRowActionsForDisplay()` počítají record akce; resolver memo čištěn v `recordAction`/`recordActions`/`selectable` |
| `Concerns/InteractsWithTableActions.php` | `findAction()` fallback na `getRecordActionInstances()` → behavior-only akce s vlastním callbackem se spustí přes **existující** `executeTableAction`/`openActionModal` (žádná nová pipeline) |

Testy: `RecordActionTest.php` 34 (11 nových F2) + `Feature/RecordActionExecutionTest.php` 2.
Pokryto: pointerMap + selection-aware default (klik↔dvojklik) + „later wins",
reference→tatáž instance, `getRecordActionInstances`, context-menu merge (wrapped
i reference), `alsoInRowActions` v renderu / `behaviorOnly` ne / dedup, `hasActions`,
exekuce behavior-only přes endpoint + `canExecute` guard. PHPStan level 6 čistý;
**celá table sada 1567 zelených**.

> **Pozn. k parametrům callbacku:** action callback resolvuje parametry **podle
> jména** z payloadu (`$record`, `$records`, `$data`, …), ne podle typu — record
> akce dědí tuto konvenci beze změny.

### Fáze 3 — JS controller + bundle + delivery + **přestavba context menu** — **HOTOVO (2026-07-23)**

Cíl: jeden delegovaný controller (klik/dvojklik/pravé tl.); row context menu
přestaven z per-`<tr>` `wireContextMenu` x-data na tentýž controller.

| Soubor | Změna |
|---|---|
| `resources/js/record-actions.js` | `wireRecordActions({bindings, contextMenu})`: delegované `onPointer(type)`/`onContextMenu()`; `row()` = `closest('[data-row-key]')` scoped na `this.$el` (tbody); `blocked(event,row)` = `closest(INTERACTIVE)` **uvnitř řádku**; pointer → `$wire.openActionModal` (modal-aware entry); context menu open/pozice/close centrálně, jeden otevřený panel |
| `package.json` | `build:table-assets` → `packages/table/dist/wire-table-records.js` |
| `WireTableServiceProvider` | `->hasAssets('dist')` + `registerAssetRoutes()` (route `wire-table.asset`, vzor forms) |
| `partials/record-actions-assets.blade.php` | `@assets <script>` s mtime cache-bustem |
| `index.blade.php` | `<tbody>` dostane `wireRecordActions` + delegované `@click/@dblclick/@contextmenu` (podmíněně); per-`<tr>` **ztratil** `wireContextMenu` x-data; panel → `data-record-menu="{key}"` bez Alpine stavu; řádek `cursor-pointer` při pointer bindingu; `@once` record-assets + core floating-assets |

Klíčový gotcha (chycen jen v browseru, ne v Pestu): `INTERACTIVE` obsahuje
`[x-data]`, ale controller root (`<tbody x-data>`) je **předek každého řádku** →
`closest('[x-data]')` vylezl na tbody a blokoval vše. Fix: guard scopovaný přes
`row.contains(hit)` — předek řádku vypadne, interaktivní prvek uvnitř řádku ne.

Testy: `RecordActionRenderTest` (controller jednou, ne per-row; klik/dvojklik
listenery; cursor-pointer; nic bez konfigurace) + `RowContextMenuTest` přepsán na
nový markup (`wireRecordActions`/`data-record-menu`, `assertDontSee wireContextMenu`).
**Browser (Playwright/CDP) ověřeno:** boot (controller na tbody, 4 panely, 0 per-row
x-data, cursor-pointer); dvojklik → potvrzovací modal „Opened …"; reálné pravé
tlačítko → context menu pozicované u kurzoru; guard (checkbox+tlačítko blokované,
prázdná buňka ne). Preview: `/previews/table-record-actions`. Celá table sada 1573 zelených.

### Fáze 4 — Blade zapojení + styling — **HOTOVO (2026-07-23)**

Zapojení `<tbody>` + behaviorOnly render-skip proběhly už v F2/F3; F4 dokončila
řádkové třídy přes kanonického vlastníka + deprecation `rowContextMenu`.

| Soubor | Změna |
|---|---|
| `HasColor::getRowHoverClasses()` | **nový kanonický** hover-only row resolver (plná paleta, `hover:bg-{c}-50 dark:hover:bg-{c}-900/20`, safelist mirror outline-buttonů; neutral default = dnešní gray hover) |
| `Table::getRowClasses()` | `cursor-pointer` když `hasRecordActionPointer()`; neutral hover default, opt-in `recordActionHover()` přes `getRowHoverClasses` (jen u ne-tintovaného clickable řádku; tint si drží vlastní hover) + `hasRecordActionPointer()` helper |
| `index.blade.php` | ad-hoc `cursor-pointer` z `<tr>` odstraněn (teď vlastní `getRowClasses`); dokumentační komentář u mobilních karet (record actions = desktop pointer, na kartách neaktivní) |
| `Table::rowContextMenu()` | **@deprecated → alias** (`Deprecation::method('rowContextMenu', 'recordAction()->onContextMenu', '2.0')`); dál plní tentýž `getContextMenuActions()`, odstranění ve v2.0 |
| `Table::actions()` | `@param` rozšířen o `RecordAction` (guard-only), aby `instanceof` guard byl PHPStan-poctivý |

Testy: 10 nových F4 v `RecordActionTest` (cursor jen u pointer akce; neutral↔`primary`
hover; hover se neaplikuje bez pointer bindingu; tintovaný řádek drží tint hover +
je clickable; rowContextMenu alias). `RecordActionRenderTest` `cursor-pointer` teď z
`getRowClasses`. Core row-tint + safelist testy zelené; **celá table sada 1579**;
PHPStan `Table.php` + `HasColor.php` čisté.

> Deprecation je potlačená `@trigger_error(E_USER_DEPRECATED)` (dedup dle názvu),
> phpunit nemá `failOnDeprecation` → stávající `rowContextMenu` testy nespadnou.

### Fáze 5 — Keyboard navigation + a11y — **HOTOVO (2026-07-23)**

Cíl: grid pattern (roving tabindex), zkratky z akcí, přístupnost.

| Soubor | Změna |
|---|---|
| `RecordActionResolver` | `primaryActionName()` (dblclick > click), `secondaryActionName()` (druhý pointer), `shortcuts()` (`keyboardShortcut()`→jméno, Enter/Space rezervované) |
| `Table.php` | `recordActionKeyboard(?bool)` (null=auto dle `hasRecordActions`), `keyboardNavEnabled()`, `getTableRole()` (grid jen když nav), `getRecordActionKeyboardConfig()` (primary/secondary/shortcuts/selectable/activeClass) |
| `resources/js/record-actions.js` | `initKeyboard()` roving tabindex; `onKeydown()` ↑/↓ + Enter/Shift+Enter (`run()`), Space (selection-aware → `toggleRecordSelection`), ContextMenu klávesa; `matchShortcut()`/`eventMatchesShortcut()` (mod/mac detekce, exact modifiers); `onRowFocus()` adoptuje fokusovaný řádek; `activate()` toggluje tabindex + active class |
| `index.blade.php` | `<table>` `role="grid"` (podmíněně, #2); `<tbody>` `@keydown="onKeydown"` + `@focusin="onRowFocus"` + `keyboard: @js($config)`; `<tr>` `role="row" tabindex="-1"` + `focus-visible:ring-2` jen při nav |

WCAG guard: keyboard nav je auto-on kdykoli existují record actions → **každá**
pointer akce je vždy dosažitelná Enter/Shift+Enter, takže „behaviorOnly bez
klávesnicové cesty" nemůže nastat (guard splněn strukturálně, ne warningem).

Testy: 8 nových config testů (primary=dblclick/secondary=click, shortcuts +
Enter/Space rezervace, auto/force nav, grid role, activeClass/selectable). Render:
`role="grid"`/`role="row"`/`onKeydown`/`tabindex`/focus-ring přítomné jen při nav.
**Browser (Playwright/CDP) ověřeno:** boot (grid, roving [0,-1,-1,-1], config);
↑/↓ posun aktivního řádku (tabindex rove + active class + focus); Enter → primary
„open" modal na aktivním řádku; Space → výběr aktivního řádku (bulk bar); shortcut
matcher (exact modifiers). Celá table sada **1587 zelených**; PHPStan čistý.

> **Pozn. k ARIA:** `role="grid"` + `role="row"` na řádcích; buňky zůstávají
> nativní `<td>` (implicitní gridcell), aby se nemusela sahat každá buňka —
> běžná, přijatelná implementace; hlubší grid pattern lze doladit později.

#### F5+ — Klávesnicový range-select (2026-07-24)

Doplněno na žádost: desktopový výběr více řádků klávesnicí.

| Klávesa | Chování |
|---|---|
| `Space` | Toggle výběru aktivního řádku **+ nastaví kotvu** |
| `Shift`+`↑`/`↓` | Posun aktivního řádku **a** rozšíření souvislého bloku od kotvy |
| `mod`+`A` | Vybrat všechny řádky na stránce |

Klíč: výběr má **jeden zdroj pravdy** — client-side selection komponenta (tutéž
používají checkboxy i bulk bar). Controller ji najde přes nový `data-selection-root`
na wrapperu (`this.$el.closest`) a volá `toggle`/`selected`/`pageKeys`/`queueCommit`
— optimisticky, bez roundtripu per klávesa. `record-actions.js`: `anchorKey`,
`moveActive()` (shift→`selectRange`), `selection()`/`toggleSelection`/`selectRange`/
`selectPage`. Plain šipka kotvu resetuje. Bulk akce zůstávají přes bar (klávesnice
řeší jen výběr). Browser (CDP driver) 14/14 — Space+kotva, Shift-range 3 řádky
(konzistentní s checkboxy + bulk bar „3 records selected"), mod+A celá stránka.

Driver: `workbench/scripts/verify-record-actions.mjs` (11→14 checků).

### Fáze 6 — Docs + boost + katalog — **HOTOVO (2026-07-23)**

| Soubor | Změna |
|---|---|
| `docs/table/record-actions.md` + `docs/cs/table/record-actions.md` | `order: 45`; základní použití, reference, triggery, behaviorOnly/alsoInRowActions, více akcí, keyboard nav, kombinace se selection/bulk, styling, UX, časté chyby, migrace z `rowContextMenu` — bilingválně |
| `packages/boost/.../guidelines/wire-table.blade.php` | record-actions bullet (celý model) + `rowContextMenu` deprecation bullet |
| `packages/boost/.../skills/wire-table-development/SKILL.md` | pravidlo pro record actions + deprecation |
| boost docs bundle | `composer boost:sync-docs` (EN record-actions zrcadleno; 113→114 docs) |
| `AI_COMPONENT_CATALOG.md` | `RecordAction`/`RecordTrigger`/`ResolvedRecordAction`, `HasRecordTriggers`, `RecordActionResolver`, `wireRecordActions` JS |
| preview | `table-record-actions` variant + route (přidáno v F3, browser-ověřeno) |

Ověřeno: `boost:check-docs` clean, `verify-api-docs` OK (104 tříd), `verify-docs`
OK (226 md, 2 locale), **boost sada 218 zelených**.

---

## Stav: **KOMPLETNÍ** (F0–F6, 2026-07-23)

Celý record-actions systém hotový a ověřený: 5 fází kódu + docs. ~90 nových
PHP testů (`RecordActionTest`/`RecordActionRenderTest`/`RecordActionExecutionTest`),
browser (Playwright/CDP) ověření pro dvojklik→modal, pravé tlačítko→menu, guard,
keyboard nav (↑/↓/Enter/Space/shortcuts). Celá table sada **1587 zelená**, PHPStan
level 6 čistý, boost/docs sync clean. Rozhodnutí #1–#5 dodržena; `rowContextMenu`
deprecated jako alias (odstranění v2.0).

### Závislosti a integrační běhy

```text
F0 ─► F1 ─► F2 ─┬─► F3 ─► F4 ─► F5 ─► F6
                └── F3 lze psát paralelně s F2 (JS proti hotovým endpointům)
```

Po fázích měnících runtime/rendering spustit i `vendor/bin/pest … --testsuite
Integration` (state/render/macra) a browser sadu; docs/preview refresh jen když
se dotknou views/preview (checklist body 6–7).
