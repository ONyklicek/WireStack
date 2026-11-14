<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Tests\Unit\Filters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Enums\FilterOperator;
use NyonCode\WireTable\Enums\FiltersLayout;
use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\QueryBuilderFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TernaryFilter;
use NyonCode\WireTable\Filters\TextFilter;
use NyonCode\WireTable\Services\TableQueryService;
use NyonCode\WireTable\Support\QueryBuilderState;
use NyonCode\WireTable\Table;

class QbInvoice extends Model
{
    protected $table = 'qb_invoices';

    protected $guarded = [];

    protected $casts = ['paid' => 'boolean'];
}

class QbTable extends Component
{
    use WithTable;

    public string $layout = 'dropdown';

    public bool $deferred = false;

    public function table(Table $table): Table
    {
        return $table
            ->model(QbInvoice::class)
            ->columns([TextColumn::make('customer'), TextColumn::make('status')])
            ->filters([
                TextFilter::make('customer')->operators(),
                qbBuilder(),
            ])
            ->filtersLayout($this->layout)
            ->filtersFormColumns(2)
            ->deferFilters($this->deferred)
            ->paginated(false);
    }

    public function render(): string
    {
        return '<div>{{ $this->table }}</div>';
    }
}

function qbBuilder(): QueryBuilderFilter
{
    return QueryBuilderFilter::make('conditions')->constraints([
        TextFilter::make('customer'),
        SelectFilter::make('status')->options(['paid' => 'Paid', 'pending' => 'Pending', 'overdue' => 'Overdue']),
        DateFilter::make('issued_on'),
        TernaryFilter::make('paid'),
    ]);
}

beforeEach(function () {
    Schema::create('qb_invoices', function (Blueprint $table) {
        $table->id();
        $table->string('customer');
        $table->string('status');
        $table->date('issued_on')->nullable();
        $table->boolean('paid')->default(false);
        $table->timestamps();
    });

    QbInvoice::create(['id' => 1, 'customer' => 'Acme', 'status' => 'paid', 'issued_on' => '2026-01-10', 'paid' => true]);
    QbInvoice::create(['id' => 2, 'customer' => 'Beta', 'status' => 'pending', 'issued_on' => '2026-03-01', 'paid' => false]);
    QbInvoice::create(['id' => 3, 'customer' => 'Acme East', 'status' => 'overdue', 'issued_on' => null, 'paid' => false]);
    QbInvoice::create(['id' => 4, 'customer' => 'Gamma', 'status' => 'paid', 'issued_on' => '2026-05-20', 'paid' => true]);
});

afterEach(function () {
    Schema::dropIfExists('qb_invoices');
});

function qbRun(array $state): array
{
    $filter = qbBuilder();

    return (new TableQueryService)->buildQuery(
        baseQuery: QbInvoice::query(),
        table: Table::make()->model(QbInvoice::class)->filters([$filter]),
        filterValues: ['conditions' => $state],
    )->orderBy('id')->pluck('id')->all();
}

function qbRule(string $field, string $operator, mixed $value = null): array
{
    return ['field' => $field, 'operator' => $operator, 'value' => $value];
}

// ─── Combining ───────────────────────────────────────────────────────────

it('combines conditions with "all of" and "any of"', function () {
    expect(qbRun(['combinator' => 'and', 'rules' => [qbRule('customer', 'contains', 'Acme'), qbRule('status', 'in', ['paid'])]]))->toBe([1])
        ->and(qbRun(['combinator' => 'or', 'rules' => [qbRule('customer', 'contains', 'Beta'), qbRule('status', 'in', ['overdue'])]]))->toBe([2, 3]);
});

it('nests a group with the opposite combinator', function () {
    // all of: paid is yes, and any of: customer is Gamma, customer starts with Ac
    $state = ['combinator' => 'and', 'rules' => [
        qbRule('paid', 'equals', 'true'),
        ['combinator' => 'or', 'rules' => [qbRule('customer', 'equals', 'Gamma'), qbRule('customer', 'starts_with', 'Ac')]],
    ]];

    expect(qbRun($state))->toBe([1, 4]);
});

it('compares each field the way its own filter does', function () {
    expect(qbRun(['combinator' => 'and', 'rules' => [qbRule('issued_on', 'lt', '2026-03-01')]]))->toBe([1])
        ->and(qbRun(['combinator' => 'and', 'rules' => [qbRule('issued_on', 'gte', '2026-03-01')]]))->toBe([2, 4])
        ->and(qbRun(['combinator' => 'and', 'rules' => [qbRule('issued_on', 'is_empty')]]))->toBe([3])
        ->and(qbRun(['combinator' => 'and', 'rules' => [qbRule('issued_on', 'not_equals', '2026-01-10')]]))->toBe([2, 3, 4])
        ->and(qbRun(['combinator' => 'and', 'rules' => [qbRule('status', 'not_in', ['paid'])]]))->toBe([2, 3])
        ->and(qbRun(['combinator' => 'and', 'rules' => [qbRule('customer', 'not_contains', 'Acme')]]))->toBe([2, 4]);
});

it('ignores conditions without a value, on an unknown field, or crafted', function () {
    expect(qbRun(['combinator' => 'and', 'rules' => [qbRule('customer', 'contains', '')]]))->toBe([1, 2, 3, 4])
        ->and(qbRun(['combinator' => 'and', 'rules' => [qbRule('secret', 'equals', 'x')]]))->toBe([1, 2, 3, 4])
        ->and(qbRun(['combinator' => 'xor', 'rules' => ['junk', qbRule('customer', 'equals', 'Beta')]]))->toBe([2])
        ->and(qbRun(['combinator' => 'and', 'rules' => [['combinator' => 'or', 'rules' => []]]]))->toBe([1, 2, 3, 4]);
});

it('words the active conditions as one chip', function () {
    $filter = qbBuilder();
    $state = ['combinator' => 'and', 'rules' => [
        qbRule('customer', 'not_contains', 'Acme'),
        ['combinator' => 'or', 'rules' => [qbRule('status', 'in', ['paid']), qbRule('issued_on', 'is_empty')]],
        qbRule('customer', 'contains', ''),
    ]];

    expect($filter->getIndicator($state))->toBe('Customer does not contain Acme and (Status is any of Paid or Issued On is empty)')
        ->and($filter->getIndicator(QueryBuilderState::empty()))->toBeNull()
        ->and(qbBuilder()->indicator('Advanced')->getIndicator($state))->toBe('Advanced');
});

it('sets its constraints up as fields: every operator, a select made multiple', function () {
    $filter = qbBuilder();

    expect(array_keys($filter->getConstraints()))->toBe(['customer', 'status', 'issued_on', 'paid'])
        ->and($filter->getConstraint('status')->isMultiple())->toBeTrue()
        ->and($filter->getConstraint('customer')->getOperators())->toBe(FilterOperator::forText())
        ->and($filter->getConstraint('issued_on')->operatorLabel(FilterOperator::LessThan))->toBe('is before')
        ->and($filter->getConstraint('paid')->getOperators())->toBe([])
        ->and($filter->getConstraint(null))->toBeNull()
        ->and($filter->getFirstConstraint()->getName())->toBe('customer')
        ->and(QueryBuilderFilter::make('x')->getFirstConstraint())->toBeNull()
        ->and($filter->spansFullWidth())->toBeTrue()
        ->and($filter->getQueryStringFields())->toBe([])
        ->and($filter->wrapValue(null))->toBe(QueryBuilderState::empty())
        ->and($filter->toPlannerDefinitions(['rules' => []]))->toBe([])
        ->and($filter->getConstraint('status')->builderInput())->toBe('select')
        ->and($filter->getConstraint('issued_on')->builderInput())->toBe('date')
        ->and($filter->getConstraint('paid')->builderInput())->toBe('boolean')
        ->and($filter->getConstraint('customer')->builderInput())->toBe('text');
});

it('hands a ->query() callback the whole tree', function () {
    $seen = null;
    $filter = qbBuilder()->query(function ($query, $value) use (&$seen) {
        $seen = $value;

        return $query;
    });
    $state = ['combinator' => 'and', 'rules' => [qbRule('customer', 'contains', 'Ac')]];

    $filter->apply(QbInvoice::query(), $state);

    expect($seen)->toBe($state);
});

// ─── The state edits ─────────────────────────────────────────────────────

it('adds and removes conditions and groups', function () {
    $state = QueryBuilderState::addRule(QueryBuilderState::empty(), 'customer', 'contains');
    $state = QueryBuilderState::addGroup($state, 'status', 'in');
    $state = QueryBuilderState::addRule($state, 'customer', 'equals', group: 1);
    $state = QueryBuilderState::addRule($state, 'x', 'y', group: 7);

    expect($state['rules'])->toHaveCount(2)
        ->and($state['rules'][1]['combinator'])->toBe('or')
        ->and($state['rules'][1]['rules'])->toHaveCount(2);

    $state = QueryBuilderState::remove($state, 0, group: 1);
    expect($state['rules'][1]['rules'])->toBe([qbRule('customer', 'equals')]);

    $state = QueryBuilderState::remove($state, 0, group: 1);
    expect($state['rules'])->toBe([qbRule('customer', 'contains')]);

    $state = QueryBuilderState::remove($state, 0);
    expect($state)->toBe(QueryBuilderState::empty())
        ->and(QueryBuilderState::remove($state, 0, group: 3))->toBe(QueryBuilderState::empty())
        // A group inside a group is not kept.
        ->and(QueryBuilderState::normalize(['rules' => [['rules' => [['rules' => []]]]]])['rules'][0]['rules'])->toBe([])
        ->and(QueryBuilderState::normalize('nope'))->toBe(QueryBuilderState::empty());
});

// ─── In a component ──────────────────────────────────────────────────────

it('edits the tree through the component and narrows the rows', function () {
    $component = Livewire::test(QbTable::class)
        ->assertSeeHtml('data-testid="query-builder-conditions"')
        ->assertSeeHtml('No conditions yet.')
        ->call('queryBuilderAddRule', 'conditions')
        ->call('queryBuilderAddGroup', 'conditions');

    expect($component->get('tableState.filters.conditions.rules'))->toHaveCount(2)
        ->and($component->get('tableState.filters.conditions.rules.0'))->toBe(qbRule('customer', 'contains'));

    $component->set('tableState.filters.conditions.rules.0.value', 'Acme')
        ->assertSeeHtml('Customer contains Acme')
        ->call('queryBuilderRemove', 'conditions', 0, 1)
        ->call('queryBuilderAddRule', 'nope')
        ->call('queryBuilderRemove', 'conditions', 0);

    expect($component->get('tableState.filters.conditions'))->toBe(QueryBuilderState::empty());

    $component->call('resetTableFilters');
    expect($component->get('tableState.filters.conditions'))->toBe(QueryBuilderState::empty())
        ->and($component->get('tableState.filters.customer'))->toBe(['value' => null, 'operator' => 'contains']);
});

// ─── Deferred panel and layouts ──────────────────────────────────────────

it('binds a deferred panel to a draft and shows Apply', function () {
    Livewire::test(QbTable::class, ['deferred' => true])
        ->assertSeeHtml('wire:model="tableState.filterDraft.customer.value"')
        ->assertSeeHtml('wire:model.live="tableState.filterDraft.conditions.combinator"')
        ->assertSeeHtml('data-testid="table-filters-apply"')
        ->call('applyTableFilters')
        ->assertOk();

    Livewire::test(QbTable::class)
        ->assertSeeHtml('wire:model.live.debounce.500ms="tableState.filters.customer.value"')
        ->assertDontSeeHtml('data-testid="table-filters-apply"');
});

function qbShown($component): array
{
    return $component->instance()->getTableRecords()->pluck('customer')->sort()->values()->all();
}

it('narrows nothing until Apply, however many requests the draft rides', function () {
    $component = Livewire::test(QbTable::class, ['deferred' => true])
        ->set('tableState.filterDraft.customer.value', 'Acme')
        ->call('queryBuilderAddRule', 'conditions')
        ->set('tableState.filterDraft.conditions.rules.0.field', 'status')
        ->set('tableState.filterDraft.conditions.rules.0.value', ['paid'])
        ->set('tableState.filterDraft.conditions.combinator', 'or')
        // Another request in between — a sort — carries the draft and must not apply it.
        ->call('sortTable', 'customer');

    expect(qbShown($component))->toBe(['Acme', 'Acme East', 'Beta', 'Gamma'])
        ->and($component->get('tableState.filters.customer.value'))->toBeNull()
        ->and($component->get('tableState.filters.conditions'))->toBe(QueryBuilderState::empty());

    $component->call('applyTableFilters');

    // customer contains "Acme" AND (status is paid) — the builder's "any of" has one rule.
    expect(qbShown($component))->toBe(['Acme'])
        ->and($component->get('tableState.filterDraft'))->toBe($component->get('tableState.filters'));
});

it('takes the draft again when something else rewrites the filters', function () {
    $component = Livewire::test(QbTable::class, ['deferred' => true])
        ->set('tableState.filterDraft.customer.value', 'Beta')
        ->call('applyTableFilters');

    expect(qbShown($component))->toBe(['Beta']);

    // An unapplied edit survives a request that leaves the filters alone …
    $component->set('tableState.filterDraft.customer.value', 'Gamma')->call('sortTable', 'customer');
    expect($component->get('tableState.filterDraft.customer.value'))->toBe('Gamma');

    // … and Reset, which does not, starts the draft over from what it wrote.
    $component->call('resetTableFilters');
    expect(qbShown($component))->toBe(['Acme', 'Acme East', 'Beta', 'Gamma'])
        ->and($component->get('tableState.filterDraft.customer.value'))->toBeNull();
});

it('applies a live panel at once and reads the filters, not a draft', function () {
    $component = Livewire::test(QbTable::class)
        ->call('queryBuilderAddRule', 'conditions');

    expect($component->get('tableState.filterDraft'))->toBeNull()
        ->and($component->get('tableState.filters.conditions.rules'))->toHaveCount(1);

    // Apply on a live panel only goes back to the first page.
    $component->call('applyTableFilters')->assertOk();
});

it('draws the filters above the rows, collapsible or always open', function () {
    Livewire::test(QbTable::class, ['layout' => 'above'])
        ->assertSeeHtml('data-testid="table-filters-above"')
        ->assertDontSeeHtml('data-testid="table-filters-trigger"');

    Livewire::test(QbTable::class, ['layout' => 'above_collapsible'])
        ->assertSeeHtml('data-testid="table-filters-above"')
        ->assertSeeHtml('wire-table-filters-toggle');

    Livewire::test(QbTable::class)
        ->assertDontSeeHtml('data-testid="table-filters-above"')
        ->assertSeeHtml('sm:w-[32rem]')
        ->assertSeeHtml('grid gap-4 sm:grid-cols-2');

    expect(FiltersLayout::Dropdown->isAboveContent())->toBeFalse()
        ->and(Table::make()->getFiltersLayout())->toBe(FiltersLayout::Dropdown)
        ->and(Table::make()->hasDeferredFilters())->toBeFalse()
        ->and(Table::make()->filtersFormColumns(9)->getFiltersFormColumns())->toBe(4)
        ->and(Table::make()->filtersFormColumns(3)->getFiltersGridClass())->toBe('grid gap-4 sm:grid-cols-2 lg:grid-cols-3')
        ->and(Table::make()->filtersFormColumns(4)->getFiltersGridClass())->toBe('grid gap-4 sm:grid-cols-2 lg:grid-cols-4')
        ->and(Table::make()->filtersFormColumns(3)->getFiltersPanelWidthClass())->toBe('w-72 sm:w-[44rem]')
        ->and(Table::make()->getFiltersPanelWidthClass())->toBe('w-72')
        ->and(Table::make()->filtersLayout(FiltersLayout::AboveContent)->getFiltersLayout())->toBe(FiltersLayout::AboveContent);

    config(['wire-table.defaults.filters_layout' => 'above', 'wire-table.defaults.defer_filters' => true]);
    expect(Table::make()->getFiltersLayout())->toBe(FiltersLayout::AboveContent)
        ->and(Table::make()->hasDeferredFilters())->toBeTrue();
});

it('leaves out a constraint the user may not see — from the fields, the query and the chip', function () {
    $filter = QueryBuilderFilter::make('conditions')->constraints([
        TextFilter::make('customer')->hidden(),
        SelectFilter::make('status')->options(['paid' => 'Paid']),
    ]);
    $state = ['combinator' => 'and', 'rules' => [qbRule('customer', 'equals', 'Beta')]];

    expect(array_keys($filter->getConstraints()))->toBe(['status'])
        ->and($filter->getFirstConstraint()->getName())->toBe('status')
        ->and($filter->getConstraint('customer'))->toBeNull()
        ->and($filter->hasActiveRule($state))->toBeFalse()
        ->and($filter->getIndicator($state))->toBeNull()
        ->and($filter->apply(QbInvoice::query(), $state)->count())->toBe(4);
});
