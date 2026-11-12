<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Tests\Unit\Filters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Modals\Support\ModalStyle;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Enums\ColumnFilterLayout;
use NyonCode\WireTable\Enums\FilterOperator;
use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TextFilter;
use NyonCode\WireTable\Table;

class FecItem extends Model
{
    protected $table = 'fec_items';

    protected $guarded = [];
}

class FecTable extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(FecItem::class)
            ->columns([TextColumn::make('name'), TextColumn::make('kind')])
            ->filters([
                TextFilter::make('name')->operators(),
                SelectFilter::make('kind')->options(['a' => 'A', 'b' => 'B'])->withCounts(),
            ])
            ->paginated(false);
    }

    public function render(): string
    {
        return '<div>{{ $this->table }}</div>';
    }
}

beforeEach(function () {
    Schema::create('fec_items', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('kind');
        $table->date('due_on')->nullable();
        $table->timestamps();
    });

    FecItem::create(['name' => 'One', 'kind' => 'a', 'due_on' => '2026-01-01']);
    FecItem::create(['name' => 'Two', 'kind' => 'a']);
    FecItem::create(['name' => 'Three', 'kind' => 'b']);
});

afterEach(function () {
    Schema::dropIfExists('fec_items');
});

it('counts a panel select filter under the other panel filters, not under its own', function () {
    Livewire::test(FecTable::class)
        ->assertSeeHtml('A (2)')
        ->set('tableState.filters.name', ['value' => 'Tw', 'operator' => 'starts_with'])
        ->set('tableState.filters.kind', ['value' => 'b'])
        ->assertSeeHtml('A (1)')
        ->assertSeeHtml('B (0)');
});

it('puts a removed panel filter with a picker back on its default comparison', function () {
    $component = Livewire::test(FecTable::class)
        ->set('tableState.filters.name', ['value' => 'One', 'operator' => 'equals'])
        ->call('removeTableFilter', 'name');

    expect($component->get('tableState.filters.name'))->toBe(['value' => null, 'operator' => 'contains']);
});

it('offers no operators on a date range, and ignores a non-text value for a single date', function () {
    $filter = DateFilter::make('due_on')->operators();
    $copy = $filter->forState(['operator' => 'lt']);

    expect(DateFilter::make('d')->range()->supportedOperators())->toBe([])
        ->and(DateFilter::make('d')->month()->supportedOperators())->toBe([])
        ->and($copy->getActiveOperator())->toBe(FilterOperator::LessThan)
        ->and($copy->apply(FecItem::query(), ['x'])->count())->toBe(3)
        ->and($copy->apply(FecItem::query(), '2026-06-01')->count())->toBe(1)
        ->and($filter->forState(['operator' => 'is_not_empty'])->apply(FecItem::query(), true)->count())->toBe(1);
});

it('takes the column filter layout as the enum too', function () {
    expect(Table::make()->columnFilterLayout(ColumnFilterLayout::Popover)->getColumnFilterLayout())->toBe(ColumnFilterLayout::Popover)
        ->and(Table::make()->columnFilterLayout('row')->getColumnFilterLayout())->toBe(ColumnFilterLayout::Row);
});

it('keeps a large-breakpoint sheet footer above the home indicator', function () {
    expect((new ModalStyle(slideOverOnMobile: true, breakpoint: 'lg'))->footerSafeAreaClass())
        ->toBe('max-lg:pb-[calc(1rem_+_env(safe-area-inset-bottom))]');
});

it('answers the cell menu on a single-choice select with "is any of", and on text only with an exact match', function () {
    $select = SelectFilter::make('kind')->options(['a' => 'A'])->operators();

    expect($select->stateForCellValue('a'))->toBe(['value' => 'a', 'operator' => 'in'])
        ->and($select->stateForCellValue('a', exclude: true))->toBe(['value' => 'a', 'operator' => 'not_in'])
        ->and($select->canFilterByCellValue())->toBeTrue()
        ->and(TextFilter::make('t')->operators(['contains', 'starts_with'])->canFilterByCellValue())->toBeFalse()
        ->and(TextFilter::make('t')->canFilterByCellValue())->toBeTrue();
});

it('drops a picker the filter no longer supports, whatever the call order', function () {
    expect(DateFilter::make('d')->operators()->range()->hasOperatorPicker())->toBeFalse()
        ->and(DateFilter::make('d')->operators()->presets()->getOperators())->toBe([]);
});
