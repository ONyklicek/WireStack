# Future Ideas

Features and improvements considered for future releases. Not currently planned — added here when discovered during implementation.

## v0.2.0 Candidates

### New Field Types
- **Repeater** — dynamic array of field groups (add/remove/reorder)
- **TagsInput** — tag input with autocomplete
- **RangeSlider** — numeric range slider
- **Tabs** — tabbed layout component for forms
- **KeyValue** — key-value pair editor
- **MarkdownEditor** — Markdown editor with preview

### Form Enhancements
- **Cross-form validation** — `validateAllForms()` helper for multi-form components
- **Form wizard** — multi-step form with step validation (standalone, not action-modal)
- **Dependent fields** — reactive field visibility/options based on other field values
- **Form state persistence** — save/restore form draft to localStorage or database

### Table Enhancements
- **Column toggling** — user can show/hide columns
- **Column reordering** — drag to reorder columns
- **Export** — CSV/Excel export action
- **Saved filters** — persist filter presets

## Future Package Extraction

When real use cases arise:
- **wire-actions** — extract from `wire-core` (trigger: `wire-infolist` needs actions)
- **wire-notifications** — extract from `wire-core` (trigger: standalone notification UI)
- **wire-modals** — extract from `wire-core` (trigger: standalone modal system)
- **wire-infolist** — read-only record display (like Filament Infolist)

See [ADR 0006](decisions/0006-modular-core-extraction-strategy.md) for extraction strategy.

## Tailwind 4

Full Tailwind 4 support planned for v0.2.0. See [ADR 0005](decisions/0005-tailwind-4-support.md).
