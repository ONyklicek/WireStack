<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

/**
 * Chart.js doughnut chart widget.
 *
 * A declarative convenience over {@see ChartWidget} preset to the `doughnut`
 * type, with the legend shown by default (like {@see PieChartWidget}).
 */
class DoughnutChartWidget extends ChartWidget
{
    protected string $type = 'doughnut';

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
