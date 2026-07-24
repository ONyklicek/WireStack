# Excel-like AutoFill (Fill Handle) — implementační plán

Vlastnící balíček: **wire-table** (runtime + API), sdílený JS v **wire-core**.
Klasifikace dle `AI_CHANGE_PROTOCOL.md`: `cross-package` (table-runtime +
core-foundation JS).

Cíl v1: tažením úchytu z pravého dolního rohu editovatelné buňky **svisle**
zduplikovat hodnotu do dalších řádků. Jeden Livewire request až na `pointerup`.
Horizontální fill, obdélníkový výběr, rozpoznávání posloupností a schránka jsou
mimo rozsah, ale návrh je nesmí zablokovat.

---

## 0. Rozhodnutí (potvrzená)

| # | Rozhodnutí | Volba |
|---|---|---|
| A | Zápisová strategie | **1 request → 1 transakce → per-record zápis** stávající pipeline |
| B | Sdílení commit logiky | **Extrahovat `Services/CellEditPipeline`**, `updateTableCell` i fill ji volají |
| C | Vlastnictví JS | **Core bundle**, zdroje rozdělené do `resources/js/fill/*.js` |
| D | Zapnutí | **Opt-in** `Table::fillHandle()`, per-sloupec `->fillable(false)` |

K bodu A: `UPDATE ... WHERE pk IN (...)` obchází `updated_at`, na kterém stojí
optimistic lock (`RecordVersion::stamp()`), přeskočí Eloquent events, casty a
mutátory, a nefunguje pro 4 z 5 větví `CellValueWriter::persist()`
(`saveCallback`, `editableUsing`, pivot, relace). Svislý fill navíc zasáhne jen
vyrenderované řádky, takže `N ≤ velikost stránky`. Požadavek „nikdy neposílat
request na řádek" je splněn; „jeden SQL UPDATE" je vědomě odmítnuto.

---

## 1. Co už existuje a bere se jako dané

**DOM kontrakt** (nic se nepřidává, jen se čte):

| Element | Atributy |
|---|---|
| `<tr>` v hlavním `tbody` | `data-row-key`, `wire:key="row-{key}"` |
| `<td>` | `data-column`, `data-testid="table-cell-{col}"` |
| kořen editovatelné buňky | `data-record-key`, `data-column-name`, `data-server-value`, `data-record-version` |

Group headery (`partials/group-header.blade.php`) ani sub-row řádky
(`partials/sub-rows.blade.php`) `data-row-key` nemají → enumerace je čistá.
Pozor: mobilní karty (`index.blade.php:944`) `data-row-key` duplikují a sub-rows
jsou **vnořené `<table>`** → selektory musí být scopované na `:scope > tr` hlavního
`tbody`.

**Reconciliační kanál**: `wireEditableCell` má `MutationObserver` na
`data-server-value` / `data-record-version` (`dropdown.js:522-533`) volající
`syncFromServer()`, který sám hlídá `saving` a `focused && dirty`. Fill controller
proto s buňkami mluví **výhradně zápisem těchto dvou atributů** — do
`wireEditableCell` nepřibývá žádná fill logika.

**Verzová propagace**: `wire-editable-committed` (`dropdown.js:620`) synchronizuje
verzi sourozeneckým buňkám téhož záznamu. Fill ho musí vyslat za každý zapsaný
záznam, jinak sourozenci drží zastaralou verzi a příští ruční edit falešně
konfliktuje.

**Precedent bulk zápisu**: `WithSortable::reorderRows()` — jeden request, jedna
transakce, každý zápis scopovaný přes `clone $table->getQuery()`. Komentář na
`WithSortable.php:191-196` dokumentuje IDOR, který tím byl opraven. **Stejné
scopování je v fillu povinné.**

**Refresh**: `invalidateTable()` (`InteractsWithTableActions.php:290`) je kanonický
invalidátor. Fill ho **nevolá** — drží se kontraktu `skipRender()`.

---

## 2. Omezení, která tvarují návrh

1. **`skipRender()` je nosný.** Morph by resetoval Alpine stav všech buněk.
   Fill endpoint musí `skipRender()` a vrátit per-record `version` + `value`.
2. **Skeleton splice.** `TextInputColumn::renderEditableCellFast()` (`:747`)
   renderuje partial jednou a `strtr`uje tři sentinely. Zatím není zapojený
   (volají ho jen testy), ale je to deklarovaný směr → **žádný per-record markup
   do cell partialů**. Úchyt je jeden plovoucí element na tabulku.
   Vedlejší efekt: nulový per-row render cost, v souladu s render-cost modelem.
3. **Polling + morph.** `Table::poll()` obaluje tabulku `wire:poll`. Během dragu
   nutné `Livewire.hook('morph.updating', … skip())` + pauza pollingu (vzor
   `packages/sortable/resources/views/partials/scripts.blade.php:35-64, 340-368`).
4. **Tailwind neskenuje `resources/js`** (`resources/css/app.css` má `@source` jen
   na `src` a `resources/views`) → preview třídy v `@once` `<style>` bloku
   partialu, ne Tailwind utility z JS.
5. **`floating-assets` se includuje podmíněně** (`index.blade.php:265, 379, 724`) —
   tabulka s editovatelnými sloupci bez filtrů/toggle/context-menu bundle nedostane.
   Nutná další podmínka na `fillHandle` (a stojí za zvážení i na `editable`).
6. **`canEdit(Model)` má jen `TextInputColumn`, `SelectColumn`, `ToggleColumn`.**
   Generický `Column::editable()` per-record guard nemá — fill to nesmí zhoršit,
   ověřuje se stejným `method_exists` testem jako `updateTableCell`.
7. **`queryCached()` nemá invalidaci po mutaci** — po fillu servíruje stará data do
   TTL. Není to regrese fillu, ale patří do dokumentace featury.

---

## 3. Nalezené preexistující bugy

Nejsou součástí featury, ale fill je zvýrazní:

| # | Popis | Dopad |
|---|---|---|
| B1 | `wireEditableCell.messages` (`dropdown.js:502, 510-514`) přiřazuje samo ze sebe → vždy `undefined`. Všechny `data-msg-*` atributy v partialech se nikdy nečtou. | Při network erroru buňka zobrazí `undefined`. Fill by dědil totéž → **opravit spolu s featurou.** |
| B2 | Verze se v blade partialech počítá z literálu `updated_at`, server používá `RecordVersion::stamp()` přes `getUpdatedAtColumn()`. | Model s přejmenovaným `updated_at` → klient pošle `'0'` → `conflicts()` vrátí `false` → **optimistic lock tiše vypnutý.** Opravit na `RecordVersion::stamp()`. |
| B3 | `canEdit()` a `recordVersion()` duplikované ve třech column třídách. | Kanonický vlastník chybí. Mimo rozsah, zaznamenat. |

---

## 4. Kontrakt na drátě

### Request

```jsonc
// $wire.fillTableCells(fills)
{
  "fills": [
    {
      "column": "status",
      "value": "New",
      "records": { "15": "1718…", "16": "1718…", "17": "1718…" }  // klíč → verze
    }
  ]
}
```

`records` je **mapa klíč → verze**, ne pole: každý řádek má vlastní `updated_at`,
jedna společná verze by lock znefunkčnila. Wrapper `fills` je seznam, takže
horizontální a obdélníkový fill později endpoint nemění — pošlou víc položek.

### Response

```jsonc
{
  "success": false,                       // true jen když prošly všechny
  "results": {
    "status": {
      "15": { "success": true,  "version": "1718…" },
      "17": { "success": false, "message": "…", "conflict": true,
              "currentValue": "Closed", "currentVersion": "1718…" }
    }
  },
  "message": "2 z 3 řádků uloženo"        // agregát pro toast
}
```

Návratový tvar `['success' => false, …]` místo výjimky je vědomý — je to stejný
drátový kontrakt jako u `updateTableCell`, což `god-object-decomposition.md:353-358`
explicitně uvádí jako výjimku z pravidla „nevracej error shape".

---

## 5. Architektura

### 5.1 Backend

```
packages/table/src/
  Concerns/CanFillCells.php        thin host concern — jen Livewire endpoint
  Services/CellEditPipeline.php    ✅ FÁZE 0 — pojmenované fáze jednoho editu
  Services/CellFillWriter.php      ✅ FÁZE 1 — resolve + cap + transakce + iterace
  Support/CellEditOutcome.php      ✅ FÁZE 0 — readonly VO
  Support/CellFill.php             ✅ FÁZE 1 — parsovaný požadavek
  Support/FillResult.php           ✅ FÁZE 1 — per-record výsledky + obálka
  Exceptions/FillRequestException.php   ✅ FÁZE 1
```

**`CellsFilling` / `CellsFilled` nevznikly.** Pipeline už vysílá `CellUpdating` /
`CellUpdated` per záznam — stejné události jako u single editu, takže audit
listener fill vidí bez jakékoli změny. Hromadná obálková událost by byla druhá,
spekulativní sada veřejného API; přidat ji jde kdykoli nezpětnovazebně, odebrat
ne. Vynecháno záměrně, ne opomenuto.

**`CellEditPipeline`** je extrakce těla `updateTableCell` (`WithTable.php:1741-1907`)
bez Livewire vazeb. `updateTableCell` zůstal veřejným endpointem se stejnou
signaturou i návratovým tvarem, jen delegující.

Dvě odchylky proti původnímu náčrtu, obě záměrné:

- **VO je v `Support/`, ne v `DTOs/`.** Adresář `DTOs/` v repu nikde neexistuje;
  value objekty bydlí v `Support/` (`SummaryTarget`, `SummaryFormat`,
  `FilterControl`). Kanonický seznam adresářů ze standardu ustupuje zavedené praxi.
- **Služba má pojmenované fáze, ne jedno `run()`.** Fill potřebuje jinou
  granularitu než single edit: sloupcové fáze jednou, záznamové N×. Jedno `run()`
  by si vynutilo hooky — přesně ten „template method wearing a service's name",
  před kterým varuje `god-object-decomposition.md:316-333`.

Veřejné API, na kterém staví fáze 1:

```php
guard(Column): ?CellEditOutcome                    // isEditable + isAuthorized
dehydrate(Column, mixed $state, ?Model): mixed     // ADR 0021 write-path transform
validateWithoutRecord(Column, string, mixed): ?CellEditOutcome
commit(Column, string, Model $locked, mixed $state, ?string $version): CellEditOutcome
settle(CellEditOutcome, Column, string $hostId, string, mixed $recordKey): void
```

Fill zavolá `guard` + `dehydrate` + `validateWithoutRecord` **jednou na sloupec**
(hodnota je pro všechny záznamy stejná), pak `commit` + `settle` per záznam.
Resolve, zámek a transakci vlastní volající — stejně jako u `CellValueWriter`.

**`CellFillWriter`**:
- resolve `(clone $table->getQuery())->whereIn($table->getPrimaryKey(), $keys)->lockForUpdate()` — IDOR guard;
- kontrola `Table::getFillMaxRecords()` (default 500) → `FillRequestException`;
- jedna `DB::transaction`, uvnitř `array_chunk` po ~200 pro velké sady;
- per záznam volá `CellEditPipeline`, sbírá `CellEditOutcome`;
- **částečné selhání transakci neruší** — konflikt na jednom řádku nesmí zahodit
  ostatní. (Rozhodnutí: pipeline chyby jsou per-record výsledky, ne throw.)

**`CanFillCells::fillTableCells(array $fills): array`** — `skipRender()`, validace
tvaru payloadu, `findColumn` + `isEditable()` + `isFillable()` + `isAuthorized()`
per sloupec, `event(CellsFilling)` / `event(CellsFilled)`, delegace do writeru.

### 5.2 Fluent API

```php
// Table.php
->fillHandle(bool $condition = true)   ->isFillHandleEnabled(): bool
->fillMaxRecords(int $max)             ->getFillMaxRecords(): int

// Column.php
->fillable(bool $condition = true)     ->isFillable(): bool   // default true když isEditable()
```

Každý fluent setter potřebuje jednořádkový docblock — hlídá to
`tests/Feature/FluentApiDocumentationTest`.

### 5.3 Frontend

```
packages/core/resources/js/fill/
  grid.js         CellGrid   — DOM adapter: rows(), columnIndex(), descriptorAt(row, col)
  range.js        FillRange  — VO {anchor:{row,col}, focus:{row,col}}; v1 klampuje col
  autoscroll.js   AutoScroller — rAF edge-scroll (v repu neexistuje, píše se od nuly)
  controller.js   wireFillHandle — pointer state machine + transport
```

`dropdown.js` je importuje a registruje `Alpine.data('wireFillHandle', …)` ve
stávajícím `alpine:init` bloku. Bundle zůstává jeden soubor, zdroje ne.

`FillRange` drží `{row, col}` už v v1 — horizontální fill pak znamená jen přestat
volat `clampToColumn()`, ne přepis.

**Úchyt sleduje hover, ne výběr — a to je vědomá odchylka od Excelu.** Excel věší
úchyt na *vybranou* buňku; výběr je lepivý, takže se úchyt nikdy nehne, když po
něm uživatel sahá. Hover je ze své podstaty nestabilní: cíl se pohybuje, zatímco
k němu miříš. Zvoleno kvůli objevitelnosti (klonovat jde bez předchozí editace) a
nestabilita je dorovnána dvěma prvky, které bez hoveru nejsou potřeba:

- `GRAB_RADIUS = 18` — dokud je kurzor do 18 px od středu úchytu, nepřemísťuje se.
  Bez toho úchyt uskočí o řádek níž přesně ve chvíli, kdy po něm sáhneš, protože
  poslední pixely přiblížení vedou přes buňku pod ním. Nedal se chytit.
- `HANDLE_INSET = 12` — úchyt sedí uvnitř buňky, ne na hranici řádků, takže
  přiblížení shora ji vůbec nepřekročí. 12 proto, že rozbalené tlačítko má 20 px
  a při 10 px by se dotýkalo okraje buňky.

Pokud by se nestabilita ukázala jako problém, přechod na excelovský model je malý:
odebrat posluchač `pointerover`, `GRAB_RADIUS` a nechat `onFocusIn` jako jediný
zdroj výběru.

**Requesty jsou serializované, optimistický zápis ne.** Reportovaná chyba: druhý
fill odeslaný dřív, než dorazila odpověď prvního, nesl verze, které DOM držel
*před* prvním zápisem — server ho správně odmítl jako zastaralý a poslední tah
uživatele se tiše vrátil. `write()` proto řetězí requesty přes `_pending` a verze
čte až v `send()`, kdy už předchozí fill ty své vrátil. Bez toho symptom seděl
přesně na hlášení: „zapíše jen poslední správně" (ověřeno sabotáží — dá
`["editor","editor","viewer"]`).

**Proč to bylo „občas": zámek má vteřinové rozlišení.** `RecordVersion::stamp()`
je `updated_at` jako unixový timestamp, takže dva zápisy uvnitř jedné vteřiny jsou
nerozlišitelné a zastaralá verze *neprojde* jako konflikt. Jestli byl předčasný
druhý fill odmítnut, záviselo na tom, zda oba requesty spadly přes hranici
vteřiny. Zdokumentováno testem `it('cannot tell two writes apart inside the same
second')`, ne opraveno: sub-vteřinové razítko by změnilo `RecordVersion` pro
všechny povrchy včetně panelů a spousta schémat stejně ukládá `timestamp`
s vteřinovou přesností.

Tři věci, které se ukázaly až při psaní:

- **Posluchače pointeru jsou na `window`, ne na úchytu.** `setPointerCapture` je
  vylepšení, ne podmínka: když selže (nebo ji prohlížeč odmítne), drag by se
  s posluchači na úchytu rozpadl v momentě, kdy kurzor element opustí. Volání je
  navíc v `try/catch` — na syntetickém pointeru vyhazuje `NotFoundError`.
- **Morph guard je jeden na stránku, ne na tabulku.** Livewire `hook()` nejde
  odregistrovat, takže registrace v `init()` by stackovala nový hook při každé
  reinicializaci tabulky. Modulový `Set` běžících controllerů řeší totéž.
- **Verzi je nutné nastavit i přímo na Alpine stavu.** MutationObserver
  v `wireEditableCell` re-syncuje jen když se změní *hodnota* — vyplnění buňky,
  která už hodnotu měla, by ji nechalo na staré verzi a její příští ruční edit by
  konfliktoval proti řádku, který sám zapsal.

**Stavový automat controlleru:**

```
idle ──pointerdown na handle──▶ dragging ──pointermove──▶ preview(range)
                                    │                          │
                                    │ Escape / pointercancel ──▶ idle (bez zápisu)
                                    └── pointerup ──▶ committing ──▶ idle
```

- `setPointerCapture` na handle → drag přežije opuštění elementu (myš, touch i pen jednou cestou).
- během dragu: **žádné `$wire` volání, žádná změna dat**; jen třídy na cílových `<td>`.
- `Livewire.hook('morph.updating', ({skip}) => this.dragging && skip())` + pauza `wire:poll`.
- na `pointerup`: snapshot starých `data-server-value`/`data-record-version`, optimistický zápis nových, jeden `$wire.fillTableCells(...)`, po odpovědi per-record potvrzení nebo rollback ze snapshotu + `wire-editable-committed`.

**Aktivní buňka**: `focusin` delegovaný na tabulku; pokud cíl leží uvnitř
`[data-record-key][data-column-name]` a sloupec je fillable, úchyt se přepozicuje.

### 5.4 Markup

`packages/table/resources/views/tables/partials/fill-handle.blade.php` — jeden
`<button data-testid="table-fill-handle">`, jeden overlay rámeček, a `@once`
`<style>` blok. Includováno jednou pod `<table>`, ne v řádku.

Rozdělení stylů: úchyt a overlay nesou **Tailwind třídy** (Blade se skenuje, takže
dědí `primary` konzumenta a `dark:` varianty zdarma). V `<style>` bloku je jen to,
co nasazuje JS — `.wire-fill-target`, `body.wire-filling` — protože Tailwind
`resources/js` neskenuje. `.wire-fill-target` má dvě deklarace `background-color`:
fallback a `color-mix()` s `var(--color-primary-500)`.

**Konfigurace jde přes `data-*` na kontejneru, ne přes `x-data`.** `data-fill-root`,
`data-fill-columns`, `data-fill-max` na `<div class="relative overflow-x-auto">`.
Kdyby fáze 2 zapsala `x-data="wireFillHandle(…)"`, Alpine by do fáze 3 na každé
tabulce házel `wireFillHandle is not defined` a rozbil inicializaci celého
podstromu. Takhle je každý mezistav zelený a JS si config přečte z `dataset` —
stejný vzor, jakým `wireEditableCell` čte `data-record-key`.

Úchyt je `<button>` s `tabindex` → dostupný i klávesnicí. (Ctrl+D / Shift+Down jako
budoucí rozšíření; v1 stačí Escape.)

---

## 6. Fáze

| # | Obsah | Hotovo když |
|---|---|---|
| ~~**0**~~ | ~~Extrakce `CellEditPipeline`; `updateTableCell` deleguje. Bez změny chování.~~ | ✅ **HOTOVO** — 1341 table + 34 integration zelených, lint/PHPStan čisté, nové soubory 100 % pokryté, mutace ověřena |
| ~~**1**~~ | ~~`CanFillCells` + `CellFillWriter` + `Table`/`Column` API + lang EN/CS~~ | ✅ **HOTOVO** — 14 feature + 4 E2E testů, nové soubory 100 %, IDOR/cap/opt-in ověřeny sabotáží |
| ~~**2**~~ | ~~Blade: fill-handle partial, mount, oprava podmíněného `floating-assets`~~ | ✅ **HOTOVO** — 8 render testů + O(1) fuse; preview `table-editable-fill`; ověřeno v prohlížeči (bez chyb konzole, bez regrese layoutu) |
| ~~**3**~~ | ~~JS moduly + registrace + build + commit distu~~ | ✅ **HOTOVO** — `grid`/`range`/`autoscroll`/`controller`, `DropdownAssetTest` +5 tvrzení |
| ~~**4**~~ | ~~Reconciliace přes data-atributy, rollback, `wire-editable-committed`~~ | ✅ **HOTOVO** — součást controlleru; ověřeno v prohlížeči (0 requestů při tažení, 1 po puštění, persistence, Escape, toggle jako `boolean`) |
| ~~**5**~~ | ~~Workbench preview + `verify-fill-handle.mjs` + MySQL/Postgres matice~~ | ✅ **HOTOVO** — driver 20/20; CI matice (513 testů) zelená na Postgres 16 i MySQL 8 |
| ~~**6**~~ | ~~Docs EN + CZ, `composer boost:sync-docs`, ADR, coverage floors~~ | ✅ **HOTOVO** — `docs:check` 224 souborů, boost bundle 112, ADR 0023; floors beze změny (table 86,9 % nad podlahou 86) |
| **B1/B2** | Oprava `messages` bugu a `RecordVersion::stamp()` v partialech | Vlastní testy |

---

## 7. Testovací matice

**Unit**
- `CellEditPipeline` — parita s původním `updateTableCell` na všech větvích (validace, canEdit, konflikt, dehydratace, 5 zápisových strategií).
- `CellFillWriter` — scopování přes base query, cap, chunking, částečné selhání nezruší ostatní.
- `Table`/`Column` fluent API + docblock guard.

**Feature** (`packages/table/tests/Feature/FillHandleTest.php`, vzor `BulkActionExecutionTest`)
- happy path text / select / toggle
- needitovatelný sloupec, `fillable(false)`, neautorizovaný sloupec
- per-record `canEdit()` odmítnutí (jeden řádek disabled)
- selhání validace
- **konflikt verze na jednom řádku** — ostatní se uloží
- **IDOR: klíč mimo base query se nezapíše**
- překročení `fillMaxRecords`
- `skipRender()` proběhl

**Integration** (`tests/Integration/FillHandleEndToEndTest.php`, vzor `EditableColumnsEndToEndTest`)
- celý stack přes `Livewire::test()->call('fillTableCells', …)`

**Asset / render**
- `DropdownAssetTest` — `wireFillHandle`, `fillTableCells`, `setPointerCapture`
- render fuse — fill nesmí přidat per-row view render

**CDP** (`workbench/scripts/verify-fill-handle.mjs`, vzor `verify-swipe.mjs` + `verify-panel-editable.mjs`)
- pointerdown → move → up přepíše správné buňky
- **během dragu nula network requestů**, po pointerup právě jeden
- preview třídy sedí na cílových `<td>`
- Escape zruší bez zápisu
- touch cesta (`TouchEvent` sekvence)
- auto-scroll u kraje viewportu

**DB matice**: fill testy musí projít i na MySQL 8 a Postgres 16
(`database-tests.yml`), ne jen na SQLite.

Dvě chyby, které matice odhalila a SQLite ne:

- **Neuspořádaný `pluck()` v testu.** Postgres vrací fyzické pořadí řádků, a fill
  je přepisuje — takže aktualizovaný řádek se přesune a `toBe()` proti literálu
  selže na pořadí klíčů, ne na hodnotách. Testy musí řadit explicitně.
- **Lokální MariaDB na 3306 přebíjí kontejner.** Při ručním spouštění matice
  mapovat MySQL na volný port (`-p 3308:3306`), jinak testy tiše míří jinam a
  padají na „Access denied", což vypadá jako chyba kódu.

---

## 8. Soubory

**Nové**
```
packages/table/src/Concerns/CanFillCells.php
packages/table/src/Services/CellEditPipeline.php
packages/table/src/Services/CellFillWriter.php
packages/table/src/Support/CellEditOutcome.php
packages/table/src/Exceptions/FillRequestException.php
packages/core/src/Core/Events/CellsFilling.php
packages/core/src/Core/Events/CellsFilled.php
packages/core/resources/js/fill/{grid,range,autoscroll,controller}.js
packages/table/resources/views/tables/partials/fill-handle.blade.php
packages/table/tests/Feature/FillHandleTest.php
packages/table/tests/Unit/Services/{CellEditPipelineTest,CellFillWriterTest}.php
tests/Integration/FillHandleEndToEndTest.php
workbench/scripts/verify-fill-handle.mjs
docs/table/columns/fill-handle.md
docs/cs/table/columns/fill-handle.md
architecture/decisions/0023-cell-fill-handle.md
```

**Mimo rozsah, nalezeno po cestě a opraveno:** `composer api:instanceof` padal na
`TableQueryService.php:444` — guard hledal `instanceof` regexem v surovém zdroji,
takže ho našel i v komentářích. `scripts/verify-instanceof-imports.php` teď
operand čte z `token_get_all()`; zahodí přesně dva falešné poplachy (`list`
v komentáři a `FileUpload` v doc bloku `FileUpload.php:544`) a zachová všech 490
skutečných cílů. Ověřeno sabotáží: odstranění importu `DehydratesState`
z `WithTable` guard chytí — tedy přesně bug z jeho vlastního docblocku.

**Změněné**
```
packages/core/resources/js/dropdown.js          import + registrace + oprava B1
packages/core/dist/wire-core-dropdown.js        rebuild, commitnutý
packages/table/src/Table.php                    fillHandle(), fillMaxRecords()
packages/table/src/Columns/Column.php           fillable(), isFillable()
packages/table/src/Concerns/WithTable.php       use CanFillCells; updateTableCell deleguje
packages/table/resources/views/tables/index.blade.php   mount + include + floating-assets
packages/table/resources/views/tables/columns/{text-input-editable,select,toggle}.blade.php   B2
packages/table/lang/{en,cs}/messages.php
packages/core/tests/Feature/DropdownAssetTest.php
workbench/app/Livewire/Previews/TablePreview.php
workbench/routes/web.php
workbench/src/WorkbenchServiceProvider.php       ⚠ nová preview komponenta MUSÍ být v foreach
scripts/coverage-floors.json                     jen pokud stoupnou
```

---

## 9. Rizika

| Riziko | Mitigace |
|---|---|
| Kolize názvu metody v `WithTable` trait složení (`insteadof` blok je nosný; kolize = fatal) | Před přidáním `CanFillCells` projet názvy metod proti všem složeným traitám |
| Morph / polling přepíše DOM během dragu | `morph.updating` skip + pauza pollingu, vzor sortable |
| Sub-rows a grouping v `tbody` | Scopovaný selektor `:scope > tr[data-row-key]`; sub-rows jsou vnořené `<table>` |
| Mobilní karty duplikují `data-row-key` | Fill je vázaný na `<table>`, ne na `[data-row-key]` globálně; na kartách vypnutý |
| Extrakce pipeline rozbije `updateTableCell` | Fáze 0 samostatně, bez změny testů; parita ověřena existující sadou |
| `TableBenchmarkTest` je časově citlivý | Přeměřit před interpretací |
| Coverage gate | Testy psát s kódem, ne potom |
