<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Tests\Unit\Filters;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\BooleanColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Data\CollectionDataSource;
use NyonCode\WireTable\Enums\ColumnFilterLayout;
use NyonCode\WireTable\Enums\DatePreset;
use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Filters\NumberRangeFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TernaryFilter;
use NyonCode\WireTable\Filters\TextFilter;
use NyonCode\WireTable\Services\FilterFacets;
use NyonCode\WireTable\Services\TableQueryService;
use NyonCode\WireTable\Table;

class CfxPerson extends Model
{
    protected $table = 'cfx_people';

    protected $guarded = [];

    protected $casts = ['active' => 'boolean'];
}

class CfxTable extends Component
{
    use WithTable;

    public string $layout = 'popover';

    public function table(Table $table): Table
    {
        return $table
            ->model(CfxPerson::class)
            ->columnFilterLayout($this->layout)
            ->cellFilterMenu()
            ->columns([
                TextColumn::make('name')->filterable()->filterOperators(),
                TextColumn::make('city')->filterAsValueList(),
                TextColumn::make('role')->filterAsMultiSelect(['admin' => 'Admin', 'editor' => 'Editor'])->filterOperators()->filterWithCounts(),
                BooleanColumn::make('active')->filterAsBoolean(),
                TextColumn::make('joined_on')->filterPresets([DatePreset::Last7Days, 'this_year', 'nonsense']),
            ])
            ->paginated(false);
    }

    public function render(): string
    {
        return '<div>{{ $this->table }}</div>';
    }
}

beforeEach(function () {
    Schema::create('cfx_people', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('city')->nullable();
        $table->string('role')->nullable();
        $table->boolean('active')->default(true);
        $table->date('joined_on')->nullable();
        $table->timestamps();
    });

    CarbonImmutable::setTestNow('2026-11-12 10:00:00');

    CfxPerson::create(['id' => 1, 'name' => 'Ada', 'city' => 'Praha', 'role' => 'admin', 'active' => true, 'joined_on' => '2026-11-10']);
    CfxPerson::create(['id' => 2, 'name' => 'Bo', 'city' => 'Brno', 'role' => 'editor', 'active' => false, 'joined_on' => '2026-01-05']);
    CfxPerson::create(['id' => 3, 'name' => 'Cy', 'city' => 'Praha', 'role' => 'editor', 'active' => true, 'joined_on' => '2025-06-01']);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    Schema::dropIfExists('cfx_people');
});

// ─── Date presets ────────────────────────────────────────────────────────

it('works out every preset from today, weeks starting on Monday', function () {
    $now = CarbonImmutable::parse('2026-11-12 10:00'); // a Thursday

    expect(DatePreset::Today->range($now))->toBe(['from' => '2026-11-12', 'to' => '2026-11-12'])
        ->and(DatePreset::Yesterday->range($now))->toBe(['from' => '2026-11-11', 'to' => '2026-11-11'])
        ->and(DatePreset::Last7Days->range($now))->toBe(['from' => '2026-11-06', 'to' => '2026-11-12'])
        ->and(DatePreset::Last30Days->range($now))->toBe(['from' => '2026-10-14', 'to' => '2026-11-12'])
        ->and(DatePreset::ThisWeek->range($now))->toBe(['from' => '2026-11-09', 'to' => '2026-11-15'])
        ->and(DatePreset::LastWeek->range($now))->toBe(['from' => '2026-11-02', 'to' => '2026-11-08'])
        ->and(DatePreset::ThisMonth->range($now))->toBe(['from' => '2026-11-01', 'to' => '2026-11-30'])
        ->and(DatePreset::LastMonth->range(CarbonImmutable::parse('2026-03-31')))->toBe(['from' => '2026-02-01', 'to' => '2026-02-28'])
        ->and(DatePreset::ThisQuarter->range($now))->toBe(['from' => '2026-10-01', 'to' => '2026-12-31'])
        ->and(DatePreset::ThisYear->range($now))->toBe(['from' => '2026-01-01', 'to' => '2026-12-31'])
        ->and(DatePreset::LastYear->range($now))->toBe(['from' => '2025-01-01', 'to' => '2025-12-31'])
        ->and(DatePreset::Today->label())->toBe('Today');
});

it('stores the preset name and resolves it when the query runs', function () {
    $filter = DateFilter::make('joined_on')->presets();
    $table = Table::make()->model(CfxPerson::class)->filters([$filter]);
    $run = fn (array $state) => (new TableQueryService)->buildQuery(CfxPerson::query(), $table, filterValues: ['joined_on' => $state])->orderBy('id')->pluck('name')->all();

    expect($filter->isRange())->toBeTrue()
        ->and($run(['from' => null, 'to' => null, 'preset' => 'last_7_days']))->toBe(['Ada'])
        ->and($run(['from' => null, 'to' => null, 'preset' => 'this_year']))->toBe(['Ada', 'Bo'])
        ->and($run(['from' => '2025-01-01', 'to' => '2025-12-31', 'preset' => null]))->toBe(['Cy'])
        // A preset this filter does not offer is not honoured.
        ->and(DateFilter::make('d')->presets([DatePreset::Today])->presetFromState(['preset' => 'this_year']))->toBeNull()
        ->and($filter->getIndicator(['preset' => 'this_month']))->toBe('Joined On: This month')
        ->and($filter->getQueryStringFields())->toBe(['from' => '_from', 'to' => '_to', 'preset' => '_preset'])
        ->and($filter->emptyState())->toBe(['from' => null, 'to' => null, 'preset' => null])
        ->and(DateFilter::make('d')->range()->emptyState())->toBe(['from' => null, 'to' => null])
        ->and(DateFilter::make('d')->emptyState())->toBeNull()
        ->and(DateFilter::make('d')->presets(false)->hasPresets())->toBeFalse();
});

it('reads "today" in the filter timezone', function () {
    CarbonImmutable::setTestNow('2026-11-12 23:30:00'); // UTC

    $range = DateFilter::make('d')->presets()->timezone('Asia/Tokyo')->resolveRange(['preset' => 'today']);

    expect($range)->toBe(['from' => '2026-11-13', 'to' => '2026-11-13']);
});

// ─── Counts and the value list ───────────────────────────────────────────

it('counts each option under the other filters, never under its own', function () {
    $table = Table::make()->model(CfxPerson::class);
    $role = SelectFilter::make('role')->multiple()->options(['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'])->withCounts();

    $all = app(FilterFacets::class)->count($table, $role, [], ['role' => ['admin']], null, 'role', isColumnFilter: true);
    $praha = app(FilterFacets::class)->count($table->columns([TextColumn::make('city')->filterable()]), $role, [], ['city' => 'Praha', 'role' => ['admin']], null, 'role', isColumnFilter: true);

    expect($all)->toBe(['editor' => 2, 'admin' => 1])
        ->and($praha)->toEqual(['admin' => 1, 'editor' => 1])
        ->and($role->facets($all)->getDisplayOptions())->toBe(['admin' => 'Admin (1)', 'editor' => 'Editor (2)', 'viewer' => 'Viewer (0)'])
        // The chip keeps the plain label.
        ->and($role->getIndicator(['value' => ['admin']]))->toBe('Role: Admin');
});

it('does not count a relation, a dotted column or a custom data source', function () {
    $table = Table::make()->model(CfxPerson::class);

    expect(app(FilterFacets::class)->count($table, SelectFilter::make('team.name'), [], [], null, 'team.name', false))->toBeNull()
        ->and(app(FilterFacets::class)->count($table, SelectFilter::make('x')->column('people.city'), [], [], null, 'x', false))->toBeNull()
        ->and(app(FilterFacets::class)->count(Table::make()->dataSource(new CollectionDataSource(collect([]))), SelectFilter::make('city'), [], [], null, 'city', false))->toBeNull();
});

it('takes the options from the data, most common first', function () {
    $filter = SelectFilter::make('city')->optionsFromColumn(true, 5)->facets(['Praha' => 2, 'Brno' => 1]);

    expect($filter->wantsFacets())->toBeTrue()
        ->and($filter->getFacetLimit())->toBe(5)
        ->and($filter->getOptions())->toBe(['Praha' => 'Praha', 'Brno' => 'Brno'])
        ->and($filter->getFacets())->toBe(['Praha' => 2, 'Brno' => 1])
        ->and($filter->hasCounts())->toBeFalse()
        ->and(SelectFilter::make('c')->wantsFacets())->toBeFalse()
        ->and(SelectFilter::make('c')->options(['a' => 'A'])->getDisplayOptions())->toBe(['a' => 'A']);
});

// ─── The popover layout and the cell menu, rendered ──────────────────────

it('draws a funnel per filterable column instead of a filter row, marked while filtered', function () {
    $component = Livewire::test(CfxTable::class);

    $component->assertSeeHtml('data-testid="column-filter-trigger-name"')
        ->assertSeeHtml('data-testid="column-filter-trigger-joined_on"')
        ->assertDontSeeHtml('data-testid="table-filter-name"')
        ->assertDontSeeHtml('data-filtered')
        // The value list read the cities and counted them.
        ->assertSeeHtml('Praha (2)')
        ->assertSeeHtml('data-cell-filter="exclude"')
        ->assertSeeHtml('data-testid="cell-filter-menu"');

    $component->set('tableState.columnFilters.name', ['value' => 'Ad', 'operator' => 'starts_with'])
        ->assertSeeHtml('data-filtered')
        ->assertSeeHtml('data-testid="column-filter-clear-name"')
        ->assertSeeHtml('<option value="starts_with" selected');
});

it('keeps the filter row in the row layout', function () {
    Livewire::test(CfxTable::class, ['layout' => 'row'])
        ->assertSeeHtml('data-testid="table-filter-name"')
        ->assertDontSeeHtml('data-testid="column-filter-trigger-name"');

    expect(Table::make()->getColumnFilterLayout())->toBe(ColumnFilterLayout::Row);

    config(['wire-table.defaults.column_filter_layout' => 'popover']);
    expect(Table::make()->getColumnFilterLayout())->toBe(ColumnFilterLayout::Popover);
});

it('hides the value box for an operator that takes none, and shows the preset range', function () {
    Livewire::test(CfxTable::class)
        ->set('tableState.columnFilters.name', ['value' => null, 'operator' => 'is_empty'])
        ->assertSeeHtml('data-filter-operator="is_empty"')
        ->set('tableState.columnFilters.joined_on', ['from' => null, 'to' => null, 'preset' => 'last_7_days'])
        ->assertSeeHtml('2026-11-06 – 2026-11-12');
});

it('sets a header filter from a cell, reading the value from the record', function () {
    $component = Livewire::test(CfxTable::class);

    $component->call('filterByCellValue', '1', 'role', true);
    expect($component->get('tableState.columnFilters.role'))->toBe(['value' => ['admin'], 'operator' => 'not_in']);

    $component->call('filterByCellValue', '2', 'city');
    expect($component->get('tableState.columnFilters.city'))->toBe(['Brno']);

    $component->call('filterByCellValue', '2', 'active');
    expect($component->get('tableState.columnFilters.active'))->toBe('false');

    $component->call('filterByCellValue', '1', 'joined_on');
    expect($component->get('tableState.columnFilters.joined_on'))->toBe(['from' => '2026-11-10', 'to' => '2026-11-10', 'preset' => null]);

    // Nothing to say: an unknown column, a missing record, an exclusion the
    // filter has no operator for — the state is left as it was.
    $before = $component->get('tableState.columnFilters');
    $component->call('filterByCellValue', '1', 'nope')
        ->call('filterByCellValue', '99', 'role')
        ->call('filterByCellValue', '1', 'city', true);
    expect($component->get('tableState.columnFilters'))->toBe($before);
});

it('maps a cell value to each filter type’s state', function () {
    expect(TextFilter::make('t')->stateForCellValue('x'))->toBe('x')
        ->and(TextFilter::make('t')->stateForCellValue('x', exclude: true))->toBeNull()
        ->and(TextFilter::make('t')->operators()->stateForCellValue('x', exclude: true))->toBe(['value' => 'x', 'operator' => 'not_equals'])
        ->and(TextFilter::make('t')->operators(['contains', 'starts_with'])->stateForCellValue('x'))->toBeNull()
        ->and(TextFilter::make('t')->stateForCellValue(null))->toBeNull()
        ->and(TextFilter::make('t')->stateForCellValue(new \stdClass))->toBeNull()
        ->and(TextFilter::make('t')->stateForCellValue(ColumnFilterLayout::Row))->toBe('row')
        ->and(TernaryFilter::make('a')->stateForCellValue(1))->toBe('true')
        ->and(TernaryFilter::make('a')->stateForCellValue(true, exclude: true))->toBeNull()
        ->and(TernaryFilter::make('a')->stateForCellValue('maybe'))->toBeNull()
        ->and(NumberRangeFilter::make('n')->stateForCellValue(5))->toBe(['min' => '5', 'max' => '5'])
        ->and(NumberRangeFilter::make('n')->stateForCellValue('x'))->toBeNull()
        ->and(NumberRangeFilter::make('n')->emptyState())->toBe(['min' => null, 'max' => null])
        ->and(DateFilter::make('d')->stateForCellValue('2026-01-02 10:00'))->toBe('2026-01-02')
        ->and(DateFilter::make('d')->stateForCellValue(CarbonImmutable::parse('2026-01-02')))->toBe('2026-01-02')
        ->and(DateFilter::make('d')->stateForCellValue('not a date'))->toBeNull()
        ->and(DateFilter::make('d')->month()->stateForCellValue('2026-01-02'))->toBeNull()
        ->and(Filter::make('x')->multiple()->stateForCellValue('a'))->toBe(['a'])
        ->and(Filter::make('x')->canExcludeCellValue())->toBeFalse();
});

it('restores the empty slots when filters are cleared', function () {
    $component = Livewire::test(CfxTable::class)
        ->set('tableState.columnFilters.name', ['value' => 'Ad', 'operator' => 'starts_with'])
        ->call('removeColumnFilter', 'name');

    expect($component->get('tableState.columnFilters.name'))->toBe(['value' => null, 'operator' => 'contains']);

    $component->call('resetColumnFilters');
    expect($component->get('tableState.columnFilters.joined_on'))->toBe(['from' => null, 'to' => null, 'preset' => null]);
});
