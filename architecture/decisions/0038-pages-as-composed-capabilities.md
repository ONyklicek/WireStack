# ADR 0038: Pages as Composed Capabilities

## Status

ACCEPTED — 2026-09-25, implemented on `feat/panels-page-chrome` in nine
commits (`8b80c130` … `0cc2549a`). Requested by the repo owner ("Panels|Pages|
Resources vylepšení"; "Pages cokoli z resources co uživatel namapuje ale klidně
i cokoli jiného … třeba kdyby chtěl vystavět kanban"). Plan:
`architecture/plans/panels-pages-and-resources.md`.

## Context

The five resource pages drew a heading and a trail and nothing else, and the
view page had no action runtime at all. A page that was not a resource surface
— a board, a report — could be routed (`RoutePage::make()` accepts any Livewire
component) but had to rebuild the heading, the trail and an action host by
hand, so it looked and behaved unlike the pages around it.

Two shapes were available: a deeper class hierarchy (`Page` at the root, every
resource page a subclass), or capabilities composed into each page.

## Decision

### 1. A page is a Livewire component plus capabilities

`InteractsWithHeaderActions` (declare, find, draw), `HostsPageActions` (the
engine where no table provides one), `InteractsWithPageWidgets`,
`InteractsWithListTabs`, `InteractsWithTrashedRecords`, `BelongsToParentRecord`,
`InteractsWithUnsavedChanges`. The resource pages compose the ones they need;
`Pages\Page` is a thin class composing the common set around a view the
application names. Nothing requires extending it.

The hierarchy was rejected because the pages do not share an action engine:
the list page has `WithTable`'s, and a common base composing `WithActions`
would give it a second modal stack.

### 2. One engine per page, one button view

A page's header actions run through the engine that page already has — the
list's through `findHeaderAction()` on its table, the others through
`WithActions` — and render through `Action::render()` with a per-host
`ResolvesActionClick` (`action-render-unification.md`). A second engine on one
component is refused by construction, not by review.

### 3. Defaults that cannot exceed the route; opt-ins for the rest

*New* is drawn by default because it is a link asking exactly what the create
route's `can:` asks. *Delete*, *Restore* and *Force delete* are builders a page
asks for: who may delete what is the application's rule (the users module will
not delete the last super-admin), and a default button would walk past it.
Without a policy they fall back to the edit page's permission, and without an
edit page they are refused.

### 4. Whole-resource behaviour is declared on the resource

Trash management (`ManagesTrashedRecords`) and nesting (`NestedResource`) are
contracts on the resource, not switches on pages, so the list and the record
pages cannot disagree — a list offering *Restore* on a row whose page 404s, or
a child list scoped differently from its record page, is the failure this
prevents.

### 5. A board is a surface, not a page

`Board` + `WithBoard` live in `wire-sortable`; a board page is a `Page`
composing `WithBoard`. Neither package depends on the other, and the drag is
Livewire's own `wire:sort`.

## Consequences

- An application's own page gets the panel's chrome by extending `Page` or by
  composing `HostsPageActions`.
- The `?action=` palette hand-off moved from `ResolvesOneRecord` to
  `HostsPageActions`; a page that composed `WithActions` beside
  `ResolvesOneRecord` by hand composes `HostsPageActions` instead.
- A table carrying a `TrashedFilter` now resolves keys among trashed rows for
  row actions and keyed selections — the fix trash management needed, owned by
  the table.
- Nesting is one level deep: a route carries one `{parent}`.
