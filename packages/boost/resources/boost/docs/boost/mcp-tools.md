---
title: MCP Server & Tools
order: 30
summary: The wire-boost MCP server and the tools it exposes to AI agents.
---

# MCP Server & Tools

Wire Boost provides an [MCP](https://modelcontextprotocol.io/) server built on
[Laravel MCP](https://github.com/laravel/mcp). It is registered under the local handle `wire-boost`
and started with:

```bash
php artisan wire-boost:mcp
```

Agents normally start it for you from the configuration written by [`wire-boost:install`](installation.md).

## WireStack tools

These are the reason to use Wire Boost — they let an agent inspect your actual wire components and the
component vocabulary.

| Tool | Description |
|------|-------------|
| `application-info` | PHP / Laravel / Livewire versions, the installed wire package versions — the stack, the optional panel and admin layers, and the ready-made modules — the companion packages a module switches surfaces on for (Fortify, `laravel/passkeys`, the permission package), and key effective config. |
| `list-wire-components` | Discover the app Livewire components that build a wire table, form, or infolist. |
| `describe-table` | Resolve a table's columns, filters, header/row/bulk actions, default sort, and searchability. |
| `describe-form` | Resolve a form's flattened field schema (name, label, type, wrapping layout). |
| `describe-infolist` | Resolve an infolist's entry schema. |
| `describe-resource` | List the registered resources — key, model, labels, which surfaces each declares, and its navigation entry. |
| `describe-module` | List the domain modules — id, dependencies, and the resources, dashboards and navigation group each declares. |
| `validate-wire-component` | Build a component — a table, form, infolist or a page that hosts actions — and report unknown colors, unregistered icons, names the model cannot resolve and faults inside every action's modal form. |
| `list-component-types` | List the built-in types for a category: `columns`, `filters`, `fields`, `actions`, `infolist-entries`, `panel-entries`, `widgets`, `modals`, `layouts`. |
| `describe-component-api` | List the public fluent API of a component type — signatures, defaults, and the values each parameter accepts (an FQCN or a short name like `badge-column`). |
| `list-icons` | Icon names registered with the wire `IconManager` (for `->icon()`). |
| `wire-config` | The effective `wire-*` configuration, or a single dotted key. |
| `search-wire-docs` | Search the full wireStack documentation and return the most relevant **sections**. |
| `fetch-wire-doc` | Read a documentation section (or a document outline) in full, by id. |

### Example

Asking an agent to add a status column to a table typically goes:

1. `list-component-types` with `category: columns` → sees `badge-column` exists.
2. `describe-component-api` with `class: badge-column` → learns `->color()` and the colors it accepts.
3. `describe-table` on your component → matches the existing column conventions.
4. Writes `BadgeColumn::make('status')->color(...)`.
5. `validate-wire-component` on your component → confirms the color, icon and attribute all resolve.

## Documentation search

The **entire English documentation** ships inside the package — not a summary of it — so the tools work
in your app without network access or a checkout of the wire repo.

`search-wire-docs` indexes it by **section**, not by file, and ranks with BM25 plus a bonus for terms that
match a section's heading or its document title. A result carries an `id`, a `breadcrumb`
(`BadgeColumn > Badge Colors`), the owning `package` and a snippet:

```json
{
  "id": "docs/table/columns/badge.md#badge-colors",
  "breadcrumb": "BadgeColumn > Badge Colors",
  "package": "wire-table",
  "score": 25.13,
  "snippet": "Set the pill colour with the color helper."
}
```

Pass that `id` to `fetch-wire-doc` to read the section in full. Passing a document id
(`docs/table/columns/badge.md`) returns its outline instead — whole pages run to tens of kilobytes, so an
outline plus targeted section fetches is cheaper and more accurate. Add `full: true` for the entire page.

Filter by package with `package: wire-table` (the `wire-` prefix is optional). The accepted values are
`wire-core`, `wire-forms`, `wire-table`, `wire-sortable`, `wire-panels`, `wire-admin`, `wire-modules` and
`wire-boost` — `wire-modules` covers all six ready-made modules at once, because `docs/modules/` is where
every one of them is documented and no single package owns that directory. Add your own Markdown to the
index with `wire-boost.docs.paths`; see [Configuration](../start/configuration.md).

## Validation

`validate-wire-component` exists because the three most common faults in generated wire code **do not
throw**, so a test that asserts the component renders stays green:

| Fault | What actually happens |
|-------|-----------------------|
| `->color('bleu')` | `Color::resolve()` falls back to gray — the badge just renders gray. |
| `->icon('heroicon-nope')` | Nothing renders where the icon should be. |
| `TextColumn::make('titel')` | The cell renders blank. |

The tool builds the component, then checks it against the canonical vocabularies — the `Color` enum, the
registered `IconManager` names, and the model's real attributes (columns, casts, `$appends`, accessors and
relation paths). Each finding names the target and suggests the near miss:

```json
{
  "severity": "warning",
  "rule": "unknown-attribute",
  "target": "columns.titel",
  "message": "[posts] has no attribute [titel]; it will render blank.",
  "suggestions": ["title"]
}
```

Attribute checks need a reachable database table; without one they are skipped (reported as such) rather
than guessed at. Columns that compute their own value via `->state()` are never flagged.

A rollup column is checked for its **relation**, not its name. `TextColumn::make('clients_count')->counts('clients')`
is named after the attribute Eloquent writes the count to, which no model declares, so the tool asks
whether `clients` is a relation instead. When it is not, the finding is `unknown-relation`, an error,
because the query throws when it tries to load the rollup.

### Action forms

An action's modal form is built only when someone clicks, so a fault inside it gets past every render
test. The tool builds each one the way the modal would: a row, bulk or header action of a table, a
page's header actions, the component's own `actions()`, every action inside an `ActionGroup`, and each
step of a wizard. Each field is then checked for its icon and color. Findings are targeted down to the
field, and `actionForms` counts the fields each form had:

```json
{
  "kind": "actions",
  "actionForms": { "pageHeaderActions.publish": 2, "pageActions.onboard": 3 },
  "diagnostics": [
    {
      "severity": "error",
      "rule": "unknown-color",
      "target": "pageActions.onboard.form.plan",
      "message": "Color [blu] is not a wire color; it will silently render as gray.",
      "suggestions": ["blue"]
    }
  ]
}
```

A page with no `table()`, `form()` or `infolist()` is inspected for its actions alone (`kind: actions`).

Each form is built against what its host would hand it. A row action gets a row of the table, and a bulk
action gets a selection holding that row. A table's header action gets no record, and a page's actions get
the page's own record. So a `form(fn (Post $record) => …)` is built the way a click would build it. When a
form throws and the record it expects was not there to give, the table being empty or the page lacking a
`record`, the finding is a `needs-record` warning rather than an error. The form is not known to be broken,
only unbuildable here.

Each builder and each action form is built on its own. When one of them throws, for example a form that
calls a service while it builds, the result is a `build-failed` finding against that target
(`form()`, `pageActions.dns.form`). Everything beside it is still checked.

### Record pages and a single action

Two optional arguments narrow what is built:

| Argument | Effect |
|----------|--------|
| `record` | A record key, set on the component's public `$record` before anything is built, as a view or edit page's own mount would. Without it a record page reports `build-failed` with "mounted without a record". The page's record is also what record-aware action forms (`form(fn ($record) => …)`) receive. |
| `action` | Check only this action: its icon, its color and its modal form. A name that matches nothing is an `unknown-action` error that suggests the near miss. |

```json
{ "component": "App\\Livewire\\Servers\\ViewServer", "record": "42", "action": "deploy" }
```

## General tools (Laravel Boost parity)

| Tool | Description |
|------|-------------|
| `database-schema` | Tables and columns, optionally for one table or connection. |
| `database-connections` | Configured connections and the default. |
| `database-query` | Execute a read-only `SELECT` (disabled by default). |
| `last-error` | The most recent error from the log file. |
| `read-log-entries` | The last N log lines. |
| `get-absolute-url` | Convert a relative path to an absolute URL. |
| `list-artisan-commands` | Available Artisan commands and descriptions. |
| `list-routes` | Application routes with methods, URI, name, and action. |
| `tinker` | Evaluate PHP in the app context (disabled by default). |
| `browser-logs` | Recent browser console entries from the configured log file. |

## Safety

`database-query` and `tinker` execute code or read arbitrary data, so they are **off by default**.
Enable them explicitly:

```dotenv
WIRE_BOOST_DATABASE_QUERY=true
WIRE_BOOST_TINKER=true
```

See [Configuration](../start/configuration.md) for the full `wire-boost` config reference.
