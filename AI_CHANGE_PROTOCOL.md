# AI Change Protocol

Strict execution protocol for AI-assisted work in this repository.

Use this when a task involves code changes, refactors, architecture decisions,
or behavior analysis. It is intentionally procedural to keep AI work consistent
with maintainer expectations.

## Core Rule

Do not start by writing code. Start by locating ownership, reading the local
pattern, and identifying the smallest correct change.

## Phase 1: Classify The Task

Classify the task into one primary type:

- `core-foundation`: shared concerns, contracts, icons, colors, base components
- `core-runtime`: metadata, query, state, hydration, validation, plugin runtime
- `action-modal`: actions, action groups, modals, confirmations, lifecycle
- `notification-widget-audit`: notifications, widgets, audit behavior
- `forms-field`: form fields, layouts, display components
- `forms-runtime`: form config, validation, state, save lifecycle
- `table-column`: table columns and cell rendering
- `table-filter`: filters and filter state
- `table-runtime`: query flow, Livewire state, selection, pagination, sorting,
  grouping, sub-rows, exports
- `sortable`: row/column reordering and plugin/macro behavior
- `docs-workbench`: docs, previews, screenshots, static docs site
- `cross-package`: anything spanning package boundaries

If more than one type applies, treat it as `cross-package`.

## Phase 2: Establish Ownership

Choose the owning package:

- reusable foundation semantics: `wire-core`
- standalone form behavior: `wire-forms`
- table behavior: `wire-table`
- reorder behavior layered on table: `wire-sortable`

Ownership tests:

- If forms and table both need it, look first in `core`.
- If only fields need it, look in `forms`.
- If only columns/filters/table runtime need it, look in `table`.
- If it extends table through drag/drop, macros, or plugin registration, look in
  `sortable`.

Do not put behavior downstream because that is where the bug appears. Put it
where the concept is owned.

## Phase 3: Required Reading

Always read:

1. `CLAUDE.md`
2. `AI_BLUEPRINT.md`
3. relevant package architecture doc

Read as needed:

- `AI_DOCS_STANDARD.md` whenever the change touches `docs/` — binding
- `AI_RECIPES.md` for implementation recipes
- `AI_COMPONENT_CATALOG.md` to find reusable building blocks
- `architecture/integrations.md` for cross-package behavior
- ADRs only when current intent is unclear

Then inspect:

- the nearest existing implementation
- the nearest tests
- the view/partial if rendering is affected
- the service provider if registration/boot behavior is affected

## Phase 4: Pre-Edit Brief

Before editing, be able to state:

- owning package
- files inspected
- existing pattern to follow
- files expected to change
- tests expected to run
- any public API or backward compatibility risk

For small changes this can be one concise paragraph. For larger changes, use a
short checklist.

## Phase 5: Implementation Rules

General:

- Keep changes scoped to the task.
- Prefer existing abstractions over new ones.
- Add new abstractions only when they remove real duplication or establish a
  necessary canonical owner.
- Preserve public fluent APIs unless the task explicitly asks for a breaking
  change.
- Do not refactor unrelated code.
- Do not edit generated output unless explicitly requested.

Shared semantics:

- Use foundation concerns/contracts/enums/value objects first.
- Avoid duplicate local resolvers for color, icon, size, visibility, state,
  label, authorization, extra attributes, or closure evaluation.
- If a feature is cross-cutting, implement the shared owner first and downstream
  consumption second.

Rendering:

- Resolve state/config in PHP.
- Render markup in Blade views.
- Use existing wrappers and partials.
- Keep table column rendering in column partials.
- Keep form field rendering in component views.

Runtime:

- Put form save/state behavior in `Forms/Runtime/`.
- Put table query translation in `TableQueryService`.
- Put Livewire table state serialization in `TableStateSynthesizer`.
- Put plugin registration behavior in providers/plugin manager seams.

## Phase 6: Testing Protocol

Run the narrowest test first:

- core owner: `composer test:core`
- forms owner: `composer test:forms`
- table owner: `composer test:table`
- sortable owner: `composer test:sortable`

Then run downstream tests according to impact:

- core changed: usually add forms and table tests
- forms changed: usually add table tests
- table changed: usually add sortable tests
- sortable changed: add table tests

Always run integration tests when changing:

- service provider registration
- plugin lifecycle
- Livewire synthesizer/state shape
- form/action/table orchestration
- cross-package runtime behavior

Command:

```bash
vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"
```

Quality checks:

```bash
composer lint
composer analyse
```

Use quality checks when touching shared PHP APIs, broad refactors, or before a
release-style change.

Documentation:

A public API change is not done when the code passes. The class's reference page
documents the new surface, its `## How It Works` still describes reality, an
example uses it (with a `[tl! focus]` spotlight if the block is long), and the
Czech mirror moves in the same commit. `AI_DOCS_STANDARD.md` is binding; these
gates enforce the checkable half:

```bash
npm run docs:check
npm run docs:standard
npm run docs:api
```

Never widen a baseline to make a docs gate pass — a baseline records what
predates the rule, not what you just wrote.

Coverage:

```bash
composer coverage:verify
```

CI gates every line a change adds or edits: it must be covered by a test. It
also holds each package to the floor in `scripts/coverage-floors.json`. Write the
test with the code — not as a follow-up — and if a line genuinely cannot be
reached, say why rather than leaving it bare. If a floor rises because of your
work, record it with `composer coverage:floors`.

## Phase 7: Post-Change Report

Report:

- changed files
- behavior changed
- tests run
- tests not run and why
- residual risks

Keep the report factual and concise.

## Red Flags

Stop and reassess before editing if any of these appear:

- the change requires modifying `core` to know about `forms`, `table`, or
  `sortable`
- a downstream package duplicates a resolver already present in `core`
- a table column returns custom inline HTML from PHP
- a form field bypasses form runtime/state/validation systems
- a table query bug is being fixed inside Blade
- a state serialization bug is being fixed only in a component class
- a plugin behavior is hard-coded instead of using `PluginManager`
- a public API method must be renamed or removed
- a docs gate is about to be silenced by updating a baseline instead of the page
- a generated directory appears in the diff without explicit request

## Review Protocol

When reviewing code, lead with findings:

1. Bugs or regressions
2. Architectural ownership violations
3. Cross-package compatibility risks
4. Missing tests
5. Smaller maintainability issues

Reference exact files and lines. If no issues are found, state that clearly and
mention remaining test gaps.

## Refactor Protocol

Use when a task asks to improve structure or consolidate behavior.

1. Find all implementations of the behavior.
2. Identify the canonical owner.
3. Add/extend the canonical abstraction.
4. Migrate one consumer at a time.
5. Preserve the public API unless breaking changes are approved.
6. Delete duplicated code only after tests prove the new owner covers it.
7. Run owner and downstream tests.

## Commit Discipline

Do not revert unrelated user changes.

Do not use destructive git commands unless explicitly requested.

Before committing, if requested:

1. Check `git status --short`.
2. Include only files relevant to the task.
3. Use a concise commit message describing behavior, not implementation trivia.

