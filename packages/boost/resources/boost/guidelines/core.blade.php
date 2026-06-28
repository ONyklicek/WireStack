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

### Discover the API with the wire-boost MCP server

- `search-wire-docs` — confirm conventions before writing code.
- `list-component-types` — list available columns, fields, filters, actions, entries, widgets.
- `describe-component-api` — see the fluent methods of a specific type.
- `list-wire-components`, `describe-table`, `describe-form`, `describe-infolist` — inspect existing components.
- `list-icons` — valid icon names for `->icon()`.
