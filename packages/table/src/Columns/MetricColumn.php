<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\View\Sparkline;
use NyonCode\WireTable\Concerns\RendersAsFigure;

/**
 * A figure read as a measurement: the number, and optionally where it came from.
 *
 * It aggregates nothing. The relation AST already turns a dotted path into a
 * `withCount` / `withSum` subquery, so `MetricColumn::make('orders.count')` is
 * the same one query it always was — see `docs/table/columns/relations.md`. What
 * this adds is how a measurement is *presented*:
 *
 * - the figure defaults of {@see RendersAsFigure}, shared with {@see MoneyColumn};
 * - an optional **trend** beside the number — a series per record, drawn as one
 *   `<polyline>` by {@see Sparkline}, the same geometry a stats widget uses.
 *
 * ```php
 * MetricColumn::make('orders.count')
 *     ->label('Orders')
 *     ->trend(fn (Customer $record): array => $record->orders_per_month)
 * ```
 *
 * ## The trend is yours to supply, and that is deliberate
 *
 * A per-record series cannot be derived from the column's own path: "orders per
 * month for this customer" is a second query shape, not a formatting of the
 * first. Handing it a closure keeps the decision — and the N+1 — where the author
 * can see it: load the series with the page (`withCount` over a grouped
 * relation, a cached column, an eager-loaded relation) and read it here. A
 * closure that queries per record will run once per row, exactly as it reads.
 *
 * An empty or absent series draws nothing at all, so a record with no history
 * costs no markup rather than an empty box.
 */
class MetricColumn extends TextColumn
{
    use RendersAsFigure;

    /** @var Closure|null fn (Model): array<int, int|float> */
    protected ?Closure $trendCallback = null;

    protected ?string $trendColor = null;

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->readAsFigure();
    }

    /**
     * The series drawn beside the figure, per record.
     *
     * @param  Closure  $callback  fn (Model $record): array<int, int|float>
     */
    public function trend(Closure $callback, ?string $color = null): static
    {
        $this->trendCallback = $callback;
        $this->trendColor = $color;

        return $this;
    }

    public function hasTrend(): bool
    {
        return $this->trendCallback !== null;
    }

    /**
     * The record's series, or an empty array when there is nothing to draw.
     *
     * Non-numeric entries are dropped rather than coerced: a null in a monthly
     * series means "no reading", and plotting it as zero invents a fall to the
     * floor that never happened.
     *
     * @return array<int, int|float>
     */
    public function getTrend(Model $record): array
    {
        if ($this->trendCallback === null) {
            return [];
        }

        $series = ($this->trendCallback)($record);

        if (! is_array($series)) {
            return [];
        }

        return array_values(array_filter($series, is_numeric(...)));
    }

    /**
     * The trend's stroke colour, through the canonical palette resolver so a
     * metric's line matches the vocabulary every other surface uses.
     */
    public function getTrendColorClass(): string
    {
        return HasColor::getFillTextClasses($this->trendColor ?? $this->getColor() ?? 'primary');
    }

    public function renderCell(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $state = $this->getState($record);
        $sparkline = $this->hasTrend() ? Sparkline::of($this->getTrend($record)) : null;

        // Without a trend a metric is a formatted figure and nothing more, so it
        // takes the base cell rather than paying for a wrapper it does not use.
        if ($sparkline === null) {
            return parent::renderCell($record);
        }

        return $this->renderView('tables.columns.metric', [
            'value' => $this->formatValue($state, $record),
            'isHtml' => $this->html,
            'textClasses' => $this->getTextClasses(),
            'viewBox' => $sparkline->viewBox(),
            'points' => $sparkline->points(),
            'strokeClass' => $this->getTrendColorClass(),
        ]);
    }
}
