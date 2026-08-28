---
title: V2 — kde to stojí a čím pokračovat
date: 2026-08-28
scope: V2.0 (hotová), V2.1 (rozpracovaná), ADR 0025 (rozpracované)
status: progress record — aktualizovat na konci každého běhu
---

# V2: stav a další krok

Jeden soubor, do kterého se dá vstoupit po týdnech a vědět, co je hotové, proč
se některé věci neudělaly a co je na řadě. Detailní odůvodnění jsou v plánech
a ADR, na které se odkazuje.

---

## 1. Hotové

### V2.0 — DataSource kontrakt ✅

Všechny tři podfáze. Exit kritérium splněno: tabulka běží nad `Collection`
zdrojem bez modelu i builderu, Eloquent cesta beze změny chování.
[ADR 0019](../decisions/0019-data-source-contract.md) je ACCEPTED.

Odchylky od plánu a jejich důvody:
[`v2.0-datasource-implementation.md`](v2.0-datasource-implementation.md) § V2.0.c.

### ADR 0025 — vrstvy uvnitř core ✅ (částečně)

[ADR 0025](../decisions/0025-core-module-layers.md): core se **neštěpí** na
balíčky, hranice modulů hlídá `ModuleLayersTest`. Dluh spadl z 19 zakázaných
hran na 12, cyklus `Actions ↔ Modals` je pryč.

**Nedokončené kroky 8 a 10** — viz §3.

### V2.1 — třináct extrakcí

| # | Metoda | Bylo → je | Vlastník |
|---|---|---|---|
| 1 | `validateTableCell` | 46 → 21 | `Services\CellEditPipeline::validateAgainstRecord()` |
| 2 | `resolveTableSummaries` | 54 → 35 | `Services\SummarySet` |
| 3 | `buildSubRowGrandTotalQuery` | 44 → 33 | `Services\SubRowQuery` + `Support\SubRowRelation` |
| 4 | `buildTableQuery` | 62 → 42 | `Services\TableQueryEvents` |
| 5 | `queueChangedRowPartials` | 67 → 47 | `Support\RowStamps` |
| 6 | `queueSatellitePartials` | 46 → 0 | `Support\TablePartials` |
| 7 | `mountWithTable` | **110 → 19** | `Concerns\TableStateSchema::initialFor()` |
| 8 | `updatedTableState` | 59 → 53 | `Support\StateInvalidation` |
| 9 | Table: whole-row interaction | 15 metod | `Concerns\HasRecordActions` |
| 10 | Table: akce — kolekce a prezentace | 27 metod | `Concerns\HasTableActions` |
| 11 | Table: akce na telefonu | 19 metod | `Concerns\CollapsesActionsOnMobile` |
| 12 | Table: stacked karty | 9 metod | `Concerns\StacksOnMobile` + `MobileCard::shapeSignature()` |
| 13 | WithTable: grupování | 4 metody | `Concerns\CanGroupRecords` + `Support\GroupPartitions` — **našlo defekt**, viz §2 |

Plus **tři host kontrakty** — `Contracts\{ShowsTableColumns, ExpandsTableRows,
SummarisesTable}` — které poprvé umožnily testovat render větev bez Livewire
komponenty (DoD 2, částečně splněno).

**Čísla:** `WithTable` 2880 → 2632 → **2486** ř. (99 → 95 metod, 1689 → 1580
řádků v tělech) · `Table` 2935 → 2730 → 2061 → **1880** ř. (190 → **135** metod,
1340 → **891** řádků v tělech).

**Nejdelší metoda v celém souboru má 19 řádků** (`getSubRowCell`). Nad 25 řádků
už není nic — to byla celou dobu ta metrika a je splněná.

Akční cluster je uzavřený: 61 metod ve třech concernech, jak měření předpovědělo.
U karet se ale měření spletlo v druhou stranu, viz §2.

Nic se nezměnilo než umístění — reflektovaný veřejný a protected povrch `Table`
je 295 signatur před i po, bez rozdílu. Jediné, co přibylo, je
`MobileCard::shapeSignature()`, a to je přesun těla, ne nové chování.

Dvě vazby jsou vědomé a pojmenované v docblocích:

- `CollapsesActionsOnMobile` volá privátní `composeRowActions()` a
  `renderEmptyStateActions()` z `HasTableActions`. To je ten smysl — telefon
  ukazuje *tytéž* akce, které složil desktop, takže je nesmí skládat sám.
- `StacksOnMobile` se ptá `MobileCard` na tvar karty místo aby si ho počítal.
  Tvar je vlastnost karty; slot přidaný v `MobileCard` tak nemůže být zapomenut
  v cache klíči.

---

## 2. Co měření změnilo na zadání

Tohle je ta část, kvůli které se plán nedá číst bez tohohle souboru.

**`WithTable` není tenká delegační vrstva.** Jen 20 z 99 metod je do pěti
řádků; 12 metod nad 40 řádků drželo 37 % kódu v tělech. Ale **65 metod je
public a jsou to Livewire endpointy** — `updateTableCell` volá Alpine jako
`$wire.updateTableCell(…)`. Přesunout se nedají; stěhují se **těla**, endpoint
zůstává tenký. Metrikou je *počet řádků v tělech*, ne délka souboru.

**`Table.php` je opak.** 197 z 205 metod public, 93 z nich do pěti řádků, jen
čtyři nad 25 řádků. Je to široký fluent builder — „rozřezat" je špatný rám,
nic se v něm neschovává. Co jde, je grupovat soudržné featury do concernů,
jak už dělá pro data source, grouping, polling, sub-rows a gesta.

**Čtyři „levné" shluky ve `WithTable` jsou dohromady 13 % objemu.** Query cache
je z nich nejhorší kandidát: `generateQueryCacheKey` je jednořádková delegace a
`queryCacheScope` je **zdokumentovaný override hook**. Extrakce by odebrala
rozšiřovací bod a nezmenšila nic.

**Extrakce našla ostrý defekt, a našla ho tím, že se ptala „kdo tuhle memo
zneplatňuje".** `cachedGroupPartitions` se nulovala ručně na pěti místech, která
nulují `cachedRecords` — a jen na třech z nich. Chyběly `setPage()` a
`setTableCursor()`. Stránkování uvnitř jednoho requestu tedy nechalo podsoučty
skupin popisovat **předchozí stránku**: skupina na obrazovce sečetla 0, zatímco
skupina, která už na stránce nebyla, dál ukazovala svoje číslo. Ověřeno na
dvoustránkové tabulce: po `setPage(2)` vracelo `Acme` 300 a `Zeta` (jediná
skupina na stránce) 0.

Oprava není šestý `= null`. `GroupPartitions` si nese **identitu záznamů, které
rozdělil**, takže pravidlo je jedno porovnání na jednom místě místo řádku, který
si musí pamatovat každý volající. Všech pět ručních nulování je pryč. Tohle je
ta třída chyby, kterou soubor `AI_CODING_STANDARD.md` § Adapters popisuje z
druhé strany: rozsypané zneplatňování je duplicitní znalost a rozjede se.

**Odhad počtu metod stárne, jakmile hýbeš sousedy.** §4 psala o „mobilním
shluku (22 metod)". Po kroku 11 jich zbylo **devět**: těch dvacet dva bylo
měřeno, když v `Table` ještě seděl mobilní collapsing, který mezitím odešel do
vlastního concernu. Skutečná práce byla jinde než v počtu — největší metoda
souboru (`getMobileCardSkeleton`, 43 ř.) počítala klíč cache z pěti přístupových
metod cizího objektu. To není délka, to je vlastnictví: tvar karty je vlastnost
karty. Přesun do `MobileCard::shapeSignature()` metodu zkrátil a hlavně zařídil,
že slot přidaný v `MobileCard` nemůže být zapomenut v klíči.

**Grupování odhalí, co testy nehlídají — a je to pokaždé jinde, než čekáš.**
Mobilní collapsing vypadal jako nejrizikovější půlka akčního clusteru (dvě
brány `verify-drivers` jsou právě na něj) a měl **nejhustší pokrytí v celém
souboru**: prahy, klamp na 1, dividery, ne-spustitelné akce, klonování bez
klávesové zkratky, literální breakpoint třídy. Nepokrytá byla nudná půlka —
**chrome akčního sloupce**. `getActionCellSkeleton()` neměl jediný test a
`actionsAlignment()` neměl žádné tvrzení nad vyrenderovaným markupem, přestože
jedno volání musí dojít na dvě místa ve dvou slovnících: `text-*` na hlavičkové
`<th>` a `justify-*` na flex řádek v buňce. Buňka, která centruje tlačítka pod
hlavičkou zarovnanou doprava, prošla všemi branami. Mutace to potvrdila:
zadrátovaný `justify-end` neshodil nic v celém balíčku `table`.

Krok 13 potvrdil, že to platí i mimo `Skeleton`: memoizace rozdělení stránky
**neexistovala z pohledu testů vůbec** — zahodit ji celou prošlo všemi 2264
testy. Nepozorované byly obě půlky: že se cachuje, i že se to zneplatňuje. Druhá
z nich byla přitom rozbitá.

Krok 12 to zopakoval do písmene. `getMobileCardSkeleton` neměl jedinou zmínku
v `tests/` a **plochá memoizace místo klíčované tvarem prošla všemi 2258 testy**
— přitom právě ta klíčovaná je to, co docblok označuje za důvod existence
metody: schovej sloupec a karta se větví jinak. Stejně tak odsazení `pl-12`,
kterým detailní mřížka a řádek akcí obcházejí sloupec s checkboxem; bez něj
visí pod checkboxem a nikde to nepraskne.

---

## 3. Co je vědomě neudělané

| Věc | Proč | Kde |
|---|---|---|
| ADR 0025 krok 8 — Blade coupling (`callInfolistAction` natvrdo ve view) | Patří do [`action-render-unification.md`](action-render-unification.md), jehož fáze 0 je golden-master připnutí markupu; §6.2 tam označuje vizuální deltu za jediné reálné riziko regrese | ADR 0025 |
| ADR 0025 krok 10 — vyříznout `wireFillHandle` z 38 KB bundlu | Samostatný, nezačatý | ADR 0025 |
| ADR 0025 krok 4 — `Trans`/`Deprecation` do Foundation | Zdůvodnění padlo: vrstvy L2→L1 **povolují**, takže by to bylo 33 souborů za nulový přínos pro hranice. Zbývá argument kanonického vlastnictví, ale to je jiný důvod | ADR 0025 |
| `modal-host.blade.php` instancuje `Modals\Html\*` | **Není defekt** — je to výsledek [`rule5-framework-wide-modal-sweep.md`](rule5-framework-wide-modal-sweep.md): framework nesmí záviset na `<x-*>` | — |
| Systematické hledání duplicitních abstrakcí napříč V2 | Průřez auditu padl na session limit. `DataSourceCapabilities`/`CapabilitySet` byl nalezen mimo audit a nejspíš nezůstal sám | [`v2-audit-2026-08-26.md`](v2-audit-2026-08-26.md) §6 |
| `ShellRenderPlan`, `InteractionRenderPlan` — host pořád `mixed` | Polling, live kanál, readiness, přístup ke stavu nemají pojmenovaný kontrakt | [`v2.1-…`](v2.1-monolith-split-implementation.md) §0a |
| `resolveActionType()` — public static, **nula volajících v src** | Nález z kroku 9; plugin API, nebo mrtvý kód. Nerozhodnuto | — |

---

## 4. Co je na řadě

Klesající výnos, seřazeno podle poměru:

1. **V2.1 fáze B — chybějící ERP typy sloupců**: `StatusColumn`, `MoneyColumn`,
   `RelationColumn`, `MetricColumn`. Aditivní, bez BC rizika, a **vidí to
   uživatel** — na rozdíl od všeho výše. Revizí nedotčeno, ověřeno že chybí.

**`Table.php` je hotová.** Nezbyla v ní metoda nad 19 řádků a každý soudržný
shluk má concern. Další práce na ní by už byla přerovnávání, ne úklid.

**`WithTable` — přeměřeno 2026-08-28, seznam v §4 byl neúplný.** Největší metoda
není žádná ze tří, které tam stály, ale `updateTableCell` (**73**), a hned za
sebou `queueRowPartial` (43); obě v seznamu chyběly. Zbytek pořadí:

| Metoda | ř. | Poznámka |
|---|---|---|
| `updateTableCell` | 73 | **Nechat.** `CellEditPipeline` má v docblocku napsáno, že transakci a zámek řádku vlastní *volající* — jedna řádka pod `lockForUpdate()` pro edit, množina pod jednou transakcí pro fill. Další extrakce jde proti doloženému rozhodnutí. |
| `updatedTableState` | 53 | Krok 8, doražené vědomě (59 → 53). |
| `submitHaltModal` | 52 | Čte halt kontext ze stavu a přeposílá podle typu akce. Kandidát na value object nad stavem. |
| `getTableRecords` | 48 | Sekvence hostitelských volání: memo, plugin seam, lazy gate, rehome, eager load. Přesouvat není co. |
| `queueChangedRowPartials` | 47 | Krok 5, hotovo. |
| `queueRowPartial` | 43 | Vázané na `renderPartial()` / `skipIslandsRender()` — hostitelské. |
| `buildTableQuery` | 42 | Krok 4, hotovo. |

Čtyři z osmi největších jsou tedy **už doražené kroky**, které skončily v téhle
velikosti záměrně. Zbývá `submitHaltModal` jako jediný nesporný kandidát, a to
je jedna metoda, ne krok.

Po V2.1 následuje **V2.3** (owner vrstva), jejíž brána je rozhodnutí o vlastníku
`ResourceRegistry` — už padlo, viz `v2.3-…` § R.1.

---

## 5. Jak pokračovat

```
Pokračuj ve V2 podle architecture/plans/v2-progress.md.
Přečti §2 (co měření změnilo na zadání) a §4 (co je na řadě), vezmi první
položku ze §4 a jeď ji stejným postupem jako předchozí kroky: změř to nejdřív,
extrahuj tělo k vlastníkovi, endpoint nech tenký, napiš testy na pravidla,
která byla doteď pozorovatelná jen v prohlížeči, a ověř mutací, že ten test umí
spadnout. Brány podle AI_CHANGE_PROTOCOL.md včetně verify:drivers.
Na konci aktualizuj tenhle soubor.
```

**Nepřeskakuj měření.** V tomhle běhu bylo pořadí kroků třikrát opraveno právě
jím: query cache vypadala jako nejlevnější první krok a byla nejhorší,
`updateTableCell` vypadal na 119 řádků k přesunu a byl už extrahovaný, a
`Table.php` vypadal jako druhý monolit a je to fluent builder.

**Pravidlo, které tenhle běh vynutil:** adaptér se **extrahuje a deleguje**,
nikdy nepíše vedle jako druhá kopie — `AI_CODING_STANDARD.md` § Adapters.
Stálo to dva reálné defekty v jednom commitu.

**Pravidlo z běhu 2026-08-28:** test na „pravidlo pozorovatelné jen v
prohlížeči" nehledej tam, kde je feature nejsložitější — tam už testy jsou,
protože právě tam je někdo psal. Hledej ho **greppem po metodách s nula
zmínkami v `tests/`**. Zabralo to jeden příkaz a našlo to `getActionCellSkeleton`
uprostřed jinak hustě pokrytého clusteru — a v dalším kroku `getMobileCardSkeleton`
úplně stejně. A mutuj **před** psaním testu i po něm: mutace zadrátovaného
`justify-end` proti staré sadě je důkaz, že to pravidlo opravdu bylo nepokryté,
ne jen tvůj dojem.

**Pravidlo z kroku 13:** u každé memoizace se ptej **kdo ji zneplatňuje**, ne
jestli funguje. Zneplatnění rozsypané po volajících je duplicitní znalost a
rozjede se — tady se rozjelo na dvou z pěti míst a nikdo si nevšiml, protože
cache samotná nebyla ničím pozorovaná. Oprava, která drží: ať si memo nese
identitu vstupu, ze kterého vzniklo.

**Dvakrát to byl `*Skeleton`, a to není náhoda.** Zkompilovaný tvar je přesně
to, co Pest vidí jako řetězec a nikdo neasertuje, protože „to je jen markup" —
jenže podmínky zapečené do tvaru (zarovnání, odsazení za checkboxem, klíč cache)
jsou rozhodnutí v PHP se symptomem jen v prohlížeči. **Každý nový `Skeleton`
chce test na svoje zapečené podmínky, hned s sebou.** Zbývající neotestované:
`getSelectionCellSkeleton`, `getRowContextMenuSkeleton`, `getSubRowCellSkeleton`.
