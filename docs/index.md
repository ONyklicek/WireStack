# Wire Documentation

User-facing documentation for the Wire ecosystem.

## Start Here

| Document | Description |
|----------|-------------|
| [Getting Started](getting-started.md) | Install Wire, configure Tailwind and Livewire, build the first table and form |
| [Architecture Overview](architecture.md) | Package boundaries, dependency graph, and design rules |
| [Migration v0 to v1](migration/v0-to-v1.md) | Upgrade path from the monolithic package |

## wire-table

| Document | Description |
|----------|-------------|
| [Table Overview](table/overview.md) | First table, `WithTable`, and base configuration |
| [Columns](table/columns.md) | Column types, formatting, search, sort, responsive visibility |
| [Filters](table/filters.md) | Built-in filters and custom query behavior |
| [Actions](table/actions.md) | Row, bulk, and header actions with modal forms |
| [Sub-Rows](table/sub-rows.md) | Related child records rendered inside a table row |
| [Notifications](table/notifications.md) | Toasts, action feedback, and delivery drivers |
| [Advanced Features](table/advanced.md) | Polling, summaries, performance, and debugging |

## wire-forms

| Document | Description |
|----------|-------------|
| [Forms Overview](forms/overview.md) | Single form, multi-form, standalone usage, and save flow |
| [Validation](forms/validation.md) | Rules, messages, and custom validation behavior |
| [Save Lifecycle](forms/save-lifecycle.md) | Validation, mutation, persistence, and notifications |
| [Field Reference](forms/fields/index.md) | Input, layout, and display components |

## wire-sortable

| Document | Description |
|----------|-------------|
| [Sortable Overview](sortable/overview.md) | Drag and drop sorting for rows and columns |
| [Installation](sortable/installation.md) | Package setup and frontend requirements |
| [API Reference](sortable/api-reference.md) | Sortable table and trait API |

## Core and Extension Docs

| Document | Description |
|----------|-------------|
| [Core Foundation](core/foundation.md) | Shared traits, icons, colors, and Blade helpers |
| [Core Actions](core/actions.md) | Full action system internals and advanced API |
| [Core Notifications](core/notifications.md) | Notification value objects and drivers |
| [Core Modals](core/modals.md) | Confirmation, slide-over, and wizard internals |
| [Unified Engine](core/unified-engine.md) | Internal architecture of the metadata and query engine |
| [Plugin Development](core/plugins.md) | Plugin interfaces and extension points |

## Internal Design Records

| Document | Description |
|----------|-------------|
| [ADR Index](decisions/0001-action-form-integration.md) | Architectural decisions for package internals |
| [Unified Engine Spec](unified-engine-spec.md) | Technical specification |
| [Unified Engine Plan](unified-engine-plan.md) | Implementation plan |
| [Unified Engine Assessment](unified-engine-assessment.md) | Gap analysis |
| [Future Ideas](future-ideas.md) | Roadmap candidates |
