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

They are merged into the agent's guideline file (`CLAUDE.md`, `AGENTS.md`, …) between stable markers, so
re-running the installer replaces the block cleanly without touching your own content.

## Skills

Skills are [Agent Skills](https://agentskills.io/) — focused `SKILL.md` modules an agent activates only
when relevant, keeping context lean:

| Skill | When it activates |
|-------|-------------------|
| `wire-table-development` | Building or changing a wire data table. |
| `wire-forms-development` | Building or changing a wire form. |
| `wire-core-development` | Working with actions, modals, notifications, infolists, or widgets. |
| `wire-sortable-development` | Adding drag & drop reordering to a table. |

## Customising

Drop your own files into the project to extend or override the shipped resources — they are merged in
when you run [`wire-boost:install`](installation.md) or `wire-boost:update`:

- `.ai/guidelines/*.md` (or `.blade.php`) — extra guidelines.
- `.ai/skills/<name>/SKILL.md` — extra skills.

## Guidelines vs. skills

| | Guidelines | Skills |
|--|-----------|--------|
| **Loaded** | Upfront, always present | On demand, when relevant |
| **Scope** | Broad conventions | Focused, task-specific |
| **Best for** | Core rules every change should follow | Detailed patterns for one workflow |
