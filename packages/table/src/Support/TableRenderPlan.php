<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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
 *
 * **Each group resolves on first ask, and is then memoised.** That is not a
 * micro-optimisation, it is what makes the plan usable by an island: when the
 * `rows` island renders on its own, its body asks for the plan and needs only
 * {@see columns()} — an eagerly-built plan would also resolve the layout, the
 * actions, the paging and the interaction config, none of which reach any markup,
 * and would install a fixed floor the island cannot get under. The whole premise
 * of islands is that cost is proportional to what changed.
 *
 * Measured before the change: building all six groups on every render cost
 * ~1.1 ms of fixed time, with the per-row slope and the byte count unmoved.
 *
 * This is also the shape Filament arrived at — `cachedVisibleColumns ??= …` on
 * the table itself, resolved per question rather than up front. Accessors rather
 * than readonly properties, because a readonly property cannot be filled lazily
 * and PHPStan is right to refuse it.
 */
final class TableRenderPlan
{
    private ?TableQueryState $state = null;

    private ?ColumnRenderPlan $columns = null;

    private ?ActionRenderPlan $actions = null;

    private ?LayoutRenderPlan $layout = null;

    private ?PagingRenderPlan $paging = null;

    private ?InteractionRenderPlan $interaction = null;

    private ?RowRenderPlan $row = null;

    private ?ShellRenderPlan $shell = null;

    /**
     * @param  LengthAwarePaginator<int, Model>|Paginator<int, Model>|CursorPaginator<int, Model>|Collection<int, Model>  $records
     */
    private function __construct(
        private readonly Table $table,
        private readonly mixed $component,
        private readonly LengthAwarePaginator|Paginator|CursorPaginator|Collection $records,
    ) {}

    /**
     * Hold the inputs for one render; resolve nothing yet.
     *
     * `$component` is the Livewire host using `WithTable`. It is `mixed` because
     * that is how {@see Table::livewireComponent()} already types it — there is
     * no host contract to depend on yet.
     *
     * @param  LengthAwarePaginator<int, Model>|Paginator<int, Model>|CursorPaginator<int, Model>|Collection<int, Model>  $records
     */
    public static function build(
        Table $table,
        mixed $component,
        LengthAwarePaginator|Paginator|CursorPaginator|Collection $records,
    ): self {
        return new self($table, $component, $records);
    }

    /** What the user narrowed, sorted and paged to. */
    public function state(): TableQueryState
    {
        return $this->state ??= TableQueryState::resolve($this->table, $this->component);
    }

    /** Which columns this render shows, and what was resolved off them. */
    public function columns(): ColumnRenderPlan
    {
        return $this->columns ??= ColumnRenderPlan::resolve($this->table, $this->component);
    }

    /** Which actions it offers, and how they reach the host. */
    public function actions(): ActionRenderPlan
    {
        return $this->actions ??= ActionRenderPlan::resolve($this->table);
    }

    /** How it is spaced, bordered and adapted to a narrow screen. */
    public function layout(): LayoutRenderPlan
    {
        return $this->layout ??= LayoutRenderPlan::resolve($this->table);
    }

    /** How a row answers to a pointer, a keyboard and a drag. */
    public function interaction(): InteractionRenderPlan
    {
        return $this->interaction ??= InteractionRenderPlan::resolve($this->table, $this->component);
    }

    /**
     * The row itself: its compiled markup, and everything selection needs.
     *
     * Reads {@see interaction()}, because the row's opening tag is shaped by
     * whether it is keyboard-navigable and whether an active-row marker exists.
     */
    public function row(): RowRenderPlan
    {
        return $this->row ??= RowRenderPlan::resolve(
            $this->table,
            $this->component,
            $this->records,
            $this->interaction(),
        );
    }

    /**
     * The frame: whether it renders yet, how it stays current, which regions exist.
     *
     * Resolves the columns too, because the view menu holds column toggles — the
     * same cross-group input {@see paging()} takes, and passed the same way.
     */
    public function shell(): ShellRenderPlan
    {
        return $this->shell ??= ShellRenderPlan::resolve(
            $this->table,
            $this->component,
            $this->columns()->hasToggles,
        );
    }

    /**
     * Where this page sits in the whole result set.
     *
     * The one group with siblings as inputs. Asking for it therefore resolves the
     * state and the columns too — which is correct rather than a leak: the empty
     * state it drives genuinely depends on whether anything is filtered, and the
     * ARIA row offset on whether a column-filter row is present.
     */
    public function paging(): PagingRenderPlan
    {
        return $this->paging ??= PagingRenderPlan::resolve(
            $this->table,
            $this->records,
            $this->state()->hasActiveFilters,
            $this->columns()->hasFilters,
        );
    }
}
