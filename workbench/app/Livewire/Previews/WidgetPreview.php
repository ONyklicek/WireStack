<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use Livewire\Component;
use NyonCode\WireCore\Widgets\BarChartWidget;
use NyonCode\WireCore\Widgets\ChartItem;
use NyonCode\WireCore\Widgets\ChartWidget;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;

class WidgetPreview extends Component
{
    public string $variant = 'overview';

    public function mount(string $variant = 'overview'): void
    {
        $this->variant = $variant;
    }

    public function render()
    {
        return view('livewire.previews.widget-preview', [
            'variant' => $this->variant,
            'stats' => $this->statsWidget(),
            'lineChart' => $this->lineChartWidget(),
            'barChart' => $this->barChartWidget(),
            'financeBars' => $this->financeBarsWidget(),
            'systemBars' => $this->systemBarsWidget(),
            'systemBarsHorizontal' => $this->systemBarsHorizontalWidget(),
        ]);
    }

    private function financeBarsWidget(): BarChartWidget
    {
        return BarChartWidget::make()
            ->heading('Přehled tržeb')
            ->description('Měsíční tržby za poslední půlrok')
            ->type('vertical')
            ->variant('finance')
            ->height(220)
            ->items([
                ChartItem::make('01 / 2024')->value(125000)->formattedValue('125 000 Kč')->color('blue')->percentage(78),
                ChartItem::make('02 / 2024')->value(98500)->formattedValue('98 500 Kč')->color('green')->percentage(61),
                ChartItem::make('03 / 2024')->value(142300)->formattedValue('142 300 Kč')->color('purple')->percentage(89),
                ChartItem::make('04 / 2024')->value(76400)->formattedValue('76 400 Kč')->color('orange')->percentage(48),
                ChartItem::make('05 / 2024')->value(118900)->formattedValue('118 900 Kč')->color('blue')->percentage(74),
                ChartItem::make('06 / 2024')->value(160500)->formattedValue('160 500 Kč')->color('green')->percentage(100),
            ]);
    }

    /**
     * @return array<int, ChartItem>
     */
    private function systemMetrics(): array
    {
        return [
            ChartItem::make('CPU')->value(72)->formattedValue('72 %')->icon('cpu-chip')->color('blue')->percentage(72),
            ChartItem::make('RAM')->value(54)->formattedValue('54 %')->icon('circle-stack')->color('green')->percentage(54),
            ChartItem::make('Disk')->value(81)->formattedValue('81 %')->icon('server')->color('orange')->percentage(81),
            ChartItem::make('GPU')->value(36)->formattedValue('36 %')->icon('bolt')->color('purple')->percentage(36),
        ];
    }

    private function systemBarsWidget(): BarChartWidget
    {
        return BarChartWidget::make()
            ->heading('Přehled systému')
            ->type('vertical')
            ->variant('system')
            ->showGrid()
            ->showMenu()
            ->maxValue(100)
            ->height(220)
            ->items($this->systemMetrics());
    }

    private function systemBarsHorizontalWidget(): BarChartWidget
    {
        return BarChartWidget::make()
            ->heading('Vytížení zdrojů')
            ->type('horizontal')
            ->variant('system')
            ->maxValue(100)
            ->items($this->systemMetrics());
    }

    private function statsWidget(): StatsOverviewWidget
    {
        return StatsOverviewWidget::make()
            ->columns(4)
            ->stats([
                Stat::make('Monthly revenue', '$48,320')
                    ->description('12.4% vs last month')
                    ->color('success')
                    ->icon('banknotes')
                    ->chart([18, 22, 19, 27, 24, 31, 29, 38]),
                Stat::make('Active users', '8,294')
                    ->description('6.1% growth')
                    ->color('primary')
                    ->icon('users')
                    ->chart([30, 34, 33, 40, 44, 42, 49, 55]),
                Stat::make('Open tickets', '47')
                    ->description('9 awaiting reply')
                    ->descriptionIcon('clock')
                    ->color('warning')
                    ->icon('inbox-arrow-down')
                    ->chart([12, 9, 14, 11, 16, 13, 10, 8]),
                Stat::make('Churn rate', '1.8%')
                    ->description('0.3% lower than last month')
                    ->color('danger')
                    ->icon('heart')
                    ->chart([5, 4, 6, 5, 4, 3, 4, 3]),
            ]);
    }

    private function lineChartWidget(): ChartWidget
    {
        return ChartWidget::make()
            ->heading('Revenue over time')
            ->description('Booked vs. target, last six months')
            ->type('line')
            ->labels(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'])
            ->datasets([
                [
                    'label' => 'Revenue',
                    'data' => [21000, 28500, 26200, 35400, 41800, 48320],
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.12)',
                    'fill' => true,
                    'tension' => 0.4,
                    'borderWidth' => 3,
                    'pointRadius' => 4,
                    'pointBackgroundColor' => '#3b82f6',
                ],
                [
                    'label' => 'Target',
                    'data' => [25000, 27000, 29000, 33000, 38000, 44000],
                    'borderColor' => '#cbd5e1',
                    'borderDash' => [6, 6],
                    'borderWidth' => 2,
                    'pointRadius' => 0,
                    'tension' => 0.4,
                    'fill' => false,
                ],
            ]);
    }

    private function barChartWidget(): ChartWidget
    {
        return ChartWidget::make()
            ->heading('Orders by channel')
            ->description('Filtered by quarter')
            ->type('bar')
            ->filter([
                'q1' => 'Q1 2026',
                'q2' => 'Q2 2026',
            ], 'q2')
            ->labels(['Direct', 'Marketplace', 'Partner', 'Referral', 'Social'])
            ->datasets([
                [
                    'label' => 'Orders',
                    'data' => [1820, 1340, 980, 640, 520],
                    'backgroundColor' => [
                        '#3b82f6', '#6366f1', '#0ea5e9', '#14b8a6', '#f59e0b',
                    ],
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'maxBarThickness' => 56,
                ],
            ]);
    }
}
