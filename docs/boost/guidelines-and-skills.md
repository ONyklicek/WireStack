---
title: Guidelines & Skills
order: 40
summary: The AI-context layer — always-loaded guidelines and on-demand agent skills.
---

# Guidelines & Skills

Where the [MCP tools](mcp-tools.md) answer questions on demand, **guidelines** and **skills** give the
agent context up front. `wire-boost:install` writes both into your selected agents.

## Guidelines

Guidelines are concise instructions loaded at the start of every session. Wire Boost ships one per
package:

| Guideline | Covers |
|-----------|--------|
| `core` | wireStack overview, the package graph, and conventions (fluent APIs, canonical ownership, `Htmlable` rendering). |
| `wire-core` | Actions, modals, notifications, infolists, widgets, icons, colors. |
| `wire-forms` | Fields, validation, layout, options, the save lifecycle. |
| `wire-table` | Tables, columns, filters, actions, summaries, sub-rows. |
| `wire-sortable` | Row and column reordering. |
| `wire-panels` | Resources, pages, the registries, navigation and the active entry. |
| `wire-admin` | The optional shell — the layout, the sidebar, the chrome regions, what is a slot and what is not. |
| `wire-modules` | The six ready-made areas, what each needs, and what stays removable. |
| `wire-suite` | The meta-package and `wire:install` — what it sets up and what it refuses to do. |

They are merged into the agent's guideline file (`CLAUDE.md`, `AGENTS.md`, …) between stable markers, so
re-running the installer replaces the block cleanly without touching your own content.

**A guideline named after a package ships only where that package is installed.** The file name is the
signal — `wire-panels.blade.php` is skipped in an application without `wire-panels`, and `wire-modules`
ships as soon as any one of the six modules does. `core` and your own files are never filtered.

## Skills

Skills are [Agent Skills](https://agentskills.io/) — focused `SKILL.md` modules an agent activates only
when relevant, keeping context lean:

| Skill | When it activates |
|-------|-------------------|
| `wire-table-development` | Building or changing a wire data table. |
| `wire-forms-development` | Building or changing a wire form. |
| `wire-core-development` | Working with actions, modals, notifications, infolists, or widgets. |
| `wire-sortable-development` | Adding drag & drop reordering to a table. |
| `wire-panels-development` | Declaring a resource, its pages, and its navigation entry. |
| `wire-admin-development` | Working on the admin shell — layout, sidebar, user menu. |
| `wire-modules-development` | Installing, adapting or writing a ready-made module. |
| `wire-v2-upgrade` | Migrating an application from wireStack 1.x to 2.0. |

Skills are filtered the same way as guidelines: `wire-panels-development` is installed only where
`wire-panels` is. `wire-v2-upgrade` is not named after a package, so it always ships.

## Customising

Drop your own files into the project to extend or override the shipped resources — they are merged in
when you run [`wire-boost:install`](installation.md) or `wire-boost:update`:

- `.ai/guidelines/*.md` (or `.blade.php`) — extra guidelines.
- `.ai/skills/<name>/SKILL.md` — extra skills.

A project directory of the same name as a shipped skill wins **one file at a time**: it is read after the
shipped module and overwrites what it names, so you can replace a single `SKILL.md` without restating the
reference files beside it.

## Guidelines vs. skills

| | Guidelines | Skills |
|--|-----------|--------|
| **Loaded** | Upfront, always present | On demand, when relevant |
| **Scope** | Broad conventions | Focused, task-specific |
| **Best for** | Core rules every change should follow | Detailed patterns for one workflow |
