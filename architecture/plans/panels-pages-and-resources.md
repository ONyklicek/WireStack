# Panels — pages and resources, the second pass

Written 2026-09-25 at the repo owner's request ("Panels|Pages|Resources
vylepšení" — all eight gaps, plus a board). Package: `wire-panels`, with the
occasional seam in `wire-core` and `wire-sortable`.

## Where it stands

A resource declares surfaces; five abstract Livewire pages render them; the
router gives them URLs; the menu lists them. Any Livewire component can already
be a page — `RoutePage::make(TaskBoard::class)` in some owner's `pages()` gives it
a URL, a `can:` guard, a menu entry and, with `{record}` in its URI, a tab.

What is missing is measured, not guessed (`packages/panels`, `origin/2.x`
`91581be8`):

| # | Gap | Evidence |
| --- | --- | --- |
| 1 | No page header actions | `partials/header.blade.php` draws crumbs and a title; `ViewPage` hosts no action runtime at all (its own docs say so) |
| 2 | No resource generator | `Resources/Console/` holds `MakeDashboardPageCommand` only |
| 3 | No soft-delete support | nothing in `packages/panels/src` mentions `trashed` or `restore` |
| 4 | An edit page leaves without a word | `StateManager::isDirty()` exists; no page reads it |
| 5 | No list tabs, no header widgets | `ListPage` renders `{{ $this->table }}` and nothing else |
| 6 | No single-page (modal) resource | every record surface is its own page |
| 7 | Registration is config-only | `ResourceRegistry`'s own docblock names attribute discovery as the second path |
| 8 | Two crumbs at most | `BelongsToResource::breadcrumbs()`; no nested resource |
| 9 | No board (kanban) surface | table, form, infolist, widgets exist; nothing arranges records in lanes |

And the one underneath them: **the chrome belongs to the five resource pages
only.** A page of the application's own — a board, a calendar, a report — has
to rebuild the heading, the crumbs and the action host by hand.

## Shape

Composition, not a deeper hierarchy (`AI_CODING_STANDARD.md` § Abstract
Classes). A page is a Livewire component plus capabilities:

| Capability | Owner | Used by |
| --- | --- | --- |
| Header actions — declare, authorize, render | `Pages\Concerns\InteractsWithHeaderActions` | every page |
| Running them where no table does | `Pages\Concerns\HostsPageActions` (over `WithActions`) | create, edit, view, `Page` |
| Running them where a table does | `ListPage` hands them to the table's own header-action registry | list |
| The page itself, for anything else | `Pages\Page` — thin, `$view` for the content | the application's own pages |

A page's action runs through the host engine that page already has. The list
page has `WithTable`'s, and a second engine beside it would be two modal stacks
on one component; so the list's page actions are found by the table's
`findHeaderAction()` and clicked through `HeaderActionClickResolver`. The other
pages compose `WithActions` and click through `MountActionClickResolver`. The
button is `Action::render()` in both — one view, two resolvers
(`action-render-unification.md`).

## Steps

Each is its own commit, with tests, EN/CS docs and the boost guideline.

1. **`Page` and header actions.** *(done — `8b80c130`)* *New* on the list by default (a link to
   the create page, when reachable); *Delete* on edit and view on request
   (`deleteHeaderAction()` — policy, then the edit page's permission, then no). A page adds its own by overriding
   `headerActions()`.
2. **Unsaved changes.** *(done — `f940a0be`)* The edit and create pages warn before a navigation
   loses typed input — the browser's `beforeunload` and Livewire's
   `wire:navigate`, both from the form's own dirty state.
3. **List tabs and page widgets.** `tabs()` on the list — named query scopes
   with counts, kept in the URL. Widgets above and below any page's content.
4. **Soft deletes.** Detected from the model. A *Trashed* filter on the list,
   restore and force-delete on the row, the bulk bar and the record pages; the
   record pages resolve `withTrashed()`.
5. **Simple resources.** `ManagePage` — the list, with create and edit opened
   as modals over it, for a resource whose form is too small for its own page.
6. **CLI.** Asked for on 2026-09-25 ("doplnil bych ještě CLI"). Everything a
   page or a resource is made of, generated in the shape above, and one command
   that reads back what is there:
   - `make:wire-resource Order` — the resource and its list/create/edit/view
     pages with `pages()` declared; `--model=`, `--generate` (columns and fields
     from the table's schema), `--view`, `--simple` (step 5), `--soft-deletes`
     (step 4), `--register` (adds it to `config('wire-core.resources')`).
   - `make:wire-page TaskBoard` — a `Pages\Page` and its content view;
     `--resource=Order` makes it a record page (`{record}` URI, composes
     `ResolvesOneRecord`), printed as the `RoutePage` line to paste.
   - `make:wire-relation-manager Order items` — the relation-scoped table, and
     the `relationManagers()` line to paste.
   - `wire:resources` — every registered resource with its surfaces, pages,
     routes per zone and permissions: `describe-resource` for a terminal.
   Stubs publishable, like the dashboard page's already are.
7. **Discovery.** `#[AsResource]` plus a scanned directory in config; the
   config list stays the reference path.
8. **Nested resources.** A resource that belongs to a parent record: its pages
   under the parent's URL, the parent in the trail, its query scoped to it.
9. **Board.** A lanes-of-cards surface over a model and a state column, drag to
   move between lanes and to reorder inside one, rendered by a `BoardPage`.
   Belongs to `wire-sortable`, which already owns drag and order persistence.

## Out of scope

- Changing `WithTable`'s or `WithActions`' engines. Everything above is an
  owner-layer change that consumes them.
- A panel object. The registry of class names stays the model (ADR 0020).
