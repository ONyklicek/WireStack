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
