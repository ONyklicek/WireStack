<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use NyonCode\WireCore\Widgets\Concerns\WithWidgets;
use NyonCode\WireCore\Widgets\Dashboard;
use NyonCode\WireCore\Widgets\DashboardFilter;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;
use NyonCode\WireCore\Widgets\Support\DashboardFilterState;

/**
 * One filter over a whole dashboard: one selection, in the address, that every
 * widget reads — and a mark on the widget that cannot be narrowed by it.
 *
 * Step 7 of `architecture/plans/customisable-dashboards.md`.
 */
class FilteredDashboard extends Component
{
    use WithWidgets;

    protected function getDashboardFilters(): array
    {
        return [
            DashboardFilter::make('period')->label('Period')->buttons()
                ->options(['week' => 'This week', 'month' => 'This month', 'all' => 'All'])
                ->default('month'),
            DashboardFilter::make('customer')->label('Customer')->placeholder('All customers')
                ->options(fn (): array => [7 => 'Acme', 12 => 'Globex']),
        ];
    }

    protected function getWidgets(): array
    {
        return [
            StatsOverviewWidget::make()->key('made')->heading('Made')
                ->stats([Stat::make('Made '.$this->dashboardFilter('period'), (string) $this->dashboardFilter('customer'))]),
            StatsOverviewWidget::make()->key('server')->heading('Server')->ignoresDashboardFilters()
                ->stats([Stat::make('Up', 'yes')]),
        ];
    }

    public function render()
    {
        return view('controls-dashboard', $this->widgetGridData());
    }
}

final class FilteredSalesDashboard extends Dashboard
{
    public function filters(): array
    {
        return [DashboardFilter::make('period')->options(['week' => 'Week', 'month' => 'Month'])->default('month')];
    }

    public function widgets(): array
    {
        return [StatsOverviewWidget::make()->key('made')->heading('Made '.$this->filter('period'))];
    }
}

beforeEach(fn () => View::addLocation(__DIR__.'/../../Fixtures/views'));

// ─── The declaration ─────────────────────────────────────────────────────────

it('keeps a value only when it is one of the options', function (mixed $value, ?string $expected) {
    $filter = DashboardFilter::make('customer')->options([7 => 'Acme', 12 => 'Globex']);

    expect($filter->resolve($value))->toBe($expected);
})->with([
    'an option, as the address carries it' => ['12', '12'],
    'an option as an integer' => [7, '7'],
    'something the filter never offered' => ['99', null],
    'nothing' => [null, null],
    'an empty string — the placeholder' => ['', null],
    'an array smuggled in the query string' => [['7'], null],
]);

it('falls back to its default rather than to nothing', function () {
    $filter = DashboardFilter::make('period')->options(['week' => 'Week', 'month' => 'Month'])->default('month');

    expect($filter->resolve('decade'))->toBe('month')
        ->and($filter->getDefault())->toBe('month')
        ->and($filter->getLabel())->toBe('Period')
        ->and($filter->isButtons())->toBeFalse()
        ->and($filter->getOptions())->toBe(['week' => 'Week', 'month' => 'Month']);
});

it('knows when it narrows the dashboard and what belongs in the address', function () {
    $filters = [
        DashboardFilter::make('period')->options(['week' => 'Week', 'month' => 'Month'])->default('month'),
        DashboardFilter::make('customer')->options([7 => 'Acme']),
    ];

    $untouched = DashboardFilterState::resolve($filters, ['period' => 'month']);
    $narrowed = DashboardFilterState::resolve($filters, ['period' => 'week', 'customer' => '7']);

    expect($untouched->isNarrowed())->toBeFalse()
        ->and($untouched->toQuery())->toBe([])
        ->and($narrowed->isNarrowed())->toBeTrue()
        ->and($narrowed->toQuery())->toBe(['period' => 'week', 'customer' => '7'])
        ->and($narrowed->values())->toBe(['period' => 'week', 'customer' => '7'])
        ->and($narrowed->value('nope'))->toBeNull()
        ->and(DashboardFilterState::none()->filters())->toBe([]);
});

// ─── The host ────────────────────────────────────────────────────────────────

it('builds the widgets for the selection, starting from the defaults', function () {
    $component = Livewire::test(FilteredDashboard::class);

    expect($component->html())->toContain('Made month');

    $component->call('setDashboardFilter', 'period', 'week')->call('setDashboardFilter', 'customer', '12');

    expect($component->html())->toContain('Made week')
        ->and($component->get('dashboardFilters'))->toBe(['period' => 'week', 'customer' => '12']);
});

it('keeps only what differs from a default, and nothing it did not offer', function () {
    $component = Livewire::test(FilteredDashboard::class)
        ->call('setDashboardFilter', 'period', 'month')
        ->call('setDashboardFilter', 'customer', '99')
        ->call('setDashboardFilter', 'nope', 'x');

    expect($component->get('dashboardFilters'))->toBe([]);
});

it('reads the selection from the address, checked the same way', function () {
    $component = Livewire::withQueryParams(['dashboard' => ['period' => 'all', 'customer' => '404']])
        ->test(FilteredDashboard::class);

    expect($component->instance()->dashboardFilter('period'))->toBe('all')
        ->and($component->instance()->dashboardFilter('customer'))->toBeNull();
});

it('puts every filter back with one reset', function () {
    $component = Livewire::test(FilteredDashboard::class)
        ->call('setDashboardFilter', 'period', 'week')
        ->call('resetDashboardFilters');

    expect($component->get('dashboardFilters'))->toBe([])
        ->and($component->instance()->dashboardFilter('period'))->toBe('month');
});

it('does not let the browser write the selection directly', function () {
    Livewire::test(FilteredDashboard::class)->set('dashboardFilters', ['period' => 'decade']);
})->throws(CannotUpdateLockedPropertyException::class);

// ─── The bar and the mark ────────────────────────────────────────────────────

it('draws buttons for one filter, a select for the other, and a reset only when narrowed', function () {
    $component = Livewire::test(FilteredDashboard::class);
    $html = $component->html();

    expect($html)->toContain('data-testid="widget-filter-period-week"')
        ->and($html)->toContain('data-testid="widget-filter-customer"')
        ->and($html)->toContain('All customers')
        ->and($html)->not->toContain('data-testid="widget-filters-reset"');

    expect($component->call('setDashboardFilter', 'customer', '7')->html())
        ->toContain('data-testid="widget-filters-reset"');
});

it('marks the widget that ignores the filters, and only while they narrow', function () {
    $component = Livewire::test(FilteredDashboard::class);

    expect($component->html())->not->toContain('data-testid="widget-unfiltered-server"');

    expect($component->call('setDashboardFilter', 'period', 'week')->html())
        ->toContain('data-testid="widget-unfiltered-server"')
        ->not->toContain('data-testid="widget-unfiltered-made"');
});

it('draws no bar on a dashboard that declares no filters', function () {
    $html = view('wire-core::widgets.partials.widget-filters', ['filterState' => DashboardFilterState::none()])->render();

    expect(trim($html))->toBe('');
});

// ─── A declared dashboard ────────────────────────────────────────────────────

it('lets a declared dashboard read its filter, at its default until told otherwise', function () {
    $dashboard = new FilteredSalesDashboard;

    expect($dashboard->widgets()[0]->getHeading())->toBe('Made month');

    $dashboard->withFilterState(DashboardFilterState::resolve($dashboard->filters(), ['period' => 'week']));

    expect($dashboard->widgets()[0]->getHeading())->toBe('Made week');
});
