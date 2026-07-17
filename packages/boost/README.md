# WireStack Boost

AI tooling for the Wire ecosystem — the wireStack equivalent of [laravel/boost](https://github.com/laravel/boost).
It ships an **MCP server**, **AI guidelines** and **Agent Skills** that help AI coding agents build
high-quality applications with `wire-core`, `wire-forms`, `wire-table` and `wire-sortable`.

## Installation

```bash
composer require nyoncode/wire-boost --dev
php artisan wire-boost:install
```

`wire-boost:install` configures the AI agents you select (Claude Code, Codex, Cursor, Gemini CLI,
GitHub Copilot, Junie): it writes the MCP server entry into the agent's config, merges the wireStack
guidelines into the agent guideline file, and installs the skills. Re-run it any time; it is idempotent.
Use `php artisan wire-boost:update` to refresh guidelines and skills after upgrading wire packages.

## MCP server

The server is started with `php artisan wire-boost:mcp` (registered as the local handle `wire-boost`).
It exposes the following tools:

**WireStack-specific**

| Tool | Purpose |
| --- | --- |
| `application-info` | PHP/Laravel/Livewire versions, installed wire package versions, key config |
| `list-wire-components` | Discover app Livewire components that build wire tables/forms/infolists |
| `describe-table` | Resolve a table's columns, filters, actions, default sort, searchability |
| `describe-form` | Resolve a form's flattened field schema |
| `describe-infolist` | Resolve an infolist's entry schema |
| `validate-wire-component` | Report unknown colors, unregistered icons and unresolvable attribute names |
| `list-component-types` | Built-in types for a category (columns, fields, filters, actions, …) |
| `describe-component-api` | Public fluent API of a component type — signatures, defaults, accepted values |
| `list-icons` | Icon names registered with the wire `IconManager` |
| `wire-config` | Effective `wire-*` configuration |
| `search-wire-docs` | Section-level search over the full bundled wireStack documentation |
| `fetch-wire-doc` | Read a documentation section, or a document outline, by id |

The complete English documentation ships inside the package, so search works offline and without a
checkout of the wire repo. `search-wire-docs` returns ranked **sections** with ids; pass an id to
`fetch-wire-doc` to read one in full.

`validate-wire-component` targets the faults that never throw: an unknown color renders gray, an
unregistered icon renders nothing, and a name the model cannot resolve renders an empty cell — none of
which a render test catches.

**General (parity with laravel/boost)**

`database-schema`, `database-connections`, `database-query` (opt-in), `last-error`, `read-log-entries`,
`get-absolute-url`, `list-artisan-commands`, `list-routes`, `tinker` (opt-in), `browser-logs`.

`database-query` and `tinker` are disabled by default; enable them with `WIRE_BOOST_DATABASE_QUERY=true`
and `WIRE_BOOST_TINKER=true`.

## Extending

Custom guidelines and skills placed in `.ai/guidelines/*` and `.ai/skills/*` are merged in automatically
when you run the installer.
