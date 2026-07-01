<?php

declare(strict_types=1);

use NyonCode\WireCore\Widgets\ChartWidget;
use NyonCode\WireCore\Widgets\DoughnutChartWidget;
use NyonCode\WireCore\Widgets\LineChartWidget;
use NyonCode\WireCore\Widgets\PieChartWidget;

it('LineChartWidget presets the line type and extends ChartWidget', function () {
    $widget = LineChartWidget::make();

    expect($widget)->toBeInstanceOf(ChartWidget::class)
        ->and($widget->getType())->toBe('line');
});

it('PieChartWidget presets the pie type', function () {
    expect(PieChartWidget::make()->getType())->toBe('pie');
});

it('DoughnutChartWidget presets the doughnut type', function () {
    expect(DoughnutChartWidget::make()->getType())->toBe('doughnut');
});

it('pie and doughnut widgets show the legend by default', function () {
    $expected = ['display' => true, 'position' => 'top'];

    expect(PieChartWidget::make()->getOptions()['plugins']['legend'])->toBe($expected)
        ->and(DoughnutChartWidget::make()->getOptions()['plugins']['legend'])->toBe($expected);
});

it('convenience widgets keep the base responsive defaults', function () {
    $options = PieChartWidget::make()->getOptions();

    expect($options['responsive'])->toBeTrue()
        ->and($options['maintainAspectRatio'])->toBeFalse();
});

it('custom options merge over the pie widget legend default', function () {
    $legend = PieChartWidget::make()
        ->options(['plugins' => ['legend' => ['position' => 'bottom']]])
        ->getOptions()['plugins']['legend'];

    // position overridden, display default preserved by the recursive merge.
    expect($legend)->toBe(['display' => true, 'position' => 'bottom']);
});

it('convenience widgets render with their preset type', function () {
    $html = PieChartWidget::make()
        ->labels(['A', 'B'])
        ->datasets([['data' => [1, 2]]])
        ->toHtml();

    expect($html)->toContain('wireChart(')
        ->toContain('pie');
});
