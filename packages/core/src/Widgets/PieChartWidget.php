<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

/**
 * Chart.js pie chart widget.
 *
 * A declarative convenience over {@see ChartWidget} preset to the `pie` type,
 * with the legend shown by default (pie/doughnut charts rely on it to identify
 * slices).
 */
class PieChartWidget extends ChartWidget
{
    protected string $type = 'pie';

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultOptions(): array
    {
        return array_replace_recursive(parent::getDefaultOptions(), [
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'top'],
            ],
        ]);
    }
}
