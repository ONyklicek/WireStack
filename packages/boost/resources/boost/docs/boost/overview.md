---
title: Introduction
order: 10
summary: AI tooling for the Wire ecosystem — an MCP server, AI guidelines, and agent skills.
---

# Wire Boost

Wire Boost is the wireStack equivalent of [Laravel Boost](https://github.com/laravel/boost). It helps
AI coding agents (Claude Code, Cursor, Codex, Gemini CLI, GitHub Copilot, Junie) write high-quality
applications with the whole stack — [wire-core](../core/foundation/index.md),
[wire-forms](../forms/overview.md), [wire-table](../table/overview.md) and
[wire-sortable](../sortable/overview.md), the optional [wire-panels](../panels/overview.md) and
[wire-admin](../admin/overview.md) layers, and the six [ready-made modules](../modules/index.md).

It ships three things:

- **An MCP server** — introspection tools an agent can call to inspect your wire tables, forms, infolists,
  the available component vocabulary, icons, config, and documentation. See [MCP Server & Tools](mcp-tools.md).
- **AI guidelines** — concise, always-loaded context describing wireStack conventions and APIs.
- **Agent Skills** — on-demand knowledge modules for table, form, core, sortable, resource, admin-shell
  and module development.

See [Guidelines & Skills](guidelines-and-skills.md) for the AI-context layer.

**Both are shipped per package.** An application that installed only core and forms gets only those
guidelines and skills — an agent told about `ListPage` where there is no `wire-panels` writes a class
that does not exist, which is worse than not being told at all.

## Why

wireStack uses fluent, Nova/Filament-style builders (`TextColumn::make('name')->sortable()`). An agent
that knows the available types, their fluent methods, and the components already present in your app
writes correct code on the first try instead of guessing. Wire Boost gives the agent that knowledge —
both upfront (guidelines/skills) and on demand (MCP tools).

## Quick start

```bash
composer require nyoncode/wire-boost --dev
php artisan wire-boost:install
```

`wire-boost:install` configures the agents you select: it registers the MCP server, merges the wireStack
guidelines into the agent's guideline file, and installs the skills. See [Installation](installation.md).

## At a glance

| Capability | Entry point |
|------------|-------------|
| Inspect tables / forms / infolists | `describe-table`, `describe-form`, `describe-infolist` |
| Discover component types & APIs | `list-component-types`, `describe-component-api` |
| Find existing wire components | `list-wire-components` |
| Search the wire docs corpus | `search-wire-docs` |
| Inspect resources, pages and modules | `describe-resource`, `describe-module` |
| App & config introspection | `application-info`, `wire-config`, `list-icons` |
| General Laravel parity tools | `database-schema`, `list-routes`, `last-error`, … |
