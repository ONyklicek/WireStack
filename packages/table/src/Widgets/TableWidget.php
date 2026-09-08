<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Widgets;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Widgets\Widget;
use NyonCode\WireTable\Services\TableQueryService;
use NyonCode\WireTable\Table;

/**
 * A few rows of a table, inside a dashboard card.
 *
 * ## Why this is in wire-table and not beside the other widgets
 *
 * Because it could never have worked there. `WireCore\Widgets\TableWidget`
 * shipped from the beginning as a class that took a `Closure` configuring a
 * `Table`, stored it, and rendered a card with a heading and an empty `<div>` —
 * its view said `{{-- Table will be rendered by the Livewire component --}}`
 * and no Livewire component ever did. `getTableCallback()`'s only caller in the
 * whole repository was a test asserting the getter, while the docs page showed a
 * full working example with columns and a query.
 *
 * The cause was structural. `Widgets/` is in `wire-core`; the table engine —
 * `Table`, `Column`, `TableQueryService` — is in `wire-table`, which *depends*
 * on core. From core, none of it can be reached, so the widget could hold the
 * callback and never call it. Moving the class to the package that owns the
 * engine is the whole fix.
 *
 * Not a contract in core with a renderer registered from table: that shape
 * (`Foundation\Contracts\RunsComponentActions`) is for two modules inside one
 * package, where neither may import the other. Between two packages the
 * direction is unambiguous, and a seam would be ceremony around a dependency
 * that is allowed to exist.
 *
 * ## What it draws, and what it deliberately does not
 *
 * Columns and rows. No toolbar, no search, no filters, no pagination, no bulk
 * actions, no row actions — those are a table's *apparatus*, and a dashboard
 * card that grows one is a table wearing a widget costume. Ask for those and you
 * want a page with `WithTable` on it, or a nested Livewire component; the point
 * of this one is that it stays plain markup inside the host, so the widget's
 * `wire:partial` region, its polling and its header actions all keep working.
 *
 * ```php
 * TableWidget::make()
 *     ->heading('Recent orders')
 *     ->limit(5)
 *     ->table(fn (Table $table) => $table
 *         ->model(Order::class)
 *         ->columns([
 *             TextColumn::make('reference'),
 *             TextColumn::make('customer.name'),
 *             BadgeColumn::make('status'),
 *         ]));
 * ```
 *
 * The query runs through {@see TableQueryService} rather than the builder
 * directly, so a column that needs a join, an aggregate or a relation path is
 * planned the same way it would be on a full table — one owner for what a column
 * costs, instead of a second, simpler resolver that would drift.
 */
class TableWidget extends Widget
{
    protected ?Closure $tableCallback = null;

    protected int $limit = 5;

    /** @var array<int, Model>|null */
    private ?array $resolvedRecords = null;

    private ?Table $resolvedTable = null;

    /**
     * Configure the table this widget draws.
     *
     * @param  Closure(Table): Table  $callback
     */
    public function table(Closure $callback): static
    {
        $this->tableCallback = $callback;
        $this->resolvedTable = null;
        $this->resolvedRecords = null;

        return $this;
    }

    public function getTableCallback(): ?Closure
    {
        return $this->tableCallback;
    }

    /**
     * How many rows to draw — default 5.
     *
     * A hard ceiling rather than a page: there is no pagination here, so a
     * dashboard card without one would render whatever the query returned.
     */
    public function limit(int $limit): static
    {
        $this->limit = max(1, $limit);
        $this->resolvedRecords = null;

        return $this;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * The configured table, or null when the widget was never given a callback.
     *
     * Memoized for the render, which is not only a saving: the view asks for the
     * columns and the rows separately, and configuring twice would run a
     * caller's closure twice.
     */
    public function getTable(): ?Table
    {
        if ($this->tableCallback === null) {
            return null;
        }

        return $this->resolvedTable ??= ($this->tableCallback)(Table::make());
    }

    /**
     * The rows, planned by the canonical query service and capped at the limit.
     *
     * @return array<int, Model>
     */
    public function getRecords(): array
    {
        if ($this->resolvedRecords !== null) {
            return $this->resolvedRecords;
        }

        $table = $this->getTable();

        if ($table === null) {
            return $this->resolvedRecords = [];
        }

        $query = app(TableQueryService::class)->buildQuery($table->getQuery(), $table);

        // A statement rather than a link in the chain, as `CanExpandSubRows` and
        // `HasSubRows` also write it: `limit()` is not declared on the Eloquent
        // builder, so chaining it forwards through `__call` and the expression
        // types as the *query* builder — whose `get()` yields `stdClass` rows.
        $query->limit($this->limit);

        return $this->resolvedRecords = $query->get()->all();
    }

    protected function viewName(): string
    {
        return 'wire-table::widgets.table';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $table = $this->getTable();

        return [
            'columns' => $table?->getColumns() ?? [],
            'records' => $this->getRecords(),
        ];
    }
}
