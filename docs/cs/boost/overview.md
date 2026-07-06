---
title: Úvod
order: 10
summary: AI nástroje pro ekosystém Wire — MCP server, AI guidelines a agent skills.
---

# Wire Boost

Wire Boost je ekvivalent [Laravel Boost](https://github.com/laravel/boost) pro wireStack. Pomáhá
AI kódovacím agentům (Claude Code, Cursor, Codex, Gemini CLI, GitHub Copilot, Junie) psát kvalitní
aplikace s [wire-core](../core/foundation.md), [wire-forms](../forms/overview.md),
[wire-table](../table/overview.md) a [wire-sortable](../sortable/overview.md).

Dodává tři věci:

- **MCP server** — introspektivní nástroje, které agent může volat k prozkoumání vašich wire tabulek, formulářů, infolistů,
  dostupného slovníku komponent, ikon, configu a dokumentace. Viz [MCP Server a nástroje](mcp-tools.md).
- **AI guidelines** — stručný, vždy načtený kontext popisující wireStack konvence a API.
- **Agent Skills** — on-demand znalostní moduly pro vývoj table, form, core a sortable.

Vrstvu AI kontextu viz [Guidelines a Skills](guidelines-and-skills.md).

## Proč

wireStack používá fluent buildery ve stylu Nova/Filament (`TextColumn::make('name')->sortable()`). Agent,
který zná dostupné typy, jejich fluent metody a komponenty už přítomné ve vaší aplikaci,
napíše správný kód napoprvé místo hádání. Wire Boost dává agentovi tuto znalost —
jak předem (guidelines/skills), tak na požádání (MCP nástroje).

## Rychlý start

```bash
composer require nyoncode/wire-boost --dev
php artisan wire-boost:install
```

`wire-boost:install` nakonfiguruje agenty, které vyberete: zaregistruje MCP server, sloučí wireStack
guidelines do souboru guideline agenta a nainstaluje skills. Viz [Instalace](installation.md).

## Ve zkratce

| Schopnost | Vstupní bod |
|------------|-------------|
| Prozkoumat tabulky / formuláře / infolisty | `describe-table`, `describe-form`, `describe-infolist` |
| Objevit typy komponent a API | `list-component-types`, `describe-component-api` |
| Najít existující wire komponenty | `list-wire-components` |
| Prohledat korpus wire docs | `search-wire-docs` |
| Introspekce aplikace a configu | `application-info`, `wire-config`, `list-icons` |
| Obecné nástroje parity s Laravel | `database-schema`, `list-routes`, `last-error`, … |
