# Future Ideas

Features and improvements considered for future releases. Not currently planned — added here when discovered during implementation.

> Many items from the original v0.2.0 list have shipped: Repeater, Tags, KeyValue,
> MarkdownEditor, Slider field types, table export (CSV/Excel/PDF), column toggling,
> and column reordering (via `wire-sortable`). Only the items below remain open.

## Field Types

- **Tabs** — tabbed layout component for forms

## Form Enhancements

- **Cross-form validation** — `validateAllForms()` helper for multi-form components
- **Form wizard** — multi-step form with step validation (standalone, not action-modal)
- **Dependent fields** — reactive field visibility/options based on other field values
- **Form state persistence** — save/restore form draft to localStorage or database

## Table Enhancements

- **Saved filters** — persist filter presets

## Future Package Extraction

When real use cases arise:
- **wire-actions** — extract from `wire-core` (trigger: `wire-infolist` needs actions)
- **wire-notifications** — extract from `wire-core` (trigger: standalone notification UI)
- **wire-modals** — extract from `wire-core` (trigger: standalone modal system)
- **wire-infolist** — read-only record display (like Filament Infolist)

See [ADR 0006](decisions/0006-modular-core-extraction-strategy.md) for extraction strategy.

## Tailwind 4

Tailwind 4 testing/support tracked separately. See [ADR 0005](decisions/0005-tailwind-4-support.md).
