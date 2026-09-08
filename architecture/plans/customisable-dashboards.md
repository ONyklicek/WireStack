# Customisable dashboards

A dashboard a user can rearrange: drag widgets into the order they want, resize
them, take one out, put another back from a tray of pre-configured ones — and
have all of it survive a reload, per user, per dashboard.

Written after the widget expansion landed (`ProgressWidget`, `ListWidget`,
`lazy()`, server-resolved filters, `headerActions()`), because that work put the
last piece in place: a widget now has a **key**, and every trigger already
answers with one widget's `wire:partial` region.

## The two findings that shaped this

**1. Livewire 4 ships the drag and drop, SortableJS included.**

```
vendor/livewire/livewire/dist/livewire.esm.js
  directive("sort")   x-sort:item   x-sort:group   x-sort:config
  handleSelector      sortable-ghost   sortable-chosen   "sortablejs"
```

`x-sort:group` is the tray ↔ grid drag, the ghost is built in, and
`x-sort:config` passes raw SortableJS options through. **This feature ships no
new JavaScript and adds no bytes to any bundle.**

Consequence worth its own change, not this one: `wire-core-sortable-list.js` is
39 kB and it is SortableJS — the library Livewire already puts on every page. A
page with a repeater sends it twice. Not touched here, because that controller
also does morph guards, `<tr>` width locking and revert-on-drop, none of which
`x-sort` does; replacing it is a measured swap with its own drivers.

**2. The per-user store already exists, one package too high.**

`wire-table` has `Preferences/`: a `TablePreferenceDriver` contract, Database /
Session / Null drivers, a `table_preferences` row per `(user_id, table_key)`
holding an open JSON bag, and a `view` dimension for named and shared layouts.
A dashboard layout is that same shape exactly. But `Widgets/` lives in
`wire-core`, and table depends on core, so core cannot reach it.

Building a second store would have been a second implementation of "a per-user
JSON bag keyed by a surface and a user", which the adapter rule forbids. So the
store **moves down** instead: `Foundation\Preferences\*` in core, the three
drivers with it, `wire-table` delegating to it, and `table_preferences` renamed
to `wire_preferences` because it will now hold more than tables.

## Decisions

| Question | Answer |
| --- | --- |
| Widget sizes | **Width and height.** A tile can be 2 columns wide and 2 rows tall |
| Layout model | **Ordered list + (w, h)**, packed by CSS Grid — no x/y coordinates, no collision resolution |
| Editing | **Explicit edit mode.** A "Customise" button reveals the tray, handles and removes; Save / Cancel |
| Per-user | **Opt-in.** A dashboard without `customisable()` behaves exactly as it does today |
| Store | `Foundation\Preferences\*` in core, extracted from `wire-table` |
| Multiple dashboards | Keyed by `Dashboard::key()`; the store's `view` dimension gives named layouts for free |
| `TableWidget` | Compact read-only rows, and it **moves to `wire-table`** — see step 1 |

### Why ordered list + (w, h) rather than coordinates

Because CSS Grid already packs. A tile declares `col-span-w` and `row-span-h`,
the grid places tiles in order, and `grid-auto-rows` gives a row its base
height. Dragging changes the order; resizing changes two integers. What that
buys is everything a coordinate engine would have to be written for — tall
tiles, wide tiles, a mobile layout that collapses to one column by ignoring `h`
— without an engine that has to answer "what is at (3, 1)", resolve overlaps,
and re-pack holes after a removal.

What it gives up: a widget cannot be pinned to an absolute cell. Order decides
position, and a tile too wide for the space left on a row wraps and leaves a
hole, exactly as the current grid does. Named as a limitation rather than
discovered as a bug.

A tile with more content than its `h` scrolls inside itself: `grid-auto-rows` is
a fixed base, so a row that grew to fit its content would make `h` mean nothing.

## Steps

### Step 1 — `TableWidget` renders something — **DONE**

`TableWidget::table(fn (Table $table) => …)` has always rendered a card with a
heading and an empty `<div>`. `getTableCallback()`'s only caller in the whole
repository is a test asserting the getter, and `widgets/table.blade.php` says
`{{-- Table will be rendered by the Livewire component using the tableCallback --}}`.
The docs page shows a full working example with columns and a query.

The cause is structural, not neglect: the class is in `wire-core` and the table
engine is in `wire-table`, which depends on core. From core it cannot be
reached. **So the class moves to `wire-table`** and renders compact read-only
rows there — no toolbar, no filters, no pagination, no bulk actions, staying
inside the host component so the partial regions keep working.

Not a seam in core with a renderer registered from table: that pattern is for
two modules *inside* one package (`RunsComponentActions`). Here the direction
between packages is unambiguous, and the honest fix is to put the class where it
can do its job.

**The gate.** `composer test:core`, `composer test:table`, `npm run docs:api`,
and the upgrade guides in both locales for the namespace change.

### Step 2 — Extract the preference store into core — **DONE**

**Landed.** `Foundation\Preferences\{Contracts\PreferenceDriver, Drivers\*,
Models\Preference, PreferenceManager}` in core, with the three drivers moved
verbatim and `wire-table`'s `TablePreferenceManager` reduced to the one thing
that is genuinely the table's — the config prefix it asks with.
`table_preferences` → `wire_preferences`, `table_key` → `surface_key`, and the
migration **renames** an existing installation's table rather than leaving it
behind.

**What made the resolver reusable** is that the config prefix became a
parameter. Each surface still chooses its own store — a table's layout may be
worth a database row while a dashboard's is not, or the other way round — so
`wire-table.preferences` keeps meaning exactly what it meant, and nothing an
application wrote had to change.

**The evidence the move kept the behaviour**: `wire-table`'s 2567 tests pass
unchanged, its 44 preference tests included. The 11 new ones in core cover what
is new — the surface-agnostic vocabulary, the prefix, and the rename path.

One deliberate loss, session storage only: the session key prefix is `wire.`
rather than `wire-table.`, so an unsaved in-session layout resets once. Database
rows are carried across.

### Step 3 — A layout, applied server-side, with no drag at all — **DONE**

**Landed.** `Dashboard::customisable()` opts in (false by default, so every
dashboard that exists renders exactly as it did). `WithWidgets::widgetLayoutKey()`
is the host-side opt-in — null by default, so no key means no store lookup at all
— and `DashboardPage` bridges the two with the key the dashboard registered
under, the same bridge it already builds for `hookKey()`.

**The layout logic is a value object, not a few lines in the trait.**
`Widgets\Support\WidgetLayout` decides what a stored bag means, because that is
business logic and the coding standard keeps it out of traits — and because it is
the piece with all the edges. Every rule in it exists because the alternative is a
dashboard that renders *wrong* rather than one that renders differently: a key the
declaration no longer has is dropped rather than conjuring a widget, a `w` of 99
is clamped rather than falling through `getColumnSpanClass()`'s match and
rendering as nothing, a key listed twice is placed once at its first position.

**Two distinctions worth the words they cost.** An empty stored list is *not* the
same as nothing stored — the first is a user who took everything off, the second
is a user who has never touched this dashboard. And a layout is a preference,
never a grant: a widget the layout places and a policy hides stays hidden, which
is why the layout is applied *before* the visibility filter rather than instead
of it.

**Height** arrived with `Foundation\Concerns\HasRowSpan`, alongside
`HasColumnSpan`. The grid only sets a row baseline when a widget on it actually
spans rows — fixing a row height for every dashboard would have changed how every
existing one looks, since a card would stop being as tall as its contents.

**The gate.** 13 new tests in core, 3 in panels, and `composer test:core` +
`test:panels` unchanged otherwise. No browser involved, which is the point of
doing this before the drag: when step 4 starts writing layouts, there is a tested
thing for it to write to.

### Step 4 — Edit mode, drag to reorder, resize — **DONE**

**Landed.** `startEditingWidgets()` / `saveWidgetLayout()` /
`cancelEditingWidgets()` / `resetWidgetLayout()` on the host, with the
arrangement held in a public `$widgetLayoutDraft` until Save. The mode is what
gives Cancel something to throw away; a live drag persists on every drop, so a
stray grab would overwrite a layout somebody was happy with with nothing to undo
it. The chrome around the grid stays the caller's — it belongs to whatever
renders the dashboard.

**No revert, and that is a change from what this step expected.** The plan said
the drop would be reverted in the DOM as `wireSortableList` does. It is not:
widget cells carry a `wire:key`, so the morph pairs them by key rather than by
position and the dropped DOM cannot disagree with the server's answer. The
repeater has to revert because its cards are morphed positionally; a keyed grid
does not. `wireSortableList` is not used here either — what it adds over the
plugin is reverting, locking a dragged `<tr>`'s cell widths and guarding a morph
mid-drag, and none of the three applies.

**Two things only a browser could find**, both invisible to a green PHP test:

- `x-sort:item="revenue"` is *evaluated* by the plugin
  (`el._x_sort_key = evaluate(expression)`), so a bare key reads as an
  identifier and throws `ReferenceError: revenue is not defined` — which kills
  the whole Alpine tree and leaves a grid with handles that do nothing. It is
  `@js($widget->getKey())` now, and the PHP assertion that had happily allowed
  the bare form was corrected with it.
- The resize expressions were being built in the template, where the key's
  quotes came out unescaped. They are `Widget::getResizeExpression()` now, beside
  `getActionExpression()` and `getFilterExpression()`, for the reason those two
  exist.

**The gate.** 17 new PHP tests, and `verify-widget-layout` — 11 checks, including
that SortableJS is bound to the grid rather than merely declared on it, and that
the arrangement is still there after a reload.

### Step 5 — The tray — **DONE**

**Landed, and without the new declared thing this step expected.** A "preset"
turned out to be the widget: it already has a key, a heading and a declaration,
and it gained `group()` and `sizes()`. A parallel `WidgetPreset` class would have
been a second way to say what a widget is, and the tray would then have had to
keep the two in step.

**One rule does two jobs.** "Not placed" and "available" are the same state —
nothing records that a widget was removed, it is simply no longer in the layout —
so a removal puts it in the tray by construction rather than through a second
mechanism, and the two can never disagree.

**Tray ↔ grid is one drag**, through `x-sort:group` with a name derived from the
component id, so two dashboards on one page cannot pull tiles out of each other.
A drop on the grid calls `placeWidget()` rather than `moveWidget()`, because a
drop cannot tell a tile moving within the grid from one arriving from the tray —
and should not have to. Buttons do the same two things, which is also what makes
the feature testable without a browser.

**What this cost elsewhere.** The grid view had started reaching for `$this` to
build the tray, which broke `<x-wire::widget-grid>` — the same view is rendered
by a Blade component where there is no Livewire host at all. The payload comes
from `WithWidgets::widgetGridData()` now, one call rather than five keys a caller
assembles, because four of them have to agree and forgetting one is silent.

**The gate.** 15 new PHP tests, and `verify-widget-layout` grew to 16 checks —
including that the tray shares the grid's drag group and that SortableJS is bound
to it, which is what makes a tile droppable into it at all.

### Step 6 — The optional module — **DONE, and the module is not one**

**The package lost its job while the steps before it were being built.** This
step was written to ship "the page, the tray UI and the database driver
registration". By the time it came round, the tray and the edit mode were in
`wire-core` (they belong with the grid), `DashboardPage` was already in
`wire-panels`, and the store had moved to core with its three drivers and a
config prefix — because it turned out to exist already, one package too high
(step 2).

So `wire-module-dashboard` would have owned nothing. A module here is a
ready-made *area* — users, settings, audit, notifications, media — each with its
own models, migrations and domain. This one would have been a Blade partial and a
page subclass over three other packages' work, and "optional" is already what
`Dashboard::customisable()` and the driver config mean: say nothing and no store
is consulted, install nothing and layouts live for the page.

**What was actually missing** was the chrome — the Customise / Save / Cancel /
Reset buttons every caller would otherwise write. That shipped as
`wire-core::widgets.partials.widget-layout-controls`, beside the grid and the
tray it belongs with, and `DashboardPage` includes it. Both partials render
nothing on a dashboard nobody may rearrange, so a page includes them
unconditionally and has no condition to keep in step with one in the grid.

The methods stay public and the chrome stays replaceable: a dashboard inside
somebody's own layout wants its buttons where that layout puts buttons. The
workbench preview writes its own, which is what proves that path still works.

**The gate.** 3 new tests in panels, `composer test:panels` and `test:core`
otherwise unchanged.


## What this does not do

- **User-created dashboards.** The registry holds classes; a dashboard a user
  invents has no class. Layouts here customise dashboards an application
  declared. Whether that is worth changing is a separate question.
- **Absolute placement.** See the layout model above.
- **Per-widget lifecycle actions.** Unchanged: a widget action runs a callback,
  no modal (`architecture/core.md` § Widgets).
