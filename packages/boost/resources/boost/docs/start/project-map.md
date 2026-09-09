---
order: 100
summary: The fourteen packages, what each one is for, what it depends on, and the shortest install for the thing you actually want.
---

# Project Map

Wire is a Livewire package ecosystem split into fourteen Composer packages.
Install only the one that matches what you are building; its dependencies are
pulled in automatically, and nothing above it is installed for you.

## Packages

| Package | Composer name | Purpose | Depends on |
|---------|---------------|---------|------------|
| Core | `nyoncode/wire-core` | Shared actions, modals, notifications, widgets, infolists, schema, audit log, Blade helpers | Laravel, Livewire |
| Forms | `nyoncode/wire-forms` | Form schema, field components, validation, save lifecycle | Core |
| Table | `nyoncode/wire-table` | Tables, columns, filters, actions, inline editing, exports, gestures | Core, Forms |
| Sortable | `nyoncode/wire-sortable` | Drag and drop row and column reordering | Core, Table |
| Panels | `nyoncode/wire-panels` | The owner layer: resources, their pages, and the routing macro | Core, Forms, Table |
| Admin | `nyoncode/wire-admin` | The optional shell: a layout and a sidebar over the catalogue | Core, Panels |
| Suite | `nyoncode/wire-suite` | The whole stack in one require, plus `php artisan wire:install` | everything above |
| Users | `nyoncode/wire-module-users` | A ready-made users area, with roles where the application has them | Panels |
| Auth | `nyoncode/wire-module-auth` | The signed-out screens over Laravel Fortify | Core |
| Settings | `nyoncode/wire-module-settings` | Typed application settings, with storage and a screen | Panels |
| Notifications | `nyoncode/wire-module-notifications` | The stored history behind the notification bell | Panels |
| Audit | `nyoncode/wire-module-audit` | A screen for the trail core already records | Panels |
| Media | `nyoncode/wire-module-media` | A media library: uploads, folders, previews, a form picker | Panels |
| Boost | `nyoncode/wire-boost` | AI tooling: MCP server, guidelines, and agent skills | Core |

The graph has one direction. `wire-panels` may name every component package and
none of them may name it back; `wire-admin` sits above panels and nothing
requires it; a module sits above both. That is what makes each layer removable —
see [Panels](../panels/overview.md) for why the direction is the design.

## Install Paths

| Goal | Install |
|------|---------|
| A whole admin, set up interactively | `composer require nyoncode/wire-suite` then `php artisan wire:install` |
| Build a table UI | `composer require nyoncode/wire-table` |
| Build standalone forms only | `composer require nyoncode/wire-forms` |
| Add row or column reordering | `composer require nyoncode/wire-sortable` |
| Declare resources and their pages | `composer require nyoncode/wire-panels` |
| Add the layout and sidebar | `composer require nyoncode/wire-admin` |
| Use shared widgets/actions only | `composer require nyoncode/wire-core` |
| Add a ready-made area | `composer require nyoncode/wire-module-users` (and the other five) |
| Add AI agent tooling (MCP) | `composer require nyoncode/wire-boost --dev` |

## Documentation Map

| Area | Start here | Main references |
|------|------------|-----------------|
| Setup | [Installing Wire](installation.md) | [Getting Started](getting-started.md), [Configuration](configuration.md), [Authorization](authorization.md) |
| Forms | [Forms Overview](../forms/overview.md) | [Field Reference](../forms/fields/index.md), [Validation](../forms/validation.md), [Save Lifecycle](../forms/save-lifecycle.md) |
| Tables | [Table Overview](../table/overview.md) | [Columns](../table/columns/index.md), [Filters](../table/filters/index.md), [Actions](../table/actions.md), [Exports](../table/exports.md) |
| Core UI | [Foundation](../core/foundation/index.md) | [Actions](../core/actions/index.md), [Schema](../core/schema/overview.md), [Modals](../core/modals.md), [Notifications](../core/notifications/index.md), [Widgets](../core/widgets/index.md), [Infolists](../core/infolists/index.md), [Plugins](../core/plugins/index.md) |
| Owner layer | [Panels Overview](../panels/overview.md) | [Resources](../panels/resources.md), [Pages](../panels/pages.md), [Navigation](../panels/navigation.md), [Routing](../panels/routing.md) |
| Admin shell | [The Admin Shell](../admin/overview.md) | [Layout](../admin/layout.md), [Sidebar](../admin/sidebar.md), [Branding](../admin/branding.md) |
| Ready-made areas | [Ready-Made Modules](../modules/index.md) | [Users](../modules/users.md), [Auth](../modules/auth.md), [Settings](../modules/settings.md), [Media](../modules/media.md) |
| Sortable | [Sortable Overview](../sortable/overview.md) | [Installation](../sortable/installation.md), [Row Sorting](../sortable/row-sorting.md), [Column Sorting](../sortable/column-sorting.md) |
| Boost (AI) | [Boost Overview](../boost/overview.md) | [Installation](../boost/installation.md), [MCP Server & Tools](../boost/mcp-tools.md), [Guidelines & Skills](../boost/guidelines-and-skills.md) |

## Source Layout

| Path | Contents |
|------|----------|
| `packages/core/src/Actions` | Action, BulkAction, HeaderAction, presets, modal action helpers |
| `packages/core/src/Foundation/Schema` | Shared layout vocabulary — Grid, Section, Fieldset, Flex, Tabs/Tab, Wizard/Step, Callout, EmptyState |
| `packages/core/src/Foundation/View` | Standalone `<x-wire::*>` Blade components mirroring the schema layouts |
| `packages/core/src/Foundation/Support` | Shared helpers — `ResponsiveGrid` (per-breakpoint columns), `MobileSheet`, `EnumResolver` |
| `packages/core/src/Foundation/Concerns` | Canonical shared traits — `HasColor`, `HasIcon`, `HasSize`, `HasVisibility`, `HasActions`, `HasSheetOnMobile`, … |
| `packages/core/src/Foundation/Registration` | `Catalog` — everything an application registered, whatever kind — plus the `RegistrySource` / `HasRegistryKey` contracts a registry joins it with |
| `packages/core/src/Foundation/Routing` | What a page declaration carries (`ProvidesPages`, `RoutePage`, `ConfiguresRoutes`), `Zone`, and the `ResolvesPageUrls` / `RegistersPageRoutes` seams the URL convention answers |
| `packages/core/src/GlobalSearch` | The ⌘K palette, its search service and result value object |
| `packages/core/src/Core/Resources` | Resource identity, the registry, `Workspace` and the navigation vocabulary |
| `packages/core/src/Modals` | Modal, confirmation, slide-over, wizard classes |
| `packages/core/src/Notifications` | Notification value object, manager, drivers |
| `packages/core/src/Widgets` | Stats, chart, table, custom widgets, and the dashboard registry |
| `packages/core/src/Infolists` | Infolist, entries, read-only record display |
| `packages/core/src/Panels` | Editable record panels — an infolist whose entries write back |
| `packages/core/src/Audit` | Audit entries, events, logger, model trait, audit trail action |
| `packages/core/src/Core/Plugin` | Plugin contract, manager, hooks, type registries |
| `packages/forms/src/Components` | Form fields, layout components, relationship fields, repeater |
| `packages/forms/src/Forms` | `Form` public API and `WithForms` Livewire trait |
| `packages/table/src/Columns` | Table column classes and inline-editing columns |
| `packages/table/src/Filters` | Select, date, number range, ternary, and custom filters |
| `packages/table/src/Export` | CSV, Excel, PDF export support |
| `packages/table/src/Concerns/WithTable.php` | Livewire integration for table state and actions |
| `packages/sortable/src` | Sortable table helpers, Livewire trait, column-order model |
| `packages/panels/src/Resources/Pages` | `ListPage`, `CreatePage`, `EditPage`, `ViewPage`, `DashboardPage` |
| `packages/panels/src/Routing` | `ResourceRoutes`, the config-declared route group, and registered page URLs |
| `packages/admin/src/View` | The shell components — layout, auth layout, sidebar, menu item |
| `packages/admin/src/Install` | `wire-admin:install` and what it writes |
| `packages/suite/src/Install` | `wire:install` — the interactive setup over every installed package |
| `packages/module-*/src` | One ready-made area each: its module manifest, resources, pages and support |
| `packages/boost/src/Mcp` | The MCP server and its introspection tools |

## Test Commands

```bash
composer test              # everything
composer test:core
composer test:forms
composer test:table
composer test:panels
composer test:admin
composer test:sortable
composer test:suite
composer test:boost
composer test:module-users
composer test:module-auth
composer test:module-settings
composer test:module-notifications
composer test:module-audit
composer test:module-media

composer lint
composer analyse
```

## Related

- [Installing Wire](installation.md) — the one-command setup over a clean Laravel
- [Configuration](configuration.md) — every config file these packages publish
- [Upgrade Guide](upgrade.md) — versioning, and what 2.0 changed
