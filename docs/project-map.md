---
order: 30
---

# Project Map

Wire is a Laravel Livewire package ecosystem split into four Composer packages. Install only the package that matches the UI you need; dependencies are pulled in automatically.

## Packages

| Package | Composer name | Purpose | Depends on |
|---------|---------------|---------|------------|
| Core | `nyoncode/wire-core` | Shared actions, modals, notifications, widgets, audit log, Blade helpers | Laravel, Livewire |
| Forms | `nyoncode/wire-forms` | Standalone form schema, field components, validation, save lifecycle | Core |
| Table | `nyoncode/wire-table` | Tables, columns, filters, actions, inline editing, exports | Core, Forms |
| Sortable | `nyoncode/wire-sortable` | Drag and drop row and column reordering for Wire Table | Core, Table |
| Boost | `nyoncode/wire-boost` | AI tooling: MCP server, guidelines, and skills for coding agents | Core, Laravel MCP |

## Install Paths

| Goal | Install |
|------|---------|
| Build a table UI | `composer require nyoncode/wire-table` |
| Build standalone forms only | `composer require nyoncode/wire-forms` |
| Add row or column reordering | `composer require nyoncode/wire-sortable` |
| Use shared widgets/actions only | `composer require nyoncode/wire-core` |
| Add AI agent tooling (MCP) | `composer require nyoncode/wire-boost --dev` |

## Documentation Map

| Area | Start here | Main references |
|------|------------|-----------------|
| Setup | [Getting Started](getting-started.md) | [Configuration](configuration.md), [Authorization](authorization.md) |
| Forms | [Forms Overview](forms/overview.md) | [Field Reference](forms/fields/index.md), [Validation](forms/validation.md), [Save Lifecycle](forms/save-lifecycle.md) |
| Tables | [Table Overview](table/overview.md) | [Columns](table/columns/index.md), [Filters](table/filters/index.md), [Actions](table/actions.md), [Exports](table/exports.md) |
| Sortable | [Sortable Overview](sortable/overview.md) | [Installation](sortable/installation.md), [Row Reordering](sortable/row-sorting.md), [Column Reordering](sortable/column-sorting.md) |
| Core UI | [Core Actions](core/actions.md) | [Schema](core/schema/overview.md), [Modals](core/modals.md), [Notifications](core/notifications.md), [Widgets](core/widgets.md), [Infolists](core/infolists.md), [Plugins](core/plugins.md), [Audit Log](core/audit.md) |
| Boost (AI) | [Boost Overview](boost/overview.md) | [Installation](boost/installation.md), [MCP Server & Tools](boost/mcp-tools.md), [Guidelines & Skills](boost/guidelines-and-skills.md) |

## Source Layout

| Path | Contents |
|------|----------|
| `packages/core/src/Actions` | Action, BulkAction, HeaderAction, presets, modal action helpers |
| `packages/core/src/Foundation/Schema` | Shared layout vocabulary — Grid, Section, Fieldset, Flex, Tabs/Tab, Wizard/Step, Callout, EmptyState |
| `packages/core/src/Foundation/View` | Standalone `<x-wire::*>` Blade components mirroring the schema layouts |
| `packages/core/src/Foundation/Support` | Shared helpers — `ResponsiveGrid` (per-breakpoint columns), `MobileSheet`, `EnumResolver` |
| `packages/core/src/Foundation/Concerns` | Canonical shared traits — `HasColor`, `HasIcon`, `HasSize`, `HasVisibility`, `HasActions`, `HasSheetOnMobile`, … |
| `packages/core/src/Modals` | Modal, confirmation, slide-over, wizard classes |
| `packages/core/src/Notifications` | Notification value object, manager, drivers |
| `packages/core/src/Widgets` | Stats, chart, table, custom widgets |
| `packages/core/src/Infolists` | Infolist, entries, read-only record display |
| `packages/core/src/Audit` | Audit entries, events, logger, model trait, audit trail action |
| `packages/core/src/Core/Plugin` | Plugin contract, manager, hooks, type registries |
| `packages/forms/src/Components` | Form fields, layout components, relationship fields, repeater |
| `packages/forms/src/Forms` | `Form` public API and `WithForms` Livewire trait |
| `packages/table/src/Columns` | Table column classes and inline-editing columns |
| `packages/table/src/Filters` | Select, date, number range, ternary, and custom filters |
| `packages/table/src/Export` | CSV, Excel, PDF export support |
| `packages/table/src/Concerns/WithTable.php` | Livewire integration for table state and actions |
| `packages/sortable/src` | Sortable table helpers, Livewire trait, column-order model |

## Test Commands

```bash
composer test
composer test:core
composer test:forms
composer test:table
composer test:sortable
composer lint
composer analyse
```
