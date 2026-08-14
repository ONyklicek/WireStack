<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireTable\Table;

/**
 * Everything one render of a table needs to know, resolved once, in PHP.
 *
 * `tables/index.blade.php` opened with a 322-line `@php` block computing ~100
 * locals — the visible columns, the compiled cell skeletons, the action
 * resolvers, the gesture config, the whole state read. Three things are wrong
 * with that, in rising order of cost:
 *
 *  1. **None of it is testable.** A view's locals can only be asserted through
 *     the markup they end up producing, so rules like "a range filter typed then
 *     cleared is not an active filter" were verified by rendering a table and
 *     looking for a chip.
 *  2. **It is the one place the deprecated magic properties were easy to reach
 *     for.** `$component->tableFilters` and friends rebuild the deprecation map
 *     on every access; the block carried a comment telling the next reader not
 *     to use them in a loop, which is a warning, not a guardrail.
 *  3. **An island cannot see any of it.** Livewire extracts an `@island` body
 *     into its own view file at compile time, and
 *     `HandlesIslands::renderIslandView()` gives that file the component's public
 *     properties and the directive's own `with:` — NOT the enclosing view's
 *     locals. So every one of those ~100 locals is invisible inside an island,
 *     which is why the islands work in
 *     `architecture/plans/livewire-4-migration-and-performance.md` §4.2 is gated
 *     behind this class existing.
 *
 * The plan is that owner. It is built once per render from the three things the
 * view is handed — the {@see Table} config, the Livewire host and the page of
 * records — memoised by `WithTable::tableRenderPlan()`, and read by the main
 * view and, later, by each island body.
 *
 * **It resolves; it does not render.** Nothing here emits markup, and nothing
 * here decides layout — that stays in Blade, per `AI_CODING_STANDARD.md`. What
 * it owns is the answer to "what is true about this render", so both the view
 * and an island body can ask the same question and get the same answer.
 *
 * Built by slice, so the head block shrinks a group at a time and each move is
 * gated on its own. The view aliases what has moved (`$activeTableFilters =
 * $plan->state->activeFilters`), which keeps the 1 200 lines below the head
 * block untouched while the computation migrates.
 *
 * The plan itself stays a composition root: each slice is its own value object,
 * so no single class ends up with a hundred properties and one docblock trying
 * to explain all of them.
 */
final class TableRenderPlan
{
    private function __construct(
        /** What the user narrowed, sorted and paged to. */
        public readonly TableQueryState $state,
        /** Which columns this render shows, and what was resolved off them. */
        public readonly ColumnRenderPlan $columns,
        /** Which actions it offers, and how they reach the host. */
        public readonly ActionRenderPlan $actions,
        /** How it is spaced, bordered and adapted to a narrow screen. */
        public readonly LayoutRenderPlan $layout,
    ) {}

    /**
     * Resolve the plan for one render.
     *
     * `$component` is the Livewire host using `WithTable`. It is `mixed` because
     * that is how {@see Table::livewireComponent()} already types it — there is
     * no host contract to depend on yet.
     */
    public static function build(Table $table, mixed $component): self
    {
        return new self(
            state: TableQueryState::resolve($table, $component),
            columns: ColumnRenderPlan::resolve($table, $component),
            actions: ActionRenderPlan::resolve($table),
            layout: LayoutRenderPlan::resolve($table),
        );
    }
}
