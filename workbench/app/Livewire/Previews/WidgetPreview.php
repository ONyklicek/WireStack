<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Widgets\BarChartWidget;
use NyonCode\WireCore\Widgets\ChartItem;
use NyonCode\WireCore\Widgets\ChartWidget;
use NyonCode\WireCore\Widgets\Concerns\WithWidgets;
use NyonCode\WireCore\Widgets\ListItem;
use NyonCode\WireCore\Widgets\ListWidget;
use NyonCode\WireCore\Widgets\ProgressItem;
use NyonCode\WireCore\Widgets\ProgressWidget;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;
use NyonCode\WireCore\Widgets\Widget;

class WidgetPreview extends Component
{
    use WithWidgets;

    public string $variant = 'overview';

    /**
     * How many times the deferred widget's header action has run.
     *
     * On the component rather than in the widget, because a widget is rebuilt
     * from the declaration on every request and cannot count anything. A driver
     * reads it off the rendered row.
     */
    public int $recounts = 0;

    public function mount(string $variant = 'overview'): void
    {
        $this->variant = $variant;
    }

    /**
     * A dashboard whose one polling widget must not re-render the rest of it.
     *
     * Every widget shows the moment it was rendered, so a tick that re-rendered
     * the whole grid moves all four stamps and a targeted one moves exactly the
     * polling widget's. Nothing is stored to make that work: a stamp taken at
     * render time differs on every request by construction, where a counter on
     * the component would ride the snapshot and be restored on a partial
     * response — hiding the very difference this fixture exists to show.
     *
     * @return array<int, Widget>
     */
    /**
     * The layout key for the editable variant, and nothing for the rest.
     *
     * Opting in per variant rather than for the whole preview: the other
     * variants are how a dashboard behaves *without* customisation, and they
     * would stop proving that if they quietly gained a store.
     */
    protected function widgetLayoutKey(): ?string
    {
        return $this->variant === 'editable' ? 'preview-editable' : null;
    }

    protected function getWidgets(): array
    {
        if ($this->variant === 'editable') {
            return [
                StatsOverviewWidget::make()->key('revenue')->heading('Revenue')
                    ->stats([Stat::make('This month', '84.2K')]),
                StatsOverviewWidget::make()->key('orders')->heading('Orders')
                    ->stats([Stat::make('Open', '128')]),
                StatsOverviewWidget::make()->key('churn')->heading('Churn')
                    ->stats([Stat::make('Rate', '1.8%')]),
                StatsOverviewWidget::make()->key('capacity')->heading('Capacity')
                    ->stats([Stat::make('Seats used', '96%')]),
            ];
        }

        if ($this->variant === 'interactive') {
            return $this->interactiveWidgets();
        }

        $stamp = (string) (int) (microtime(true) * 1000);

        return array_map(function (int $i) use ($stamp) {
            $widget = StatsOverviewWidget::make()
                ->heading('Widget '.$i)
                ->stats([Stat::make('Rendered', $stamp.'-w'.$i)]);

            return $i === 2 ? $widget->pollingInterval('2s') : $widget;
        }, range(1, 4));
    }

    /**
     * The three things a widget can do that only a browser can prove.
     *
     * A filter that re-resolves its closure on the server, a chart whose Alpine
     * instance has to be rebuilt when the morph brings new data, and a deferred
     * widget that must replace its own skeleton. Each carries a stamp taken at
     * render time, so a driver can tell "this widget re-rendered" from "the page
     * did" without anything being stored to make it work.
     *
     * @return array<int, Widget>
     */
    private function interactiveWidgets(): array
    {
        $stamp = (string) (int) (microtime(true) * 1000);

        return [
            ListWidget::make()
                ->key('orders')
                ->heading('Recent orders')
                ->filter(['week' => 'This week', 'month' => 'This month'], 'week')
                ->items(fn (?string $filter) => [
                    ListItem::make('range: '.$filter)
                        ->description('resolved on the server')
                        ->meta($stamp)
                        ->icon('outline:shopping-cart')
                        ->color($filter === 'month' ? 'warning' : 'success'),
                    ListItem::make('Order #1042')
                        ->description('Acme s.r.o.')
                        ->meta('2 minutes ago')
                        ->url('#order-1042'),
                ]),

            ProgressWidget::make()
                ->key('quota')
                ->heading('Quota attainment')
                ->headerActions([
                    Action::make('recount')
                        ->label('Recount')
                        ->icon('outline:arrow-path')
                        ->action(fn () => $this->recounts++),
                ])
                ->lazy()
                ->items([
                    ProgressItem::make('New MRR')->value(84)->target(120)->color('success'),
                    ProgressItem::make('Churn budget')->value(31)->target(40)->color('warning'),
                    ProgressItem::make('Loaded at')->value(100)->target(100)->formattedValue($stamp),
                    ProgressItem::make('Recounts')->value(100)->target(100)
                        ->formattedValue('recounts: '.$this->recounts),
                ]),

            ChartWidget::make()
                ->key('channels')
                ->heading('Orders by channel')
                ->type('bar')
                ->filter(['q1' => 'Q1 2026', 'q2' => 'Q2 2026'], 'q1')
                ->labels(fn (?string $filter) => $filter === 'q2'
                    ? ['Direct', 'Marketplace']
                    : ['Direct', 'Marketplace', 'Partner', 'Referral'])
                ->datasets(fn (?string $filter) => [[
                    'label' => 'Orders '.$filter,
                    'data' => $filter === 'q2' ? [2100, 1500] : [1820, 1340, 980, 640],
                    'backgroundColor' => ['#3b82f6', '#6366f1', '#0ea5e9', '#14b8a6'],
                    'borderRadius' => 8,
                ]]),
        ];
    }

    /**
     * The two pure-CSS widget types, with content that looks like an
     * application's rather than a fixture's.
     *
     * Separate from the `interactive` variant on purpose: that one instruments
     * everything with timestamps and counters so a driver can tell one render
     * from another, which is exactly what you do not want in a picture.
     *
     * @return array<int, ProgressItem>
     */
    private function quotaItems(): array
    {
        return [
            ProgressItem::make('New MRR')
                ->value(84_200)->target(120_000)
                ->formattedValue('84.2K / 120K')
                ->description('12 deals closed, 4 in review')
                ->icon('arrow-trending-up')
                ->color('success'),
            ProgressItem::make('Expansion')
                ->value(31_500)->target(45_000)
                ->formattedValue('31.5K / 45K')
                ->icon('banknotes')
                ->color('primary'),
            ProgressItem::make('Churn budget')
                ->value(37)->target(40)
                ->formattedValue('37 / 40 accounts')
                ->description('3 left before the quarter target slips')
                ->icon('exclamation-triangle')
                ->color('warning'),
            ProgressItem::make('Onboarding backlog')
                ->value(9)->target(60)
                ->formattedValue('9 / 60')
                ->icon('clock')
                ->color('danger'),
        ];
    }

    private function quotaWidget(): ProgressWidget
    {
        return ProgressWidget::make()
            ->key('quarterly-targets')
            ->heading('Quarterly targets')
            ->description('Closed-won against plan, this quarter')
            ->headerActions([
                Action::make('recalculate')->label('Recalculate')->icon('arrow-path'),
            ])
            ->items($this->quotaItems());
    }

    private function capacityWidget(): ProgressWidget
    {
        return ProgressWidget::make()
            ->key('capacity')
            ->heading('Capacity')
            ->description('Bars only — the comparison is the fill, not the number')
            ->showValues(false)
            ->items([
                ProgressItem::make('Support queue')->value(72)->color('warning'),
                ProgressItem::make('Storage')->value(41)->color('primary'),
                ProgressItem::make('Seats used')->value(96)->color('danger'),
            ]);
    }

    private function ordersWidget(): ListWidget
    {
        return ListWidget::make()
            ->key('recent-orders')
            ->heading('Recent orders')
            ->description('The last five, newest first')
            ->filter(['today' => 'Today', 'week' => 'This week'], 'today')
            ->headerActions([
                Action::make('open-all')->label('Open all')->url('#orders'),
            ])
            ->items([
                ListItem::make('#1042 · Acme s.r.o.')
                    ->description('12 400 Kč — paid')
                    ->meta('2 min ago')->icon('check-circle')->color('success')->url('#order-1042'),
                ListItem::make('#1041 · Bravo a.s.')
                    ->description('4 900 Kč — card declined')
                    ->meta('18 min ago')->icon('x-circle')->color('danger')->url('#order-1041'),
                ListItem::make('#1040 · Cirrus s.r.o.')
                    ->description('31 250 Kč — awaiting dispatch')
                    ->meta('1 hour ago')->icon('truck')->color('warning')->url('#order-1040'),
                ListItem::make('#1039 · Delta GmbH')
                    ->description('8 100 Kč — paid')
                    ->meta('3 hours ago')->icon('check-circle')->color('success')->url('#order-1039'),
                ListItem::make('#1038 · Echo s.r.o.')
                    ->description('2 350 Kč — refunded')
                    ->meta('yesterday')->icon('credit-card')->color('gray')->url('#order-1038'),
            ]);
    }

    private function emptyFeedWidget(): ListWidget
    {
        return ListWidget::make()
            ->key('escalations')
            ->heading('Escalations')
            ->items([])
            ->emptyState('Nothing escalated today.');
    }

    public function render()
    {
        if ($this->variant === 'editable') {
            return view('livewire.previews.widget-editable', $this->widgetGridData(2));
        }

        if ($this->variant === 'progress-list') {
            return view('livewire.previews.widget-progress-list', [
                'quota' => $this->quotaWidget(),
                'capacity' => $this->capacityWidget(),
                'orders' => $this->ordersWidget(),
                'escalations' => $this->emptyFeedWidget(),
            ]);
        }

        if ($this->variant === 'interactive') {
            // Its own shell rather than the grid view directly: the chart widget
            // needs Chart.js on the page, and the grid is a partial that brings
            // no document with it.
            return view('livewire.previews.widget-interactive', [
                'widgets' => $this->getVisibleWidgets(),
                'columns' => 2,
            ]);
        }

        if ($this->variant === 'polling') {
            return view('wire-core::widgets.widget-grid', [
                'widgets' => $this->getVisibleWidgets(),
                'columns' => 2,
            ]);
        }

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
