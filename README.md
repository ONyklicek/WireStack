# Wire

Monorepo for the Wire ecosystem – enterprise-grade Livewire components for Laravel.

## Packages

| Package | Description | Standalone |
|---------|-------------|:----------:|
| [`wire-core`](packages/core/) | Shared foundation: traits, actions, modals, notifications, icons | Dependency only |
| [`wire-forms`](packages/forms/) | Form fields, validation, layout components | Yes |
| [`wire-table`](packages/table/) | Table component with inline editing, filters, sorting, pagination | No (requires core + forms) |
| [`wire-sortable`](packages/sortable/) | Drag and drop row and column reordering for Wire Table | Optional |

## Supported Combinations

| Combination | Use Case |
|-------------|----------|
| `wire-core` | Not typically installed alone (dependency of other packages) |
| `wire-core` + `wire-forms` | Standalone forms in Livewire components |
| `wire-core` + `wire-forms` + `wire-table` | Full table with edit actions and inline editing |

## Requirements

- PHP 8.2+
- Laravel 12.61+ or 13.12+
- Livewire 3.x
- Tailwind CSS 3.x
- Node.js & npm (for Vite)

`nyoncode/laravel-package-toolkit` `^2.4` comes with the packages and sets that
Laravel floor — see [Getting Started → Requirements](docs/getting-started.md#requirements).

## Installation

```bash
# Full ecosystem (table + forms + core)
composer require nyoncode/wire-table

# Standalone forms (forms + core)
composer require nyoncode/wire-forms
```

After installing, configure Tailwind CSS to scan Wire package views. See the [Getting Started guide](docs/getting-started.md) for Vite setup, Tailwind content paths, layout template, and troubleshooting.

## Development

Maintainer architecture notes and decision records live outside user documentation in [architecture/](architecture/README.md).

### Setup

```bash
git clone https://github.com/NyonCode/wire.git
cd wire
composer install
```

### Running Tests

```bash
# All tests
composer test

# Per-package
composer test:core
composer test:forms
composer test:table
```

### Code Quality

```bash
# Laravel Pint
composer lint

# PHPStan
composer analyse
```

## Documentation

| Section | Description |
|---------|-------------|
| [Core: Foundation](docs/core/foundation.md) | Shared traits, icons, colors, base classes |
| [Core: Actions](docs/core/actions.md) | Row, bulk, header actions, action groups |
| [Core: Notifications](docs/core/notifications.md) | Notification drivers and customization |
| [Core: Modals](docs/core/modals.md) | Modals, confirmations, slide-overs, wizards |
| [Core: Plugins](docs/core/plugins.md) | App and package extension points |
| [Core: Audit Log](docs/core/audit.md) | Audit model changes and table-related events |
| [Project Map](docs/project-map.md) | Package overview, install paths, source layout |
| [Configuration](docs/configuration.md) | Published config files and environment variables |
| [Authorization](docs/authorization.md) | Gates, policies, permissions, and callbacks |
| [Forms: Overview](docs/forms/overview.md) | Form setup, WithForms trait, save lifecycle |
| [Forms: Field Reference](docs/forms/fields/index.md) | Per-field documentation for built-in form components |
| [Table: Overview](docs/table/overview.md) | Table features and configuration |
| [Table: Actions](docs/table/actions.md) | Row, bulk, and header actions |
| [Table: Exports](docs/table/exports.md) | CSV, Excel, and PDF exports |
| [Table: Notifications](docs/table/notifications.md) | User feedback and notification drivers |
| [Table: Sub-Rows](docs/table/sub-rows.md) | Child rows and grouped detail views |
| [Sortable: Overview](docs/sortable/overview.md) | Drag and drop sorting for rows and columns |

## License

MIT
