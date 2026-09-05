## wire-admin

The **optional** admin shell: a layout and a sidebar over what is already registered. It requires
`wire-panels` and **nothing requires it** — a composer boundary is the opt-in, so an application that wants
resource pages and its own chrome simply does not install this (ADR 0028).

- **Installing is not adopting.** No provider sets `livewire.component_layout`. A page renders inside the
  shell only once the application's own layout view says so, and the sidebar works alone inside any frame.
@verbatim
- **Two tags, both class-based**: `<x-wire-admin::layout>` (slots: `head`, `brand`, `topbar`, `user`, default)
  and `<x-wire-admin::sidebar :linked-only="false" :zone="…" :active-key="…" />`.
@endverbatim
@verbatim
- **The application writes the layout view, the package writes the frame.** A layout that names
  `<x-wire-admin::layout>` and fills slots is what a consuming app owns; `config('livewire.component_layout')`
  points at *that* view, never at `wire-admin::layout` directly (the component needs its props).
@endverbatim
- **Slots, never configuration.** There is no `Panel` object, no branding/colour/auth config and no URL scheme
  — that is the panel-builder drift ADR 0020 named. `vendor:publish --tag=wire-admin::views` is how markup
  changes.
- **It reads seams, holds no state**: `Workspace::navigation($zone, $linkedOnly)` for the menu,
  `ResolvesPageUrls` for every link (null until a package owns routing), `Zone::current()` /
  `Zone::currentKey()` for the zone and the active entry.
- **Zone and active key are read at page render, in the component constructor** — never re-derived per render.
  Inside a Livewire update `Route::currentRouteName()` is `livewire.update`, so a re-derived answer is right
  once and null forever after, while rendering perfectly (ADR 0027).
- **An unrouted entry keeps its row and loses its link** (`aria-disabled`), which is the honest picture of a
  half-routed catalogue; `:linked-only="true"` drops those rows instead.
- **The mobile handle listens to the media query, not to `resize`** — the same query the `lg:` classes are
  matched on, so the two cannot disagree at the boundary. A driver caught the resize version failing.
