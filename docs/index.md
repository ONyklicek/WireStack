---
order: 10
---

# Wire Documentation

User-facing documentation for the Wire ecosystem.

## Start Here

| Document | Description |
|----------|-------------|
| [Getting Started](getting-started.md) | Install Wire, configure Tailwind and Livewire, build the first table and form |
| [Project Map](project-map.md) | Package overview, install paths, source layout, and test commands |
| [Configuration](configuration.md) | Published config files, environment variables, and package defaults |
| [Authorization](authorization.md) | Gates, policies, permissions, table rules, and form rules |
| [Theming & Customization](theming.md) | Colors, icons, overriding views, and localization |
| [Testing](testing.md) | Standalone, Livewire, and unit tests for forms and tables |
| [Cookbook](cookbook.md) | Task-oriented recipes built from the public API |
| [Troubleshooting](troubleshooting.md) | Fixes for common configuration issues |
| [Upgrade Guide](upgrade.md) | Versioning, requirements, and upgrade steps |

## wire-table

| Document | Description |
|----------|-------------|
| [Table Overview](table/overview.md) | First table, `WithTable`, and base configuration |
| [Columns](table/columns/index.md) | Column types, formatting, search, sort, responsive visibility |
| [Filters](table/filters/index.md) | Built-in filters and custom query behavior |
| [Actions](table/actions.md) | Row, bulk, and header actions with modal forms |
| [Exports](table/exports.md) | CSV, Excel, and PDF exports for the current table query |
| [Imports](table/imports.md) | CSV imports with header mapping, casting, and per-row validation |
| [Summaries](table/summaries.md) | Footer aggregates, scopes, rollups, and grand totals |
| [Row Grouping](table/grouping.md) | Grouped rows with headers and per-group subtotals |
| [Sub-Rows](table/sub-rows.md) | Related child records rendered inside a table row |
| [Relation Managers](table/relation-managers.md) | Relationship-scoped tables as standalone Livewire components |
| [Notifications](table/notifications.md) | Toasts, action feedback, and delivery drivers |
| [Advanced Features](table/advanced.md) | Polling, summaries, performance, and debugging |

## wire-forms

| Document | Description |
|----------|-------------|
| [Forms Overview](forms/overview.md) | Single form, multi-form, standalone usage, and save flow |
| [Validation](forms/validation.md) | Rules, messages, and custom validation behavior |
| [Save Lifecycle](forms/save-lifecycle.md) | Validation, mutation, persistence, and notifications |
| [Field Reference](forms/fields/index.md) | Input, layout, display, relationship, and repeater components |
| [Extending Forms](forms/custom-fields.md) | Custom fields, display components, presets, and packaging |

## wire-sortable

| Document | Description |
|----------|-------------|
| [Sortable Overview](sortable/overview.md) | Drag and drop sorting for rows and columns |
| [Installation](sortable/installation.md) | Package setup and frontend requirements |
| [API Reference](sortable/api-reference.md) | Sortable table and trait API |

## Core API

| Document | Description |
|----------|-------------|
| [Core Foundation](core/foundation.md) | Shared traits, icons, colors, and Blade helpers |
| [Core Actions](core/actions.md) | Row, bulk, header actions, action groups |
| [Core Schema](core/schema/overview.md) | Shared layout vocabulary — Grid, Section, Flex, Tabs, Wizard, Callout, Empty State |
| [Core Notifications](core/notifications.md) | Notification value objects and drivers |
| [Core Modals](core/modals.md) | Confirmation, slide-over, and wizard components |
| [Core Widgets](core/widgets.md) | Dashboard widgets and widget layout |
| [Core Infolists](core/infolists.md) | Read-only, schema-driven display of a record |
| [Core Plugins](core/plugins.md) | App and package extension points |
| [Audit Log](core/audit.md) | Record model changes and table-related events |

## wire-boost

AI tooling for the Wire ecosystem — an MCP server, guidelines, and skills for AI coding agents.

| Document | Description |
|----------|-------------|
| [Boost Overview](boost/overview.md) | What Wire Boost is and how it helps AI agents |
| [Installation](boost/installation.md) | Install the package and configure your agents |
| [MCP Server & Tools](boost/mcp-tools.md) | The MCP server and its twenty introspection tools |
| [Guidelines & Skills](boost/guidelines-and-skills.md) | The always-loaded and on-demand AI-context layer |
