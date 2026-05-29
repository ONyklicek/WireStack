# ADR 0003: Inline Editing Columns

## Status
Accepted

## Context
`TextInputColumn`, `SelectColumn`, and `ToggleColumn` provide inline editing in table cells. They share some concepts with Form Fields (validation, options, state management) but have a very different rendering model (inline in a table cell vs. standalone form).

Should these columns internally use `Field` from `wire-forms`?

## Decision
**Keep inline editing columns as standalone implementations in `wire-table`.**

Reasons:
1. **Different rendering model.** Inline columns render as compact table cells with Livewire `wire:change` handlers. Form Fields render as full form controls with labels, help text, wrappers, etc. The overlap is superficial.
2. **Different validation flow.** Inline columns validate per-cell via `updateTableCell()`. Form Fields validate as part of a form submission. Integrating would add complexity without benefit.
3. **Minimal shared code.** The actual shared logic (options array, rules array) is trivial. Extracting it to a shared concern would be over-engineering.
4. **No breaking change.** Keeping standalone means no API changes for existing users.

## Consequences
- **Good:** Simple, self-contained column implementations.
- **Good:** No coupling between inline editing and the Forms subsystem.
- **Trade-off:** Some minor duplication (e.g., `options()` method exists on both `SelectColumn` and `Select` field). This is acceptable given the different use cases.
