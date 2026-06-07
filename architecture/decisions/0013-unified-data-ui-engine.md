# ADR 0013: Unified Data UI Engine

## Status

ACCEPTED

## Context

The current Wire ecosystem treats Tables, Forms, Actions, and future UI systems (Infolists, Widgets) as isolated systems. This leads to:

- Duplicated logic between Column and Field classes
- Monolithic traits (WithTable.php 600+ lines) containing query building, reflection parsing, modal state, and action execution
- God objects (Column.php 800+ lines, 220+ properties)
- String-only relation parsing (`explode('.', $path)`)
- PHP source code parsing via reflection to guess accessor columns
- No shared metadata, capabilities, state, hydration, or validation infrastructure
- Ad-hoc join generation without deduplication or deterministic aliasing

## Decision

Refactor the architecture into a **Unified Data UI Engine** where all UI layers share core infrastructure:

1. **Metadata System** — immutable, cacheable model/relation/column/accessor metadata
2. **Capability System** — shared capabilities (searchable, sortable, etc.) across all UI systems
3. **Relation AST** — structured relation path parsing instead of string splitting
4. **Query Planning** — separate planning from execution, immutable QueryPlan objects
5. **Query Pipeline** — replace monolithic query methods with composable pipes
6. **Shared Components** — DataComponent base class inherited by both Columns and Fields
7. **Shared Runtime** — state engine, hydration, validation, action pipeline usable by all UI layers

Rendering layers remain separated. Business/data infrastructure becomes shared.

## Implementation

6-phase approach:
- Phase 0: Foundation (Metadata, Capabilities, Relation AST, JoinRegistry, DataComponent)
- Phase 1: Query Planning (QueryPlanner, QueryPlan, aggregate/morph planning)
- Phase 2: Query Execution (Pipeline pipes, QueryExecutor, DB strategies)
- Phase 3: Shared Runtime (State, Hydration, Validation, ActionPipeline, Events)
- Phase 4: UI Layers (refactor Table/Column/Form/Field onto new foundations)
- Phase 5: Extensions (plugins, performance, backward compatibility, docs)

Each phase ends with a working state and green CI.

## Consequences

**Positive:**
- Eliminates code duplication between tables and forms
- Enables future UI systems (Infolists, Widgets) with minimal effort
- Proper query optimization (join deduplication, deterministic aliasing)
- Testable subsystems without Livewire lifecycle
- Plugin-ready architecture

**Negative:**
- Large scope (~85 new classes + ~60 refactored)
- Risk of regression during UI layer refactor (Phase 4)
- Learning curve for contributors due to new abstractions

**Mitigations:**
- Strict phasing ensures each phase is independently deployable
- Backward compatibility layer preserves existing public API
- Comprehensive test coverage required for every new class

## References

- [core/unified-engine.md](../core/unified-engine.md) — Current-state reference for the implemented engine (supersedes the original spec/plan/assessment planning docs)
