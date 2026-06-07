# Architecture Index

This directory is split for low-token navigation. Start with the smallest relevant file.

## Read Path

1. `../CLAUDE.md`
2. One package doc:
   - `core.md`
   - `forms.md`
   - `table.md`
   - `sortable.md`
3. `integrations.md` if the change crosses package boundaries
4. `audit.md` for full analysis, inconsistency hunting, or review
5. ADRs in `decisions/` only when current behavior or tradeoffs are unclear

## Package Docs

- `core.md`
  Shared foundation, actions, modals, notifications, widgets, core runtime
- `forms.md`
  Form config/runtime split, field system, save lifecycle, validation
- `table.md`
  Table config, columns, filters, exports, query bridge, Livewire state
- `sortable.md`
  Table plugin wiring, reorderability, macros, persistence
- `integrations.md`
  Package seams, service-provider boot flow, downstream test matrix, workbench/docs flow
- `audit.md`
  Full-project analysis workflow, inconsistency checklist, review output format

## Existing Deep References

- `core/unified-engine.md`
- `core/plugins.md`
- `core/notifications.md`
- `future-ideas.md`
- `plans/v1-gaps.md`

## ADRs

Key ADR clusters:

- `0001`, `0010`, `0011`, `0012`: forms lifecycle and usage
- `0006`, `0007`, `0013`, `0015`, `0016`: core runtime and module wiring
- `0008`, `0009`: form component and runtime design
- `0014`: plugin architecture
- `0017`: ERP/CRM target application architecture

Read ADRs only when the code no longer makes intent obvious.
