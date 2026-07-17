---
title: Sticky actions column (frozen column, v1 = akce)
date: 2026-07-14
scope: packages/table (+ ověření proti packages/core/resources/js/dropdown.js)
status: plan (nezahájeno)
related:
  - architecture/table.md
  - architecture/plans/v1-gaps.md
  - memory/floating_dropdown_architecture_2026_06_24.md
---

# Sticky actions column

Sloupec s akcemi zůstane přilepený k hraně tabulky při vodorovném scrollu.
Volitelná funkce, defaultně vypnutá.

Dnes v repu **neexistuje** — `Column` nemá `sticky()` / `frozen()` / `pinned()`
a jediné výskyty „sticky" v `packages/table` jsou `stickyHeader` / `stickyFooter`
u modalů, což je nesouvisející věc.

## Výchozí stav (ověřeno v kódu 2026-07-14)

- **Scroll kontejner:** `packages/table/resources/views/tables/index.blade.php:531`
  → `<div class="overflow-x-auto {{ $tableHiddenClass }}">`. `position: sticky`
  v něm funguje — *pokud* nad ním není žádný ancestor s `overflow: hidden`.
  **První věc k ověření**, jinak celý návrh padá.
- **Buňka s akcemi:** `index.blade.php:775` a dál — bespoke `<td>` renderované
  z pole `$actions`, řízené `$hasActions`, `$actionsPosition` (`start` / `end`)
  a `$actionsJustifyClass`. **Není to `Column` instance**, takže samotné
  `Column::sticky()` by ji nepokrylo.
- **Řádek má kanonického vlastníka tříd:**
  `<tr class="{{ $table->getRowClasses($record, $rowIndex) }}">`, který skládá
  i tint přes `HasColor::getRowTintClasses()` (`Table.php:1225+`). Výběr se
  přidává zvlášť Alpinem: `:class="isSelected(…) ? 'bg-primary-50 dark:bg-primary-900/20' : ''"`.

## API

Jeden sdílený vlastník sticky sémantiky (strana + offset + třídy) v
`packages/table/src`, který konzumují dva vstupy:

- `Table::stickyActions()` — **v1 dodat tohle**, je to ta volitelná funkce.
- `Column::sticky()` — obecné frozen columns, **v1 nedodávat**, ale vlastníka
  navrhnout tak, aby šlo doplnit bez přepisu.

Sticky je inherentně tabulková věc (mrazíš sloupec ve vodorovném scrollu), takže
vlastník patří do `table`, ne do `Foundation` — tam by neměl druhého konzumenta.
Viz invariant „kanonický vlastník v nejnižší vrstvě, která ho *může* vlastnit"
v `CLAUDE.md`.

## Nejtěžší část: pozadí

Sticky buňka je defaultně **průhledná** → pod ní projíždí obsah ostatních
sloupců. Nejčistší řešení je `background: inherit` na `<td>` a garantované
neprůhledné pozadí na `<tr>`.

Práce je v tom, že `getRowClasses()` musí vracet **konkrétní** pozadí pro
**každý** stav — base, striped (sudý i lichý), hover, selected, tint. Pokud dnes
některý stav pozadí nemá (pravděpodobné u nestripovaného base řádku),
`bg-inherit` zdědí průhlednost a rozbije se to.

Řešit **rozšířením kanonického vlastníka** (`getRowClasses`), ne novou paralelní
logikou v Blade. Pozor na `selected`, který dnes chodí přes Alpine `:class` —
musí skončit na `<tr>`, aby ho `bg-inherit` na `<td>` zdědil.

## Rozsah v1

Jen **edge-pinned**: akce na `end` → `right: 0`, na `start` → `left: 0`.

Kumulativní offsety pro N sticky sloupců potřebují měřit šířky za běhu (nebo je
dopočítat z `columnMeta`) — to je až v2 spolu s `Column::sticky()`.

## Vazba na vrstvení dropdownů

Sticky buňka = `position: sticky` + `z-index` = **vytváří stacking context**, a
trigger row-action dropdownu bude sedět uvnitř ní.

Dnešní `floorZ` v `floatingAnchor()` (`packages/core/resources/js/dropdown.js`)
to má pokryté: `layerAbove()` najde `z-10` sticky buňky, ale podlaha z vlastní
`z-50` třídy panelu vyhraje, takže panel zůstane na 50. Bez té podlahy by sticky
sloupec vrstvení rozbil (panel by spadl na `z-11`).

**Chce to explicitní CDP check, ne důvěru v tenhle odstavec.**

## Ověření (v prohlížeči, ne Pestem)

Pest tohle nechytí — stejně jako nechytil, že teleportovaný panel mizí pod
stacknutým modalem (viz `workbench/scripts/verify-modal-layering.mjs`). Sticky
selhání je čistě vizuální/hit-test záležitost.

Nový driver, vzor `verify-modal-layering.mjs`:

1. Při vodorovném scrollu zůstane `actionsCell.getBoundingClientRect().right`
   přilepený k hraně kontejneru, zatímco běžná buňka se posune.
2. **Hit-test** na tlačítko akce během scrollu — že pod ním neprojíždí jiný
   sloupec (`elementFromPoint` musí vrátit tlačítko, ne cizí buňku).
3. Otevřený dropdown v sticky buňce zůstane ukotvený (`autoUpdate`) a nad vším;
   panel drží `z-50` (viz podlaha výše).
4. Stavy pozadí: striped × hover × selected × tint × bordered — že sticky buňka
   nikdy neprosvítá.
5. Interakce: column toggle, sub-rows, grouping rows, summary footer,
   responsive skryté sloupce, mobil.

Preview: nová varianta v `workbench/app/Livewire/Previews/TablePreview.php`
(dost sloupců, aby tabulka reálně scrollovala) + route entry ve
`workbench/routes/web.php` + registrace v `WorkbenchServiceProvider`
(viz `memory/workbench_preview_livewire_registration_2026_07_13`).

## Otevřené otázky

1. Má sticky platit i pro `<th>` akcí a pro footer / summary řádek? Pravděpodobně
   ano — jinak hlavička odjede od těla.
2. Hrana: stín jen když je odscrollováno (potřebuje scroll listener), nebo
   statický border? v1 spíš statický.
3. Co `$actionsPosition === 'start'` v RTL?

## Definition of done

- `Table::stickyActions()` + sdílený vlastník, `Column::sticky()` připravitelné
  bez přepisu
- `getRowClasses()` garantuje neprůhledné pozadí ve všech stavech
- 100% coverage nových souborů (`composer coverage:enforce --min=100`)
- PHPStan + Pint čisté; `composer test:table` + `tests/Integration/` zelené
- CDP driver zelený **a ověřeně padající** bez implementace
- docs EN + CS (viz `memory/docs_bilingual_en_cz`)
- CHANGELOG
