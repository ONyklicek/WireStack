<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Pages\Concerns;

use NyonCode\WireCore\Widgets\Widget;

/**
 * Widgets above and below a page's own content — a row of stats over a list, a
 * chart under a record.
 *
 *   protected function headerWidgets(): array
 *   {
 *       return [StatsOverviewWidget::make()->stats([...])];
 *   }
 *
 * They are **drawn, not hosted**: the page renders them through the same grid a
 * dashboard uses, and does not become a widget host. So what needs one — a
 * widget that polls, one loaded lazily, a widget's own actions — belongs on a
 * dashboard page, where `WithWidgets` answers for it. A stat, a chart, a list
 * or a progress bar computed when the page renders is exactly what this is for.
 */
trait InteractsWithPageWidgets
{
    /**
     * Widgets drawn under the heading, above the page's content; a `null` entry
     * is skipped, so a conditional widget is written inline.
     *
     * @return array<int, Widget|null>
     */
    protected function headerWidgets(): array
    {
        return [];
    }

    /**
     * Widgets drawn under the page's content.
     *
     * @return array<int, Widget|null>
     */
    protected function footerWidgets(): array
    {
        return [];
    }

    /** How many columns the page's widget rows have at their widest. */
    protected function pageWidgetColumns(): int
    {
        return 3;
    }

    /**
     * The widgets for one position, keyed so the grid can tell them apart.
     *
     * Keyed by position and index — `header-0`, `footer-1` — because a page
     * has no layout to remember, and two positions must never share a key.
     *
     * @return array{widgets: array<int, Widget>, columns: int}
     */
    protected function pageWidgetsForView(string $position): array
    {
        $declared = $position === 'header' ? $this->headerWidgets() : $this->footerWidgets();

        $widgets = [];

        foreach (array_values($declared) as $index => $widget) {
            if ($widget instanceof Widget) {
                $widgets[] = $widget->getKey() === null ? $widget->key($position.'-'.$index) : $widget;
            }
        }

        return ['widgets' => $widgets, 'columns' => $this->pageWidgetColumns()];
    }
}
