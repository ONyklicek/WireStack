---
title: V3 — Sjednocené chování (viditelnost, vrstvy core, pojmenování)
date: 2026-11-14
scope: packages/core (Foundation/Concerns, Foundation/Contracts, Actions, Widgets), packages/table (Columns, Filters), packages/forms (HasOptions), docs + upgrade guide
status: PLÁN — nezahájeno. Zadání vlastníka 2026-11-14 („nedostatky zapsat jako plán pro v3, čímž sjednotíme chování")
origin: zbytek vlny D z 2.10.0 — co nešlo udělat bez BC breaku
parent: architecture/plans/v3-optional-admin-and-module-packages.md
---

# V3 — Sjednocené chování

Ve 2.10.0 se sjednotilo všechno, co šlo bez BC breaku (`HasContextualVisibility`
pro akce a filtry, `DatePreset` v core, `FilterOperator::dateLabel()`,
`HostsTableShell`). Tady je to, co bez breaku nejde. Každá položka mění buď
význam veřejné metody, nebo signaturu, kterou aplikace přepisují, a proto patří
do major verze.

Pravidlo pro celý plán: **jeden pojem = jedna metoda = jeden význam na všech
plochách.** Kde dnes stejné jméno znamená na dvou plochách dvě různé věci, v3
jednu z nich přejmenuje. Nenechá je žít vedle sebe.

---

## 1. Viditelnost: jeden model pro všechny plochy

### 1.1 Co je dnes (změřeno proti stromu 2026-11-14)

| Plocha | Vlastník | `visible()` / `hidden()` znamená | Closure dostane |
|---|---|---|---|
| pole formuláře, widget, položka a skupina navigace, tour | `Foundation\Concerns\HasVisibility` | vykreslit / nevykreslit | `$get` a spol. přes `evaluate()` |
| akce, filtr | `Foundation\Concerns\HasContextualVisibility` (od 2.10) | vykreslit / nevykreslit | záznam (akce), nic (filtr) |
| **sloupec tabulky** | `Foundation\Concerns\HasVisibility` | **výchozí stav v přepínači sloupců** — a jen u `toggleable()` sloupce | nic |
| buňka sloupce | `Column::visibleForRecord()` | vykreslit buňku pro daný záznam | záznam |

Tři zjištění, která plán zakládají:

1. **`Column::hidden()` se při vykreslení neuplatní.** `Column::canView()` vrací
   jen `isAuthorized()` (`packages/table/src/Columns/Column.php`) a `isVisible()`
   čte jen `WithTable` a `TableStateSchema` pro výchozí stav přepínače. Sloupec,
   který není `toggleable()`, se s `->hidden()` vykreslí. Vypadá to jako
   nastavené, ale nastavené to není. Je to přesně ten druh chyby, kvůli kterému
   `NavigationShape` a `ChromeSlot` odmítají neplatnou hodnotu.
2. **Stejné jméno, dva významy.** `->visible(false)` na poli formuláře pole
   nevykreslí. Na sloupci ho vykreslí, jen ho v přepínači ve výchozím stavu
   odškrtne. Kdo zná jednu plochu, chybuje na druhé.
3. **Dva traity pro jednu otázku.** `HasVisibility` (closure čte stav formuláře)
   a `HasContextualVisibility` (closure dostane záznam) se liší jen tím, *co*
   closure dostane. To je otázka `evaluate()` a jeho pojmenovaných argumentů, ne
   dvou implementací.

### 1.2 Cíl

- **`visible()` / `hidden()` znamená všude „vykreslit / nevykreslit"**, sloupec
  nevyjímaje. `Column::canView()` = oprávnění **a** `isVisible()`.
- **Výchozí stav přepínače dostane vlastní jméno**: `toggleable(hiddenByDefault: true)`
  (tvar, který znají uživatelé Filamentu), čtené přes `isToggledHiddenByDefault()`.
  `WithTable` a `TableStateSchema` čtou to, ne `isVisible()`.
- **Jeden trait viditelnosti** ve `Foundation\Concerns`: `visible(bool|Closure)`,
  `hidden(bool|Closure)`, `isVisible(mixed $context = null)`, `isHidden(mixed $context = null)`.
  Closure se vyhodnotí přes `evaluate()` s kontextem jako pojmenovaným argumentem
  (`$record`, `$get`, `$state`…). Zůstává pravidlo z `HasContextualVisibility`:
  closure, která chce argument, který nikdo nemá, spadne na statickou hodnotu a
  nevyhodí chybu. `HasContextualVisibility` zanikne a jeho testy přejdou na
  společný trait.
- **Buňka sloupce přes stejné API**: `visible(fn (Model $record) => …)` se na
  sloupci vyhodnotí dvakrát. Strukturálně bez záznamu (hlavička, přepínač,
  export), kde closure se záznamem spadne na `true`, a pak pro každou buňku se
  záznamem. `visibleForRecord()` zůstane ve v3 jako alias s `@deprecated`,
  ve v4 zmizí.

### 1.3 Pasti

1. **Skeleton buňky.** `renderCellFast()` vyhodnocuje viditelnost na každém
   řádku. Closure na záznamu je per-row větev, ne tvar skeletonu. Hlídá to
   `TableRenderCountTest` a nesmí přibýt žádné vykreslení šablony na řádek.
2. **Export a sumáře** dnes respektují `canView()`. Jakmile `canView()` začne
   respektovat `hidden()`, zmizí skrytý sloupec i z exportu. To je správně, ale
   musí to být v upgrade guide jako změna chování.
3. **Uložené pohledy a URL** nesou stav přepínače. Sloupec, který je nově
   `hidden()`, se v uloženém pohledu nesmí znovu objevit. `ColumnLayout::pins()`
   a `widths()` už čtou tabulku „jak je deklarovaná teď", stav přepínače musí
   dělat totéž.

---

## 2. Vrstvy core: vyrovnat dluh v `ModuleLayersTest`

### 2.1 Co zbývá (`coreLayerDebt()`)

| Soubor | Hrany | Proč to ve 2.x nešlo |
|---|---|---|
| `Foundation/View/WidgetGrid.php` | → `Widgets` ×1 (docblock) | mřížka *widgetů* patří do modulu Widgets; přesun mění jméno Blade komponenty |
| `Actions/Concerns/HasModal.php` | → `Modals` ×4, `Infolists` ×1 | akce staví `Modal` / `SlideOver` / `Wizard` a infolist přímo |
| `Actions/Concerns/InteractsWithActions.php` | → `Modals` ×1, `Infolists` ×3, `Notifications` ×2 | `sendActionNotification(Notification $n)` přepisuje wire-table i aplikace, takže změna typu parametru je BC break |

### 2.2 Cíl

- **`Foundation\Contracts\ActionNotification`** (typ, zpráva, volitelně akce):
  implementuje ho `Notifications\Notification`. `sendActionNotification()` a
  `sendNotification()` berou kontrakt. Akce staví notifikaci přes továrnu
  rozhodnutou kontejnerem, takže `Actions` nepojmenuje `Notifications`. Je to
  stejný měkký šev, jaký dnes ukazuje `HasLifecycle::resolveNotificationManagerClass()`,
  jen typovaný.
- **`Foundation\Contracts\PresentsModal`** pro `Modal` / `SlideOver` / `Wizard`:
  `HasModal` drží jen konfiguraci (nadpis, šířka, kroky) a modal z ní staví
  továrna v modulu Modals.
- **Infolist v modalu akce** přes kontrakt `Foundation\Contracts\RendersRecordDetail`
  místo `Infolists\Infolist`. `InteractsWithActions` se ptá kontraktu,
  `RepeatableEntry` a `Entry` zná jen modul Infolists.
- **`WidgetGrid` přesunout do `Widgets/View`.** Alias `<x-wire::widget-grid>`
  zůstane, takže pro aplikaci se nic nemění, jen pro toho, kdo importuje třídu.
- Hotovo, až `coreLayerDebt()` vrátí `[]`. Test pak tu funkci smaže a
  zůstanou jen vědomé výjimky.

---

## 3. Pojmenování: jedno jméno, jeden význam

| Dnes | Problém | V3 |
|---|---|---|
| `Column::searchable()` = hledání tabulky sahá na sloupec; `SelectFilter::searchable()` a formulářový `Select::searchable()` = vyhledávací pole v nabídce; `SelectColumn::searchableOptions()` = vyhledávací pole v nabídce | jedno jméno, dva významy; sloupec si kvůli kolizi musel vzít třetí | `searchableOptions()` na **všech** select plochách (filtr, pole, sloupec); `searchable()` jen pro „hledání tabulky sahá sem". `SelectFilter::searchable()` a `Select::searchable()` jako `@deprecated` aliasy ve v3 |
| `Column::canView()`, `Filter::canView()`, `Action::canExecute($record)` | tři jména pro „smí se to ukázat / spustit" | `canView(mixed $context = null)` všude (oprávnění + viditelnost), `canExecute()` jen u akcí a jen pro „smí se spustit" |
| `Filter::isHidden()` (od 2.10 s kontextem), `Column::isHidden()` (bez kontextu, při vykreslení ignorováno) | viz § 1 | vyřeší společný trait z § 1.2 |

---

## 4. Validace voleb: jedno pravidlo pro všechny volby

Ve 2.10 dostal implicitní `in:` formulářový `Select` / `Radio` s volbami
z enumu **nebo pevného pole** a `SelectColumn` validuje vždy. Volby z closure
zůstaly bez omezení, protože jejich sada může záviset na stavu, který vlastník
nechtěl zmrazit. Rozhodnutí do v3:

- **Validovat i volby z closure** (vyhodnotí se ve chvíli validace se stejným
  stavem jako při vykreslení). Vlastník, který opravdu chce přijmout cokoli,
  napíše `->allowAnyValue()`. Výchozí stav je bezpečný a výjimka je slyšet.
- **Vícehodnotový select** dostane `array` + `*`-pravidlo `in:` místo dnešního
  „bez omezení".
- Tabulkový `SelectColumn`, formulářový `Select` a `BulkEditAction` pak čtou
  jedno pravidlo z jednoho místa (`HasOptions::optionRules()`), ne tři kopie.

---

## 5. Pořadí

| # | Krok | Proč v tomhle pořadí |
|---|---|---|
| 1 | společný trait viditelnosti + `toggleable(hiddenByDefault:)` | zakládá § 3 (`canView` všude) |
| 2 | `Column::canView()` respektuje `hidden()`, `visibleForRecord` → alias | největší změna chování, ať je v upgrade guide první |
| 3 | pojmenování `searchableOptions()` / `canView()` s aliasy | čistě API, staví na kroku 1 |
| 4 | kontrakty core + přesun `WidgetGrid` | nezávislé, ale mění signatury, které aplikace přepisují |
| 5 | validace voleb z closure + `allowAnyValue()` | poslední: může odmítnout data, která dnes projdou |

## 6. Kritéria hotového

- `ModuleLayersTest::coreLayerDebt()` vrací `[]`.
- Nový architektonický test: každá plocha s `visible()` používá **jeden** trait
  a `HasContextualVisibility` neexistuje.
- `ColumnVisibilityTest`: `->hidden()` na nepřepínatelném sloupci ho nevykreslí
  (hlavička, buňka, export), `toggleable(hiddenByDefault: true)` ho vykreslí
  a jen odškrtne.
- `TableRenderCountTest` a `TablePayloadFuseTest` beze změny rozpočtů.
- Upgrade guide `docs/start/upgrading-to-3.md` (EN + CS strukturálně shodné):
  každý řádek tabulek § 1.1, § 2.1 a § 3 s tím, co přepsat. Žádný odkaz na
  ADR ani plán, ty jsou interní.
- Driver sweep zelený. Kde selektor počítal se sloupcem „skrytým jen
  v přepínači", přepíše se na `toggleable(hiddenByDefault:)`.

## 7. Co to vědomě nedělá

- **Nemění zóny, routing ani shell** — to jsou jiné v3 plány.
- **Neslučuje `CanBeDisabled` s viditelností.** „Vidět" a „smět použít" jsou dvě
  otázky (sloupec jde skrýt, ale ne zakázat) a tak to zůstane.
- **Neruší per-row stav buňky přes `disabled(fn ($record))`** — to je
  `InteractsWithRecordDisabledState` a funguje.
