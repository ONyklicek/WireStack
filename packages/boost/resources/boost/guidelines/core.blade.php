## WireStack

WireStack is a set of Livewire packages for building admin UIs with fluent, Nova/Filament-style APIs:

- **wire-core** — shared foundation: actions, modals, notifications, infolists, widgets, icons, colors.
- **wire-forms** — form fields, validation, save lifecycle.
- **wire-table** — data tables: columns, filters, actions, summaries, sub-rows, exports.
- **wire-sortable** — drag & drop row/column reordering for wire-table.

Dependency graph (each depends only on those to its right):

    wire-sortable -> wire-table -> wire-forms -> wire-core

### Conventions

- **Fluent, declarative APIs.** Build UI by composing objects: `TextColumn::make('name')->sortable()->searchable()`.
  Prefer this over ad-hoc Blade.
- **Canonical ownership.** Shared behaviour lives once in `wire-core` Foundation concerns
  (`HasColor`, `HasIcon`, `HasSize`, `HasVisibility`, `HasName`, `HasLabel`). Extend the existing concern
  instead of creating a local variant.
- **Render reusable markup from PHP**, returning `Illuminate\Contracts\Support\Htmlable` via `getXHtml()`
  methods; Blade only consumes the rendered HTML.
- **Components are created with `::make($name)`** and configured by chaining setter methods that return `$this`.
- **Failures throw; they are never returned as an error shape.** Every wire exception is `final`, lives in
  its package's `Exceptions/`, and implements `WireCore\Foundation\Contracts\WireException` — so
  `catch (WireException $e)` catches the whole stack. Each extends the SPL class the failure really is
  (`InvalidArgumentException` = bad argument, `RuntimeException` = bad state), which is also why catching
  the SPL class keeps working. Build them with named constructors
  (`TableHasNoDataSourceException::make()`), not `new`. See ADR 0022.

### Discover the API with the wire-boost MCP server

- `search-wire-docs` — find the documentation section that answers the question. The full wireStack
  documentation is indexed by section, not summarised.
- `fetch-wire-doc` — read a section in full by the id `search-wire-docs` returned. Do not stop at the snippet.
- `list-component-types` — list available columns, fields, filters, actions, entries, widgets.
- `describe-component-api` — see the fluent methods of a specific type, their defaults, and the values
  each parameter accepts.
- `list-wire-components`, `describe-table`, `describe-form`, `describe-infolist` — inspect existing components.
- `list-icons` — valid icon names for `->icon()`.
- `validate-wire-component` — **run this after writing or editing a component.** An unknown color renders
  gray, an unregistered icon renders nothing, and a name the model cannot resolve renders an empty cell.
  None of the three throws, so a passing render test does not rule any of them out.

### Versions and the 1.x → 2.0 upgrade

The current line is **2.0**: it needs **Livewire 4**, PHP 8.2+, Laravel 12.61+ / 13.12+. The 1.x line stays
on Livewire 3 and no release runs on both, so Livewire is upgraded first.

When moving an application across, read the guide before changing any constraint — `fetch-wire-doc` with
`docs/start/upgrade.md`, and `search-wire-docs` for any symbol that stopped resolving. What breaks loudest:

- The nine `NyonCode\WireCore\Concerns\*` trait shims are gone — import from `Actions\Concerns\*`
  (`Foundation\Concerns\HasColor` for colors). So is `WireTable\Concerns\TableQueryService` (now `Services\`).
- `Widget::lazy()` / `isLazy()` are gone and never deferred anything; defer the component instead.
- The registration and routing contracts were renamed with no aliases: `NavigationSource` →
  `Foundation\Registration\Contracts\RegistrySource`, and `ProvidesResourcePages` /
  `ConfiguresResourceRoutes` / `RoutePage` → `WireCore\Foundation\Routing\…`.

And what breaks silently: table and field views published from 1.x keep working while missing the new row
markup, the gesture markup and the `Alpine.data()` field controllers — re-publish them
(`--tag=wire-table::views --force`) and add `@@wireStackScripts` to the layout `<head>`.
