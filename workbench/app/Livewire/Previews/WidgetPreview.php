<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use Livewire\Component;
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
        ]);
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
