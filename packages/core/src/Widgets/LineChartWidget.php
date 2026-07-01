<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

/**
 * Chart.js line chart widget.
 *
 * A declarative convenience over {@see ChartWidget} preset to the `line` type,
 * so dashboards can express intent (`LineChartWidget::make()`) instead of
 * `ChartWidget::make()->type('line')`.
 */
class LineChartWidget extends ChartWidget
{
    protected string $type = 'line';
}
