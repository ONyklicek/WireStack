<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * The expander column is a property of the page, not of the table.
 *
 * A table whose sub-rows are decided per record (subRowsHideWhenEmpty(),
 * subRowsVisible()) used to keep the column on a page where no record got any —
 * an empty strip down the left with a master chevron above it that opened
 * nothing. The page now decides it once, and the header, the filter cell, every
 * row, the footer, the group subtotals and every colspan read that one answer.
 */
class ExcOrder extends Model
{
    protected $table = 'exc_orders';

    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(ExcItem::class, 'order_id');
    }
}

class ExcItem extends Model
{
    protected $table = 'exc_items';

    protected $guarded = [];

    public $timestamps = false;
}

class ExcHost extends Component
{
    use WithTable;

    /** 'empty' — subRowsHideWhenEmpty(); 'kind' — subRowsVisible() on the kind column; 'none' — no per-record rule. */
    public string $rule = 'empty';

    public bool $grouped = false;

    public bool $partials = false;

    public function table(Table $table): Table
    {
        $table
            ->model(ExcOrder::class)
            ->paginated(false)
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('kind'),
                TextColumn::make('amount')->summarizeSum('Total'),
            ])
            ->subRows('items')
            ->subRowColumns([TextColumn::make('label')]);

        match ($this->rule) {
            'empty' => $table->subRowsHideWhenEmpty(),
            'kind' => $table->subRowsVisible(fn (ExcOrder $order) => $order->kind === 'kit'),
            default => null,
        };

        if ($this->grouped) {
            $table->groupBy('kind')->groupSummaries();
        }

        if ($this->partials) {
            $table->poll('5s')->rowPartials();
        }

        return $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

/** Cells in the header row, in the first body row and in the footer's totals row. */
function excCellCounts(string $html): array
{
    preg_match('/<thead.*?<\/tr>/s', $html, $head);
    preg_match('/<tr[^>]*data-row-key=.*?<\/tr>/s', $html, $row);
    preg_match('/<tfoot.*?<\/tfoot>/s', $html, $foot);
    preg_match_all('/<tr\b.*?<\/tr>/s', $foot[0] ?? '', $footRows);

    // The footer opens with a full-width scope switch; the totals row is the
    // one laid out per column.
    $totals = max(array_map(fn (string $tr) => substr_count($tr, '<td'), $footRows[0] ?: ['']));

    return [
        preg_match_all('/<th[\s>]/', $head[0] ?? ''),
        substr_count($row[0] ?? '', '<td'),
        $totals,
    ];
}

beforeEach(function () {
    Schema::create('exc_orders', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('kind');
        $t->integer('amount')->default(1);
        $t->timestamps();
    });
    Schema::create('exc_items', function (Blueprint $t) {
        $t->id();
        $t->foreignId('order_id');
        $t->string('label');
    });

    ExcOrder::create(['id' => 1, 'name' => 'First', 'kind' => 'plain']);
    ExcOrder::create(['id' => 2, 'name' => 'Second', 'kind' => 'plain']);
});

afterEach(function () {
    Schema::dropIfExists('exc_items');
    Schema::dropIfExists('exc_orders');
});

it('drops the column from a page where no record has children', function () {
    $html = Livewire::test(ExcHost::class)->html();

    [$head, $row, $foot] = excCellCounts($html);

    expect($html)->not->toContain('wire:key="exp-')
        ->not->toContain('data-testid="subrows-master-toggle"')
        // The view-menu switch stays: it is a baseline for pages that do have them.
        ->toContain('data-testid="subrows-expand-all-rows"')
        ->and($head)->toBe(3)
        ->and($row)->toBe(3)
        ->and($foot)->toBe(3);
});

it('keeps the column as soon as one record on the page has children', function () {
    ExcItem::create(['order_id' => 2, 'label' => 'Screw']);

    $html = Livewire::test(ExcHost::class)->html();

    [$head, $row, $foot] = excCellCounts($html);

    expect($html)->toContain('data-testid="subrows-master-toggle"')
        ->and(substr_count($html, 'wire:key="exp-'))->toBe(2)
        ->and(substr_count($html, 'data-testid="table-row-expand"'))->toBe(1)
        ->and($head)->toBe(4)
        ->and($row)->toBe(4)
        ->and($foot)->toBe(4);
});

it('decides it from a subRowsVisible() condition too', function () {
    expect(Livewire::test(ExcHost::class, ['rule' => 'kind'])->html())->not->toContain('wire:key="exp-');

    ExcOrder::find(1)->update(['kind' => 'kit']);

    expect(Livewire::test(ExcHost::class, ['rule' => 'kind'])->html())->toContain('wire:key="exp-');
});

it('keeps the column on every row of a table without a per-record rule', function () {
    $html = Livewire::test(ExcHost::class, ['rule' => 'none'])->html();

    expect(substr_count($html, 'wire:key="exp-'))->toBe(2);
});

it('spans the empty state across the header it sits under', function () {
    ExcOrder::query()->delete();

    $html = Livewire::test(ExcHost::class, ['rule' => 'none'])->html();

    preg_match('/<thead.*?<\/tr>/s', $html, $head);

    expect($html)->toContain('colspan="'.preg_match_all('/<th[\s>]/', $head[0]).'"')
        ->not->toContain('wire:key="exp-');
});

it('lines the group subtotals up with the rows when the column is dropped', function () {
    $html = Livewire::test(ExcHost::class, ['grouped' => true])->html();

    preg_match('/<thead.*?<\/tr>/s', $html, $head);
    preg_match('/<tr[^>]*class="bg-gray-50 dark:bg-gray-800\/40".*?<\/tr>/s', $html, $subtotal);

    expect($subtotal[0] ?? '')->not->toBe('')
        ->and(substr_count($subtotal[0], '<td'))->toBe(preg_match_all('/<th[\s>]/', $head[0]));
});

it('renders the region, not a row, when the changed row decides the column', function () {
    ExcOrder::find(1)->update(['kind' => 'kit']);

    $test = Livewire::test(ExcHost::class, ['rule' => 'kind', 'partials' => true]);
    $test->call('refreshTable');

    // Order 1 was the only kit: turning it plain takes the column with it, which
    // a row partial cannot say — the header and the other row keep theirs.
    ExcOrder::find(1)->update(['kind' => 'plain']);
    $test->call('refreshTable');

    $region = collect($test->effects['islandFragments'] ?? [])->flatten()->implode('');

    expect($test->effects['wirePartials'] ?? null)->toBeNull()
        ->and($region)->toContain('data-row-key')
        ->not->toContain('wire:key="exp-');
});

it('still answers with the row when another row keeps the column', function () {
    ExcOrder::query()->update(['kind' => 'kit']);

    $test = Livewire::test(ExcHost::class, ['rule' => 'kind', 'partials' => true]);
    $test->call('refreshTable');

    ExcOrder::find(1)->update(['kind' => 'plain']);
    $test->call('refreshTable');

    expect(array_keys($test->effects['wirePartials'] ?? []))->toContain('row-1')
        ->and($test->effects['islandFragments'] ?? null)->toBeNull();
});
