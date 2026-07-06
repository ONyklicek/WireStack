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
| `wire-forms` | Pole, validace, layout, options, životní cyklus ukládání. |
| `wire-table` | Tabulky, sloupce, filtry, akce, souhrny, podřádky. |
| `wire-sortable` | Řazení řádků a sloupců. |

Slučují se do souboru guideline agenta (`CLAUDE.md`, `AGENTS.md`, …) mezi stabilní markery, takže
opětovné spuštění instalátoru čistě nahradí blok bez zásahu do vašeho vlastního obsahu.

## Skills

Skills jsou [Agent Skills](https://agentskills.io/) — zaměřené `SKILL.md` moduly, které agent aktivuje jen
když jsou relevantní, čímž drží kontext štíhlý:

| Skill | Kdy se aktivuje |
|-------|-------------------|
| `wire-table-development` | Stavba nebo změna wire datové tabulky. |
| `wire-forms-development` | Stavba nebo změna wire formuláře. |
| `wire-core-development` | Práce s akcemi, modaly, notifikacemi, infolisty nebo widgety. |
| `wire-sortable-development` | Přidání drag & drop řazení do tabulky. |

## Přizpůsobení

Vhoďte vlastní soubory do projektu pro rozšíření nebo přepsání dodaných zdrojů — sloučí se
při spuštění [`wire-boost:install`](installation.md) nebo `wire-boost:update`:

- `.ai/guidelines/*.md` (nebo `.blade.php`) — extra guidelines.
- `.ai/skills/<name>/SKILL.md` — extra skills.

## Guidelines vs. skills

| | Guidelines | Skills |
|--|-----------|--------|
| **Načtení** | Předem, vždy přítomné | Na požádání, když relevantní |
| **Rozsah** | Široké konvence | Zaměřené, task-specifické |
| **Nejlepší pro** | Základní pravidla, která má každá změna dodržet | Detailní vzory pro jeden workflow |
