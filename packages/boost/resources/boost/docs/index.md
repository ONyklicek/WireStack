---
order: 1
summary: The map of the Wire documentation — the two surfaces most people arrive for, what they are built out of, and the layers that assemble a whole admin.
---

# Wire Documentation

Wire is a Livewire component ecosystem for Laravel: a table, a form system, and
the layers that turn them into an admin — an owner for each entity, a shell to
render it in, and ready-made areas you install rather than write.

```bash
composer require nyoncode/wire-suite
php artisan wire:install
```

Nothing forces that path. Each package below installs on its own, and the whole
stack is optional above `wire-core` — see [Installing Wire](start/installation.md)
for the one-command setup and [Project Map](start/project-map.md) for what
depends on what.

## Start Here

| Document | Description |
|----------|-------------|
| [Installing Wire](start/installation.md) | The whole stack in one require, and one interactive command |
| [Getting Started](start/getting-started.md) | Tailwind, Livewire, assets, and the first table and form |
| [Configuration](start/configuration.md) | Published config files, environment variables, and package defaults |
| [Authorization](start/authorization.md) | Gates, policies, permissions, table rules, and form rules |
| [Theming & Customization](start/theming.md) | Colors, icons, overriding views, and localization |
| [Testing](start/testing.md) | Standalone, Livewire, and unit tests for forms and tables |
| [Cookbook](start/cookbook.md) | Task-oriented recipes built from the public API |
| [Error Handling](start/error-handling.md) | The exceptions each package throws, and what they mean |
| [Troubleshooting](start/troubleshooting.md) | Fixes for common configuration issues |
| [Upgrade Guide](start/upgrade.md) | Versioning, requirements, and what 2.0 changed |
| [Project Map](start/project-map.md) | Package overview, install paths, source layout, test commands |

## Forms

`wire-forms` — a schema of fields you declare in PHP, bound to a Livewire host or
used standalone.

| Document | Description |
|----------|-------------|
| [Forms Overview](forms/overview.md) | Single form, multi-form, standalone usage, and the save flow |
| [Validation](forms/validation.md) | Rules, messages, and custom validation behavior |
| [Reactive Fields](forms/reactive-fields.md) | Fields that react to other fields, live |
| [Save Lifecycle](forms/save-lifecycle.md) | Validation, mutation, persistence, and notifications |
| [Field Reference](forms/fields/index.md) | Every field — input, layout, display, relationship, repeater |
| [Extending Forms](forms/custom-fields.md) | Custom fields, display components, presets, and packaging |

## Table

`wire-table` — the list surface: columns, filters, actions, inline editing,
exports and the gesture layer.

| Document | Description |
|----------|-------------|
| [Table Overview](table/overview.md) | First table, `WithTable`, and base configuration |
| [Columns](table/columns/index.md) | Column types, formatting, search, sort, responsive visibility |
| [Filters](table/filters/index.md) | Built-in filters and custom query behavior |
| [Actions](table/actions.md) | Row, bulk, and header actions with modal forms |
| [Record Actions](table/record-actions.md) | The row context menu and what it can reach |
| [Selection](table/selection.md) and [Gestures](table/gestures.md) | Keyboard grid navigation, ranges, the drag sweep, the fill handle |
| [Exports](table/exports.md) and [Imports](table/imports.md) | CSV, Excel and PDF out; mapped, cast and validated CSV in |
| [Summaries](table/summaries.md) | Footer aggregates, scopes, rollups, and grand totals |
| [Row Grouping](table/grouping.md) and [Sub-Rows](table/sub-rows.md) | Grouped rows with subtotals; child records inside a row |
| [Relation Managers](table/relation-managers.md) | Relationship-scoped tables as standalone components |
| [Data Sources](table/data-sources.md) | A table over something that is not Eloquent |
| [Advanced Features](table/advanced.md) | Polling, performance, and debugging |

## Core

`wire-core` — what both surfaces are built out of, and the surfaces that have no
list or form of their own.

| Document | Description |
|----------|-------------|
| [Wire Core](core/overview.md) | What lives in core, how its modules are layered, and which page answers what |
| [Foundation](core/foundation/index.md) | Shared traits, icons, colors, enums, and Blade helpers |
| [Actions](core/actions/index.md) | Row, bulk and header actions, groups, modals, wizards, queues |
| [Modals](core/modals.md) | Confirmation, slide-over, and wizard components |
| [Notifications](core/notifications/index.md) | Notification value objects, the manager, and drivers |
| [Widgets](core/widgets/index.md) | Stats, charts, table and custom widgets, and dashboards |
| [Infolists](core/infolists/index.md) | Read-only, schema-driven display of one record |
| [Editable Panels](core/record-panels.md) | An infolist you can edit — each change commits on its own |
| [Schema](core/schema/overview.md) | The shared layout vocabulary — Grid, Section, Flex, Tabs, Wizard |
| [Global Search](core/global-search.md) | One command palette over everything registered |
| [Audit Log](core/audit.md) | Recording model changes and table-related events |
| [Plugins](core/plugins/index.md) | App and package extension points, hooks and type registries |

## Panels

`wire-panels` — the owner layer: one entity declared once, with the pages, menu
and routes that follow.

| Document | Description |
|----------|-------------|
| [Panels Overview](panels/overview.md) | What the owner layer is, and what installs with it |
| [Resources](panels/resources.md) | Identity, surface contracts, naming, registration |
| [Pages](panels/pages.md) | List, create, edit, view and dashboard pages |
| [Navigation](panels/navigation.md) | Entries, groups, the workspace, and the catalogue |
| [Routing](panels/routing.md) | Declared pages as URLs, per-resource middleware, zones |
| [Modules](panels/modules.md) | One business area's resources and dashboards, declared once |

## Admin

`wire-admin` — the optional shell. Everything else works without it.

| Document | Description |
|----------|-------------|
| [The Admin Shell](admin/overview.md) | Installing it, what it reads, and the one Tailwind line |
| [The Layout](admin/layout.md) | Slots, the auth frame, and the signed-in user's corner |
| [The Sidebar](admin/sidebar.md) | The menu component, and the 64-pixel rail |
| [Branding And Theme](admin/branding.md) | The logo, the three-state theme switch, publishing views |

## Modules

Whole areas shipped as composer packages.

| Document | Description |
|----------|-------------|
| [Ready-Made Modules](modules/index.md) | What each one installs, and how to take one out |
| [Users](modules/users.md) | The users area, with roles where the application has them |
| [Teams and Two-Factor](modules/teams-and-two-factor.md) | Switching on Fortify's two-factor and per-team roles |
| [Auth](modules/auth.md) | Login, password reset, verification, the two-factor challenge |
| [Settings](modules/settings.md) | Typed application settings, with the screen and the storage |
| [Notifications](modules/notifications.md) | The history behind the notification bell |
| [Audit](modules/audit.md) | A screen for the trail wire-core already records |
| [Media](modules/media.md) | Uploads, a browsable library, previews and a form picker |

## Sortable

| Document | Description |
|----------|-------------|
| [Sortable Overview](sortable/overview.md) | Drag and drop sorting for rows and columns |
| [Installation](sortable/installation.md) | Package setup and frontend requirements |
| [Row Sorting](sortable/row-sorting.md) and [Column Sorting](sortable/column-sorting.md) | The two axes, and what each persists |
| [Customization](sortable/customization.md) and [Advanced](sortable/advanced.md) | Handles, constraints, and the extension points |
| [API Reference](sortable/api-reference.md) | Sortable table and trait API |

## Boost

AI tooling for the ecosystem — an MCP server, guidelines, and skills for coding
agents.

| Document | Description |
|----------|-------------|
| [Boost Overview](boost/overview.md) | What Wire Boost is and how it helps AI agents |
| [Installation](boost/installation.md) | Install the package and configure your agents |
| [MCP Server & Tools](boost/mcp-tools.md) | The MCP server and its introspection tools |
| [Guidelines & Skills](boost/guidelines-and-skills.md) | The always-loaded and on-demand AI-context layer |
