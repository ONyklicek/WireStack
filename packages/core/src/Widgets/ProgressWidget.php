<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

use NyonCode\WireCore\Widgets\Concerns\HasWidgetItems;

/**
 * Rows of progress toward a target — quota, budget, capacity, a sprint.
 *
 * The gap between {@see StatsOverviewWidget} and {@see BarChartWidget}: a stat
 * card says a figure and a bar chart compares figures against each other, and
 * neither says how far a figure is along the way to a figure it is supposed to
 * reach. That is the one question a fill against a track answers at a glance and
 * a number never does.
 *
 * Pure CSS, like the bar chart and unlike {@see ChartWidget} — no Chart.js, no
 * canvas, nothing to wait for. Each row's geometry is
 * {@see ProgressItem::getPercentage()}'s, resolved in PHP.
 *
 * ```php
 * ProgressWidget::make()
 *     ->heading('Quarterly targets')
 *     ->items([
 *         ProgressItem::make('New MRR')->value(84_000)->target(120_000)->color('success'),
 *         ProgressItem::make('Churn budget')->value(31)->target(40)->color('warning'),
 *     ]);
 * ```
 *
 * The series may be a closure instead, which is what makes a filter on this
 * widget mean anything — see {@see HasWidgetItems}.
 */
class ProgressWidget extends Widget
{
    use HasWidgetItems;

    protected bool $showTrackLabels = true;

    /**
     * Print each row's value beside its label. Off leaves the bars alone, which
     * reads better on a dense board where the fills are the comparison.
     */
    public function showValues(bool $condition = true): static
    {
        $this->showTrackLabels = $condition;

        return $this;
    }

    public function showsValues(): bool
    {
        return $this->showTrackLabels;
    }

    /**
     * @return class-string<ProgressItem>
     */
    protected function itemClass(): string
    {
        return ProgressItem::class;
    }

    protected function viewName(): string
    {
        return 'wire-core::widgets.progress';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'items' => $this->getItems(),
            'showValues' => $this->showTrackLabels,
            'filterOptions' => $this->getFilterOptions(),
            'activeFilter' => $this->getActiveFilter(),
            'filterExpression' => $this->getFilterExpression(),
        ];
    }
}
