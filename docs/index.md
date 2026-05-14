# Wire Ecosystem — Documentation

> Enterprise-grade Livewire components for Laravel.
> PHP 8.2+ | Laravel 10–12 | Livewire 3 | Tailwind 3+ | Alpine.js 3+

---

## Getting Started

- [Installation & Quick Start](getting-started.md) — requirements, setup, first table & form
- [Architecture Overview](architecture.md) — packages, dependency graph, design principles
- [Migration v0 → v1](migration/v0-to-v1.md) — namespace changes, deprecated methods, new features

---

## wire-core

Shared foundation for the entire ecosystem.

| Document | Description |
|----------|-------------|
| [Foundation](core/foundation.md) | Traits, base classes, icons, colors, Blade components |
| [Actions](core/actions.md) | Row, bulk, header actions, groups, modals, forms, lifecycle |
| [Notifications](core/notifications.md) | Notification drivers, builder, custom drivers |
| [Modals](core/modals.md) | Confirmation dialogs, slide-overs, wizards |
| [Unified Engine](core/unified-engine.md) | Metadata, capabilities, relation AST, query planning, state, hydration, validation, action pipeline, events |
| [Plugin Development](core/plugins.md) | Plugin interface, PluginManager, hooks, custom column/filter/pipe extensions |

---

## wire-forms

Standalone form system for Laravel Livewire.

| Document | Description |
|----------|-------------|
| [Forms Overview](forms/overview.md) | Architecture, single/multi-form, standalone, Form API reference |
| [Validation](forms/validation.md) | Field rules, form-level rules, custom messages, ValidationPipeline |
| [Save Lifecycle](forms/save-lifecycle.md) | validate → mutate → beforeSave → persist → afterSave → notify |

### Field Reference

#### Input Fields

| Field | Docs |
|-------|------|
| TextInput | [text-input.md](forms/fields/text-input.md) |
| Textarea | [textarea.md](forms/fields/textarea.md) |
| Select | [select.md](forms/fields/select.md) |
| Checkbox | [checkbox.md](forms/fields/checkbox.md) |
| CheckboxList | [checkbox-list.md](forms/fields/checkbox-list.md) |
| Radio | [radio.md](forms/fields/radio.md) |
| Toggle | [toggle.md](forms/fields/toggle.md) |
| DateTimePicker | [date-time-picker.md](forms/fields/date-time-picker.md) |
| ColorPicker | [color-picker.md](forms/fields/color-picker.md) |
| FileUpload | [file-upload.md](forms/fields/file-upload.md) |
| RichEditor | [rich-editor.md](forms/fields/rich-editor.md) |
| Hidden | [hidden.md](forms/fields/hidden.md) |

#### Layout

| Component | Docs |
|-----------|------|
| Grid | [grid.md](forms/fields/grid.md) |
| Section | [section.md](forms/fields/section.md) |
| Fieldset | [fieldset.md](forms/fields/fieldset.md) |

#### Display

| Component | Docs |
|-----------|------|
| Placeholder | [placeholder.md](forms/fields/placeholder.md) |
| Alert | [alert.md](forms/fields/alert.md) |
| Html | [html.md](forms/fields/html.md) |
| ViewField | [view-field.md](forms/fields/view-field.md) |

---

## wire-table

Enterprise Livewire table component.

| Document | Description |
|----------|-------------|
| [Table Overview](table/overview.md) | WithTable trait, Table API, quick start |
| [Columns](table/columns.md) | All 13 column types, shared API, relations, formatting |
| [Filters](table/filters.md) | SelectFilter, DateFilter, NumberRangeFilter, TernaryFilter, custom |
| [Advanced Features](table/advanced.md) | Sub-rows, summary, polling, lazy loading, performance, debug |

---

## wire-sortable

Drag & drop row reordering plugin for wire-table.

| Document | Description |
|----------|-------------|
| [Sortable Rows](sortable/overview.md) | Installation, SortableTable API, WithSortableRows trait, config, frontend |

---

## Architecture Decisions (ADR)

| # | Decision | Status |
|---|----------|--------|
| [0001](decisions/0001-action-form-integration.md) | Action-Form integration | Accepted |
| [0002](decisions/0002-js-alpine-distribution.md) | JS/Alpine distribution | Accepted |
| [0003](decisions/0003-inline-editing-columns.md) | Inline editing columns | Accepted |
| [0004](decisions/0004-notification-driver-defaults.md) | Notification driver defaults | Accepted |
| [0005](decisions/0005-tailwind-4-support.md) | Tailwind 4 support | Accepted |
| [0006](decisions/0006-modular-core-extraction-strategy.md) | Modular core extraction strategy | Accepted |
| [0007](decisions/0007-internal-module-dependencies.md) | Internal module dependencies | Accepted |
| [0008](decisions/0008-datetimepicker-unification.md) | DateTimePicker unification | Accepted |
| [0009](decisions/0009-single-multi-form-coexistence.md) | Single/multi form coexistence | Accepted |
| [0010](decisions/0010-form-save-notifications-integration.md) | Form save notifications | Accepted |
| [0011](decisions/0011-form-config-runtime-separation.md) | Form config/runtime separation | Accepted |
| [0012](decisions/0012-form-make-standalone-usage.md) | Form::make() standalone usage | Accepted |
| [0013](decisions/0013-unified-data-ui-engine.md) | Unified Data UI Engine | Accepted |
| [0014](decisions/0014-plugin-architecture.md) | Plugin architecture | Accepted |
| [0015](decisions/0015-performance-extensions.md) | Performance extensions | Accepted |

---

## Internal

| Document | Description |
|----------|-------------|
| [Unified Engine Spec](unified-engine-spec.md) | Technical specification |
| [Unified Engine Plan](unified-engine-plan.md) | 6-phase implementation plan |
| [Unified Engine Assessment](unified-engine-assessment.md) | Gap analysis |
| [Future Ideas](future-ideas.md) | Roadmap candidates |
