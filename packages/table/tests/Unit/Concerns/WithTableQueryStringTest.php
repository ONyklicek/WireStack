<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Filters\NumberRangeFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Livewire\TableUrl;
use NyonCode\WireTable\Table;

// ─── Test Model ──────────────────────────────────────────────────────────────

class WtqsItem extends Model
{
    protected $table = 'wtqs_items';

    protected $guarded = [];
}

// ─── Test Components ─────────────────────────────────────────────────────────

class WtqsComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtqsItem::class)
            ->queryString()
            ->perPageOptions([10, 25, 50])
            ->columns([
                Column::make('name')->sortable()->searchable()->filterAsMultiSelect(['x' => 'Xray', 'y' => 'Yankee']),
                Column::make('status')->filterAsSelect(['active' => 'Active', 'archived' => 'Archived']),
                Column::make('price')->filterAsNumberRange(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['active' => 'Active', 'archived' => 'Archived']),
                SelectFilter::make('statuses')->column('status')->multiple()->options(['active' => 'Active', 'archived' => 'Archived']),
                NumberRangeFilter::make('price'),
                SelectFilter::make('secret')->options(['a' => 'A'])->hidden(),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class WtqsDisabledComponent extends WtqsComponent
{
    public function table(Table $table): Table
    {
        return parent::table($table)->queryString(false);
    }
}

class WtqsPrefixedComponent extends WtqsComponent
{
    public function table(Table $table): Table
    {
        return parent::table($table)->queryString('items_');
    }
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Schema::create('wtqs_items', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('status');
        $table->integer('price');
        $table->timestamps();
    });

    WtqsItem::create(['name' => 'Widget', 'status' => 'active', 'price' => 10]);
    WtqsItem::create(['name' => 'Gadget', 'status' => 'archived', 'price' => 50]);
    WtqsItem::create(['name' => 'Doohickey', 'status' => 'active', 'price' => 100]);
});

afterEach(function () {
    Schema::dropIfExists('wtqs_items');
});

function wtqsState(array $queryParams, string $component = WtqsComponent::class)
{
    return Livewire::withQueryParams($queryParams)->test($component)->instance()->tableState;
}

// ─── Seeding from the URL ────────────────────────────────────────────────────

it('seeds search from the query string', function () {
    expect(wtqsState(['search' => 'widget'])->get('search'))->toBe('widget');
});

it('applies a seeded search to the rendered records', function () {
    Livewire::withQueryParams(['search' => 'widget'])
        ->test(WtqsComponent::class)
        ->assertSee('Widget')
        ->assertDontSee('Gadget');
});

it('seeds sort column and direction for sortable columns', function () {
    $state = wtqsState(['sort' => 'name', 'direction' => 'desc']);

    expect($state->get('sort.column'))->toBe('name')
        ->and($state->get('sort.direction'))->toBe('desc');
});

it('rejects sort columns that are not sortable', function () {
    expect(wtqsState(['sort' => 'status'])->get('sort.column'))->toBe('');
});

it('normalizes unknown sort directions to asc', function () {
    expect(wtqsState(['sort' => 'name', 'direction' => 'sideways'])->get('sort.direction'))->toBe('asc');
});

it('seeds per page when the value is a configured option', function () {
    expect(wtqsState(['per_page' => '25'])->get('pagination.perPage'))->toBe(25);
});

it('rejects per page values outside the configured options', function () {
    expect(wtqsState(['per_page' => '13'])->get('pagination.perPage'))->toBe(10);
});

it('seeds a single-value filter', function () {
    expect(wtqsState(['filter_status' => 'active'])->get('filters'))
        ->toBe(['status' => ['value' => 'active']]);
});

it('applies a seeded filter to the rendered records', function () {
    Livewire::withQueryParams(['filter_status' => 'archived'])
        ->test(WtqsComponent::class)
        ->assertSee('Gadget')
        ->assertDontSee('Widget');
});

it('seeds number range filter bounds from suffixed parameters', function () {
    expect(wtqsState(['filter_price_min' => '20', 'filter_price_max' => '60'])->get('filters'))
        ->toBe(['price' => ['min' => '20', 'max' => '60']]);
});

it('seeds array values for multiple filters', function () {
    expect(wtqsState(['filter_statuses' => ['active', 'archived']])->get('filters'))
        ->toBe(['statuses' => ['value' => ['active', 'archived']]]);
});

it('rejects array values for non-multiple filters', function () {
    expect(wtqsState(['filter_status' => ['active']])->get('filters'))->toBe([]);
});

it('ignores parameters for unknown filters', function () {
    expect(wtqsState(['filter_nope' => 'x'])->get('filters'))->toBe([]);
});

it('does not seed hidden filters', function () {
    expect(wtqsState(['filter_secret' => 'a'])->get('filters'))->toBe([]);
});

it('does not seed anything when query string persistence is disabled', function () {
    $state = wtqsState(['search' => 'widget', 'sort' => 'name'], WtqsDisabledComponent::class);

    expect($state->get('search'))->toBeNull()
        ->and($state->get('sort.column'))->toBe('');
});

it('uses the configured prefix for every parameter', function () {
    $state = wtqsState([
        'items_search' => 'widget',
        'items_filter_status' => 'active',
        'search' => 'ignored',
    ], WtqsPrefixedComponent::class);

    expect($state->get('search'))->toBe('widget')
        ->and($state->get('filters'))->toBe(['status' => ['value' => 'active']]);
});

// ─── Column header filters ───────────────────────────────────────────────────

it('seeds a single-value column filter from a col_ parameter', function () {
    expect(wtqsState(['col_status' => 'active'])->get('columnFilters.status'))->toBe('active');
});

it('seeds column number-range filter bounds from suffixed col_ parameters', function () {
    expect(wtqsState(['col_price_min' => '20', 'col_price_max' => '60'])->get('columnFilters')['price'])
        ->toBe(['min' => '20', 'max' => '60']);
});

it('seeds array values for a multi-select column filter', function () {
    expect(wtqsState(['col_name' => ['x', 'y']])->get('columnFilters.name'))
        ->toBe(['x', 'y']);
});

it('applies a seeded column filter to the rendered records', function () {
    Livewire::withQueryParams(['col_status' => 'archived'])
        ->test(WtqsComponent::class)
        ->assertSee('Gadget')
        ->assertDontSee('Doohickey');
});

// ─── URL-tracking attribute registration ─────────────────────────────────────

it('registers TableUrl attributes for every tracked state path', function () {
    $component = Livewire::test(WtqsComponent::class)->instance();

    $tracked = $component->getAttributes()
        ->filter(fn ($attribute) => $attribute instanceof TableUrl)
        ->map(fn (TableUrl $attribute) => [$attribute->getName(), $attribute->urlName()])
        ->values()
        ->all();

    expect($tracked)->toBe([
        ['tableState.search', 'search'],
        ['tableState.sort.column', 'sort'],
        ['tableState.sort.direction', 'direction'],
        ['tableState.pagination.perPage', 'per_page'],
        ['tableState.filters.status.value', 'filter_status'],
        ['tableState.filters.statuses.value', 'filter_statuses'],
        ['tableState.filters.price.min', 'filter_price_min'],
        ['tableState.filters.price.max', 'filter_price_max'],
        ['tableState.columnFilters.name', 'col_name'],
        ['tableState.columnFilters.status', 'col_status'],
        ['tableState.columnFilters.price.min', 'col_price_min'],
        ['tableState.columnFilters.price.max', 'col_price_max'],
    ]);
});

it('registers no TableUrl attributes when disabled', function () {
    $component = Livewire::test(WtqsDisabledComponent::class)->instance();

    $tracked = $component->getAttributes()
        ->filter(fn ($attribute) => $attribute instanceof TableUrl);

    expect($tracked)->toHaveCount(0);
});
