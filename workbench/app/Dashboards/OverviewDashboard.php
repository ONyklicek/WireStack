<?php

declare(strict_types=1);

namespace Workbench\App\Dashboards;

use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Routing\Contracts\ConfiguresRoutes;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Widgets\BarChartWidget;
use NyonCode\WireCore\Widgets\ChartItem;
use NyonCode\WireCore\Widgets\Dashboard;
use NyonCode\WireCore\Widgets\ListItem;
use NyonCode\WireCore\Widgets\ListWidget;
use NyonCode\WireCore\Widgets\ProgressItem;
use NyonCode\WireCore\Widgets\ProgressWidget;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;
use NyonCode\WireCore\Widgets\Widget;
use Workbench\App\Livewire\Dashboards\ShowOverview;
use Workbench\App\Models\Document;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\Task;

/**
 * The workbench's dashboard, on the same real data its resources list.
 *
 * V2.6 step 3's own gate: a dashboard has to reach a menu and a page without
 * being a resource and without `Workspace` learning what a dashboard is. Every
 * widget here counts real rows — a dashboard of fixed numbers would render
 * identically whether or not the declaration was ever reached.
 *
 * It is also each zone's landing page (ADR 0027): declaring pages makes it
 * routable by the same macro a resource is, and `routePrefix()` of
 * `ConfiguresRoutes::ROOT` adds no segment — so its index lands on the group's
 * own path, and `/previews/zoned/business` is a page rather than a 404. Which
 * zone lands where is `only`/`except`, like every other membership question;
 * an application wanting a different landing per zone gives each one its own
 * dashboard rather than a second declaration.
 *
 * **And it is the repository's one customisable dashboard on real data.**
 * `/previews/widgets-editable` shows the edit mode over four fixture cards in a
 * hand-written page; this is the whole feature where an application meets it —
 * a registered dashboard, rendered by `DashboardPage`, whose Customise / Save /
 * Cancel / Reset chrome arrives from the page rather than from anything the
 * workbench wrote, and whose layout is stored per user under the key the
 * dashboard registered with. The two exist for different reasons and neither
 * replaces the other: the preview is where a driver can instrument the grid,
 * and this is where the default path has to hold.
 *
 * Six widgets in two groups is the smallest declaration that shows what the
 * tray is for. With everything placed the tray is empty and says so; take a
 * widget off and it reappears there under its own group heading, which is the
 * one rule (`not placed` == `available`) doing both jobs.
 */
final class OverviewDashboard extends Dashboard implements ConfiguresRoutes, ProvidesNavigation, ProvidesPages
{
    public static function pages(): array
    {
        return ['index' => ShowOverview::class];
    }

    public static function routePrefix(): ?string
    {
        return self::ROOT;
    }

    public static function routeMiddleware(): array
    {
        return [];
    }

    public static function routeDomain(): ?string
    {
        return null;
    }

    /**
     * Let a user rearrange this one.
     *
     * Which is the entire opt-in on this side: `DashboardPage` turns it into the
     * key the layout is stored under ({@see Dashboard::key()}, so `overview`),
     * and the page's controls appear because that key exists. Nothing else here
     * knows about editing.
     *
     * What it obliges is the keys below. A derived key is a *position*, so on a
     * dashboard somebody rearranges, inserting a widget at the top would make
     * every saved layout describe different widgets than it did yesterday —
     * `WithWidgets` refuses rather than letting that render.
     */
    public function customisable(): bool
    {
        return true;
    }

    /**
     * And let them keep more than one arrangement.
     *
     * The second half of the opt-in, separate because the wishes are: this is
     * what draws the switcher and the "Save as" beside the Customise button.
     * Turned on here because the workbench is where the shipped chrome has to be
     * driven in a browser — a select nobody clicks is markup, not a feature.
     */
    public function savedLayouts(): bool
    {
        return true;
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        return [
            $this->billingTotals(),
            $this->invoicedByStatus(),
            $this->latestInvoices(),
            $this->workTotals(),
            $this->taskProgress(),
            $this->dueSoon(),
        ];
    }

    /**
     * Three columns rather than two, because the width stepper only means
     * something on a grid wide enough to step through: 1–4 columns against a
     * two-column grid is two sizes and two disabled buttons.
     */
    public function columns(): int
    {
        return 3;
    }

    /**
     * In a group of its own, sorted above both resource groups — so the menu
     * shows an entry that is not a resource, in a heading that contains nothing
     * else, ordered against every registration order there is.
     *
     * Named "Overview" rather than "Operations" on purpose: the key a dashboard
     * derives is its class name minus the suffix, so an `OperationsDashboard`
     * would key itself `operations` and collide with the *group* of that name
     * in the workbench's own url map. Nothing in the framework breaks — the two
     * are different namespaces — but the page it links to would be the wrong
     * one, which is exactly the kind of thing a driver should be able to see.
     */
    public static function navigation(): NavigationItem
    {
        return NavigationItem::make('Overview')
            ->icon('outline:chart-bar')
            ->group('insights');
    }

    /**
     * The two figures an invoice list is read for, at the width a pair of
     * figures reads well and no other.
     *
     * `sizes()` is the offer the tray honours, so a card that only looks right
     * at two columns is not dropped in as a 1×1 the user then has to fix.
     */
    private function billingTotals(): StatsOverviewWidget
    {
        return StatsOverviewWidget::make()
            ->key('billing-totals')
            ->group('Billing')
            ->heading('Billing')
            ->columnSpan(2)
            ->sizes([[2, 1], [3, 1], [1, 1]])
            ->stats([
                Stat::make('Invoices', (string) Invoice::query()->count())
                    ->icon('document-text')
                    ->color('primary'),
                Stat::make('Overdue', (string) Invoice::query()->where('status', 'overdue')->count())
                    ->description('unpaid past their date')
                    ->icon('exclamation-triangle')
                    ->color('danger'),
            ]);
    }

    /**
     * What has been invoiced, by status, from the line items themselves.
     *
     * A `BarChartWidget` rather than the Chart.js-backed `ChartWidget`: this
     * page is rendered by whatever layout an application mounts it in, and
     * Chart.js is the consuming application's dependency rather than something
     * the stack ships. A pure-CSS chart renders wherever the grid does, which is
     * the property a default dashboard needs.
     */
    private function invoicedByStatus(): BarChartWidget
    {
        $totals = Invoice::query()
            ->withSum('items', 'line_total')
            ->get()
            ->groupBy('status')
            ->map(fn ($invoices): float => (float) $invoices->sum('items_sum_line_total'));

        $colors = ['paid' => 'green', 'pending' => 'blue', 'overdue' => 'orange'];

        return BarChartWidget::make()
            ->key('invoiced-by-status')
            ->group('Billing')
            ->heading('Invoiced')
            ->description('Line totals by invoice status')
            ->type('vertical')
            ->variant('finance')
            ->height(200)
            ->sizes([[1, 1], [2, 1]])
            ->items($totals->map(fn (float $total, string $status): ChartItem => ChartItem::make(ucfirst($status))
                ->value($total)
                ->formattedValue(number_format($total, 0, ',', ' ').' Kč')
                ->color($colors[$status] ?? 'blue'))->values()->all());
    }

    private function latestInvoices(): ListWidget
    {
        $icons = ['paid' => 'check-circle', 'pending' => 'clock', 'overdue' => 'exclamation-circle'];
        $colors = ['paid' => 'success', 'pending' => 'primary', 'overdue' => 'danger'];

        return ListWidget::make()
            ->key('latest-invoices')
            ->group('Billing')
            ->heading('Latest invoices')
            ->description('Newest first')
            ->sizes([[1, 1], [2, 1]])
            ->items(Invoice::query()
                ->latest('issued_at')
                ->limit(5)
                ->get()
                ->map(fn (Invoice $invoice): ListItem => ListItem::make($invoice->number.' · '.$invoice->customer)
                    ->description(ucfirst((string) $invoice->status))
                    ->meta($invoice->issued_at?->diffForHumans())
                    ->icon($icons[$invoice->status] ?? 'document-text')
                    ->color($colors[$invoice->status] ?? 'gray'))
                ->all())
            ->emptyState('No invoices yet.');
    }

    private function workTotals(): StatsOverviewWidget
    {
        return StatsOverviewWidget::make()
            ->key('work-totals')
            ->group('Work')
            ->heading('Work')
            ->columnSpan(2)
            ->sizes([[2, 1], [3, 1], [1, 1]])
            ->stats([
                Stat::make('Open tasks', (string) Task::query()->where('completed', false)->count())
                    ->icon('clipboard-document-list')
                    ->color('warning'),
                Stat::make('Documents', (string) Document::query()->count())
                    ->icon('folder')
                    ->color('primary'),
            ]);
    }

    /**
     * Tasks done against tasks declared, per priority.
     *
     * Counted in one grouped query rather than three, because the point of this
     * dashboard is that every figure on it is a real one — and a widget that
     * costs three queries to say three numbers is how a dashboard becomes the
     * slowest page in an application.
     */
    private function taskProgress(): ProgressWidget
    {
        $byPriority = Task::query()
            ->selectRaw('priority, count(*) as total, sum(case when completed = 1 then 1 else 0 end) as done')
            ->groupBy('priority')
            ->get();

        $colors = ['high' => 'danger', 'medium' => 'warning', 'low' => 'success'];

        return ProgressWidget::make()
            ->key('task-progress')
            ->group('Work')
            ->heading('Task progress')
            ->description('Completed against declared, by priority')
            ->sizes([[1, 1], [2, 1]])
            ->items($byPriority
                ->map(fn ($row): ProgressItem => ProgressItem::make(ucfirst((string) $row->priority))
                    ->value((int) $row->done)
                    ->target(max(1, (int) $row->total))
                    ->formattedValue($row->done.' / '.$row->total)
                    ->color($colors[$row->priority] ?? 'primary'))
                ->all());
    }

    private function dueSoon(): ListWidget
    {
        return ListWidget::make()
            ->key('due-soon')
            ->group('Work')
            ->heading('Due soon')
            ->description('Open tasks with a date, soonest first')
            ->sizes([[1, 1], [2, 1]])
            ->items(Task::query()
                ->where('completed', false)
                ->whereNotNull('due_at')
                ->orderBy('due_at')
                ->limit(5)
                ->get()
                ->map(fn (Task $task): ListItem => ListItem::make($task->title)
                    ->description($task->owner_name)
                    ->meta($task->due_at?->diffForHumans())
                    ->icon($task->due_at?->isPast() === true ? 'exclamation-triangle' : 'calendar')
                    ->color($task->due_at?->isPast() === true ? 'danger' : 'gray'))
                ->all())
            ->emptyState('Nothing due.');
    }
}
