---
title: MCP Server & Tools
order: 30
summary: The wire-boost MCP server and the twenty tools it exposes to AI agents.
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
| `application-info` | PHP / Laravel / Livewire versions, installed wire package versions, and key effective config. |
| `list-wire-components` | Discover the app Livewire components that build a wire table, form, or infolist. |
| `describe-table` | Resolve a table's columns, filters, header/row/bulk actions, default sort, and searchability. |
| `describe-form` | Resolve a form's flattened field schema (name, label, type, wrapping layout). |
| `describe-infolist` | Resolve an infolist's entry schema. |
| `list-component-types` | List the built-in types for a category: `columns`, `filters`, `fields`, `actions`, `infolist-entries`, `widgets`, `modals`. |
| `describe-component-api` | List the public fluent API of a component type (accepts an FQCN or a short name like `badge-column`). |
| `list-icons` | Icon names registered with the wire `IconManager` (for `->icon()`). |
| `wire-config` | The effective `wire-*` configuration, or a single dotted key. |
| `search-wire-docs` | Search the bundled guideline and skill corpus for conventions and examples. |

### Example

Asking an agent to add a status column to a table typically goes:

1. `list-component-types` with `category: columns` → sees `badge-column` exists.
2. `describe-component-api` with `class: badge-column` → learns `->color()`, `->icon()`, …
3. `describe-table` on your component → matches the existing column conventions.
4. Writes `BadgeColumn::make('status')->color(...)`.

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

See [Configuration](../configuration.md) for the full `wire-boost` config reference.
