# Wire

Monorepo for the Wire ecosystem – enterprise-grade Livewire components for Laravel.

## Packages

| Package | Description | Standalone |
|---------|-------------|:----------:|
| [`wire-core`](packages/core/) | Shared foundation: traits, actions, modals, notifications, icons | Dependency only |
| [`wire-forms`](packages/forms/) | Form fields, validation, layout components | Yes |
| [`wire-table`](packages/table/) | Table component with inline editing, filters, sorting, pagination | No (requires core + forms) |

## Dependency Graph

```
wire-table
├── wire-forms
│   └── wire-core
└── wire-core
```

## Supported Combinations

| Combination | Use Case |
|-------------|----------|
| `wire-core` | Not typically installed alone (dependency of other packages) |
| `wire-core` + `wire-forms` | Standalone forms in Livewire components |
| `wire-core` + `wire-forms` + `wire-table` | Full table with edit actions and inline editing |

## Modular Architecture

`wire-core` contains four internal modules with strict dependency boundaries:

```
wire-core/
├── Foundation/       ← shared base (traits, icons, colors, components)
├── Actions/          ← row, bulk, header actions (extraction candidate)
├── Notifications/    ← pluggable notification drivers (extraction candidate)
└── Modals/           ← modals, confirmations, wizards (extraction candidate)
```

**Dependency rules:**
- Foundation depends on nothing. All other modules depend on Foundation.
- Actions, Notifications, and Modals do not depend on each other directly. Cross-module communication uses service container resolution.
- When `wire-forms` is installed, it injects form capabilities into Actions via macros (no direct imports).

**Future extraction:** Actions, Notifications, and Modals are prepared for extraction into standalone packages (`wire-actions`, `wire-notifications`, `wire-modals`) when a real use case arises (e.g., building `wire-infolist`). Extraction is a mechanical operation — see [ADR 0006](docs/decisions/0006-modular-core-extraction-strategy.md).

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12
- Livewire 3.x
- Tailwind CSS 3.x
- Node.js & npm (for Vite)

## Installation

```bash
# Full ecosystem (table + forms + core)
composer require nyoncode/wire-table

# Standalone forms (forms + core)
composer require nyoncode/wire-forms
```

After installing, configure Tailwind CSS to scan Wire package views. See the [full installation guide](packages/table/docs/installation.md) for Vite setup, Tailwind content paths, layout template, and troubleshooting.

## Development

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
| [Forms: Overview](docs/forms/overview.md) | Form architecture, WithForms trait, save lifecycle |
| [Forms: Field Reference](docs/forms/fields/) | Per-field documentation (20 types) |
| [Table: Overview](docs/table/overview.md) | Table features and configuration |
| [Migration Guide](docs/migration/v0-to-v1.md) | Migrating from monolithic wire-table |
| [Future Ideas](docs/future-ideas.md) | Planned features for future releases |

## Architecture Decisions

| ADR | Title | Status |
|-----|-------|--------|
| [0001](docs/decisions/0001-action-form-integration.md) | Action ↔ Form Integration | Accepted |
| [0002](docs/decisions/0002-js-alpine-distribution.md) | JS/Alpine Distribution | Accepted |
| [0003](docs/decisions/0003-inline-editing-columns.md) | Inline Editing Columns | Accepted |
| [0004](docs/decisions/0004-notification-driver-defaults.md) | Notification Driver Defaults | Accepted |
| [0005](docs/decisions/0005-tailwind-4-support.md) | Tailwind 4 Support | Accepted |
| [0006](docs/decisions/0006-modular-core-extraction-strategy.md) | Modular Core Extraction Strategy | Accepted |
| [0007](docs/decisions/0007-internal-module-dependencies.md) | Internal Module Dependencies | Accepted |
| [0008](docs/decisions/0008-datetimepicker-unification.md) | DateTimePicker Unification | Accepted |
| [0009](docs/decisions/0009-single-multi-form-coexistence.md) | Single/Multi-Form Coexistence | Accepted |
| [0010](docs/decisions/0010-form-save-notifications-integration.md) | Form Save Notifications Integration | Accepted |
| [0011](docs/decisions/0011-form-config-runtime-separation.md) | Form Config/Runtime Separation | Accepted |
| [0012](docs/decisions/0012-form-make-standalone-usage.md) | Form::make() Standalone Usage | Accepted |

## License

MIT
