# Unified Data UI Engine — Current State Assessment

## What Exists and Can Be Reused

| Area | Current State | Reusability |
|------|--------------|-------------|
| Monorepo structure | 3 packages (core/forms/table), CI, PHPStan, Pint | **High** — keep as-is |
| Action system | BaseAction, Action, BulkAction, HeaderAction, ActionGroup, ActionHalt, ModalStep | **Medium** — good foundation, missing ActionPipeline/Context/Result |
| Modal system | Modal, SlideOver, ConfirmationDialog, Wizard | **Medium** — exists but usage is table-specific |
| Notification system | NotificationManager, 4 drivers, pluggable | **High** — already well modular |
| Foundation Concerns | 20+ traits (HasColor, HasIcons, HasLabel, HasState, ...) | **High** — already shared |
| Form system | Form, Config/Runtime separation, 20+ field types, StateManager, SaveHandler | **Medium** — good API but doesn't share infrastructure with table |
| Column system | 13 column types, inline editing, summaries, sorting, searching | **Low** — monolithic Column.php (800+ lines), all in one class |
| Filter system | 5 filter types | **Medium** — small, standalone classes |
| WithTable trait | 600+ lines — query building, reflection, modals, actions | **Low** — exactly what the spec says NOT to do |
| Blade views | Complete set for table/forms/actions/modals | **High** — rendering layer stays |

## 10 Critical Problems

### 1. WithTable.php = mega-trait (main problem)
- 600+ lines of logic directly in trait
- Reflection parsing of accessors (`file()` reads PHP source)
- Query building inline
- Metadata analysis inline
- Join logic inline
- Modal state management inline
- Action execution inline

### 2. Column.php = god object
- 800+ lines, 220+ properties
- Combines: metadata + query + display + editing + filtering + summaries + styling
- No separation of concerns
- Duplicates concepts that should be shared with Form fields

### 3. String-only relation parsing
- `explode('.', $relation)` in `getRelatedModel()` and `getRelatedModelColumns()`
- No AST, no RelationSegment objects
- No RelationGraph

### 4. No Query Planner
- Joins generated ad-hoc inside sorting/searching/filtering
- No QueryPlan object
- No JoinRegistry — duplicate joins possible
- No deterministic aliasing

### 5. Accessor reflection = anti-pattern
- `extractColumnsFromMethodSource()` reads PHP files via `file()`
- `guessColumnsFromName()` guessing by name (hardcoded patterns like 'full_name' → ['first_name', 'last_name'])
- `findColumnsInSource()` regex matching against source code
- Spec explicitly prohibits this ("DO NOT parse accessor PHP bodies")

### 6. No Metadata cache
- Reflection parsing every request
- Schema discovery every request
- No cache layer

### 7. No Capability system
- searchable/sortable/filterable are isolated booleans on Column
- Not shared across forms/tables

### 8. No Event system
- No TableSearching, TableFiltering, ActionExecuting events
- No telemetry/audit capability

### 9. No shared State infrastructure
- Table has its own state in 30+ Livewire public properties in WithTable
- Form has its own StateManager
- No shared StateContainer

### 10. No shared Hydration
- Form has SaveHandler
- Table has nothing shared
- Inline editing is ad-hoc

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Scope of work | Massive change across 220+ classes | Strict phasing, each phase = working state |
| Backward compatibility | Existing API must keep working | Compatibility layer + deprecation |
| Eloquent integration | Depth of integration with Builder/Relations | Reuse Eloquent, NEVER replace |
| Morph relations | Complex edge case | Dedicated MorphRelationStrategy |
| Performance regression | New abstraction layer = overhead | Benchmarks after each phase |
| Testability | New code must be testable without Livewire | Unit tests for every subsystem |

## Scope Estimate

| Phase | New Classes | Refactored Classes |
|-------|------------|-------------------|
| Phase 0 (Foundation) | ~20 | 0 |
| Phase 1 (Query Planning) | ~10 | 0 |
| Phase 2 (Query Execution) | ~15 | 0 |
| Phase 3 (Shared Runtime) | ~20 | 0 |
| Phase 4 (UI Layers) | ~5 | ~50 |
| Phase 5 (Extensions) | ~15 | ~10 |
| **Total** | **~85** | **~60** |
