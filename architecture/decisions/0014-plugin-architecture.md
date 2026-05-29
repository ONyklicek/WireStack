# ADR 0014: Plugin Architecture

## Status

ACCEPTED

## Context

The Wire ecosystem needs a standardized way for third-party packages and application-level code to extend tables, forms, and the query pipeline. Currently, extension points exist (QueryPipe, SearchStrategy, NotificationDriver, ActionPipeline stages) but there is no unified registration mechanism or lifecycle management.

Without a plugin system:
- Extensions must manually wire into service providers
- No standard discovery or registration pattern
- No lifecycle guarantees (register before boot)
- Query pipes, column types, and filter types cannot be added declaratively

## Decision

Introduce a **Plugin architecture** with three components:

### 1. Plugin Interface
A `Plugin` contract requiring:
- `getId()` — unique identifier
- `register(PluginManager)` — called during service provider registration
- `boot(PluginManager)` — called during service provider boot

### 2. PluginManager
Central registry providing:
- Plugin lifecycle management (register → boot, boot-once guard)
- Query pipe extension (`addQueryPipe`)
- Column/filter type registration (`addColumnType`, `addFilterType`)
- Hook system for cross-cutting concerns (`hook`, `runHook`)

### 3. Hook System
Named hooks that plugins can listen to:
- `table.configuring` — before table config finalization
- `table.querying` / `table.queried` — around query execution
- `form.saving` / `form.saved` — around form save
- `action.executing` / `action.executed` — around action execution

Hooks receive and return payload arrays, allowing modification by multiple listeners.

### Registration
Plugins are registered via config (`wire-core.plugins` array) and resolved through the container during the service provider lifecycle.

## Consequences

- Third-party packages can extend the ecosystem through a single interface
- Plugins get lifecycle guarantees (register before boot)
- Hook system enables audit logging, telemetry, and cross-cutting concerns without modifying core code
- Duplicate plugin registration is prevented by ID uniqueness check
