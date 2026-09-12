---
title: Guidelines a Skills
order: 40
summary: Vrstva AI kontextu — vždy načtené guidelines a on-demand agent skills.
---

# Guidelines a Skills

Zatímco [MCP nástroje](mcp-tools.md) odpovídají na dotazy na požádání, **guidelines** a **skills** dávají
agentovi kontext předem. `wire-boost:install` zapíše obojí do vybraných agentů.

## Guidelines

Guidelines jsou stručné instrukce načtené na začátku každé session. Wire Boost dodává jednu per
balíček:

| Guideline | Pokrývá |
|-----------|--------|
| `core` | Přehled wireStack, graf balíčků a konvence (fluent API, kanonické vlastnictví, `Htmlable` rendering). |
| `wire-core` | Akce, modaly, notifikace, infolisty, widgety, ikony, barvy. |
| `wire-forms` | Pole, validace, layout, možnosti, životní cyklus ukládání. |
| `wire-table` | Tabulky, sloupce, filtry, akce, souhrny, podřádky. |
| `wire-sortable` | Řazení řádků a sloupců. |
| `wire-panels` | Resources, stránky, registry, navigace a aktivní položka. |
| `wire-admin` | Volitelný shell — layout, sidebar, chrome regiony, co je slot a co ne. |
| `wire-modules` | Šest hotových oblastí, co která potřebuje a co zůstává odinstalovatelné. |
| `wire-suite` | Meta-balíček a `wire:install` — co nastaví a co odmítá dělat. |

Slučují se do souboru guideline agenta (`CLAUDE.md`, `AGENTS.md`, …) mezi stabilní markery, takže
opětovné spuštění instalátoru čistě nahradí blok bez zásahu do vašeho vlastního obsahu.

**Guideline pojmenovaná po balíčku se dodá jen tam, kde je ten balíček nainstalovaný.** Signálem je název
souboru — `wire-panels.blade.php` se v aplikaci bez `wire-panels` přeskočí a `wire-modules` se dodá,
jakmile je nainstalovaný kterýkoli z šesti modulů. `core` a vaše vlastní soubory se nikdy nefiltrují.

## Skills

Skills jsou [Agent Skills](https://agentskills.io/) — zaměřené `SKILL.md` moduly, které agent aktivuje jen
když jsou relevantní, čímž drží kontext štíhlý:

| Skill | Kdy se aktivuje |
|-------|-------------------|
| `wire-table-development` | Stavba nebo změna wire datové tabulky. |
| `wire-forms-development` | Stavba nebo změna wire formuláře. |
| `wire-core-development` | Práce s akcemi, modaly, notifikacemi, infolisty nebo widgety. |
| `wire-sortable-development` | Přidání drag & drop řazení do tabulky. |
| `wire-panels-development` | Deklarace resource, jeho stránek a navigační položky. |
| `wire-admin-development` | Práce na admin shellu — layout, sidebar, uživatelské menu. |
| `wire-modules-development` | Instalace, úprava nebo psaní hotového modulu. |
| `wire-v2-upgrade` | Migrace aplikace z wireStacku 1.x na 2.0. |

Skills se filtrují stejně jako guidelines: `wire-panels-development` se nainstaluje jen tam, kde je
`wire-panels`. `wire-v2-upgrade` není pojmenovaný po balíčku, takže se dodává vždy.

## Přizpůsobení

Vhoďte vlastní soubory do projektu pro rozšíření nebo přepsání dodaných zdrojů — sloučí se
při spuštění [`wire-boost:install`](installation.md) nebo `wire-boost:update`:

- `.ai/guidelines/*.md` (nebo `.blade.php`) — extra guidelines.
- `.ai/skills/<name>/SKILL.md` — extra skills.

Projektový adresář se stejným názvem jako dodaný skill vyhrává **po jednotlivých souborech**: čte se až po
dodaném modulu a přepíše to, co pojmenuje — takže můžete nahradit jediný `SKILL.md`, aniž byste znovu psali
referenční soubory vedle něj.

## Guidelines vs. skills

| | Guidelines | Skills |
|--|-----------|--------|
| **Načtení** | Předem, vždy přítomné | Na požádání, když relevantní |
| **Rozsah** | Široké konvence | Zaměřené, task-specifické |
| **Nejlepší pro** | Základní pravidla, která má každá změna dodržet | Detailní vzory pro jeden workflow |
