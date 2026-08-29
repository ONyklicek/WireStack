<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Contracts\ExpandsTableRows;
use NyonCode\WireTable\Contracts\SummarisesTable;
use NyonCode\WireTable\Support\SubRowPanel;
use NyonCode\WireTable\Table;

/*
 * The numbers an expanded parent's child panel draws.
 *
 * Both renderings of that panel — the desktop table and the stacked card's list
 * — derived these inside their own `@php` block, from the same lines copied
 * across. Blade is where nobody asserts, so a copy that drifted stayed drifted:
 * the desktop half carried a *corrected* rule for "is a sub-row filter active"
 * while the canonical service still answered the old one, and every table with
 * one multi-select sub-row column silently lost its page-wide eager load.
 *
 * The host is a double implementing ExpandsTableRows + SummarisesTable, which is
 * what those contracts exist for: none of this was reachable without a Livewire
 * component before them.
 */
class SrpItem extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

/**
 * @param  array<string, mixed>  $answers
 */
function srpHost(array $answers = []): object
{
    return new class($answers) implements ExpandsTableRows, SummarisesTable
    {
        /** @param array<string, mixed> $answers */
        public function __construct(private array $answers) {}

        public function isRowExpanded(mixed $recordKey): bool
        {
            return true;
        }

        public function getSubRows(mixed $record): Collection
        {
            return collect();
        }

        public function isSubRowsShowAll(string|int $parentKey): bool
        {
            return $this->answers['showAll'] ?? false;
        }

        public function getSubRowsTotalCount(mixed $record): int
        {
            return $this->answers['total'] ?? 0;
        }

        public function getLoadedSubRowCount(mixed $record): ?int
        {
            return null;
        }

        /** @return array<string, mixed> */
        public function getSubRowFilterValues(): array
        {
            return $this->answers['filterValues'] ?? [];
        }

        public function hasActiveSubRowFilters(): bool
        {
            return $this->answers['hasActiveFilter'] ?? false;
        }

        public function tableHasSummaries(): bool
        {
            return true;
        }

        public function tableHasGroupSummaries(): bool
        {
            return false;
        }

        /** @return array<string, array<int, mixed>> */
        public function computeTableSummaries(string $scope = 'query', mixed $parentRecord = null, ?Collection $subRecords = null): array
        {
            return $this->answers['summaries'] ?? [];
        }

        /** @return array<string, array<int, mixed>> */
        public function computeGroupSummaries(mixed $groupValue): array
        {
            return [];
        }

        /** @return array<string, mixed> */
        public function computeSubRowGrandTotals(string $scope = 'query'): array
        {
            return [];
        }

        public function getSummaryScope(): string
        {
            return 'page';
        }

        /** @return array<string, string> */
        public function getSummaryScopeOptions(): array
        {
            return [];
        }
    };
}

function srpTable(): Table
{
    return Table::make()
        ->model(SrpItem::class)
        ->columns([Column::make('number')])
        ->subRows('items')
        ->subRowColumns([Column::make('product'), Column::make('amount')]);
}

/**
 * @param  array<string, mixed>  $answers
 * @param  int  $rendered  How many children are actually on screen.
 */
function srpPanel(Table $table, array $answers = [], int $rendered = 0): SubRowPanel
{
    $children = collect(range(1, max(0, $rendered)))
        ->take($rendered)
        ->map(fn (int $i) => new SrpItem(['id' => $i]));

    return SubRowPanel::for($table, srpHost($answers), new SrpItem(['id' => 99]), 99, $children);
}

// ─── "Show N more" ───────────────────────────────────────────────────────────

it('asks for the real total only when a limit is hiding children', function () {
    // No limit configured: the rendered set *is* the whole set, so the panel must
    // not spend getSubRowsTotalCount() (a COUNT, per open parent) to learn it.
    $panel = srpPanel(srpTable(), ['total' => 999], rendered: 3);

    expect($panel->total)->toBe(3)
        ->and($panel->remaining)->toBe(0);
});

it('counts what the limit is still hiding', function () {
    $panel = srpPanel(srpTable()->subRowsLimit(2), ['total' => 7], rendered: 2);

    expect($panel->total)->toBe(7)
        ->and($panel->remaining)->toBe(5);
});

it('stops asking once this parent has been expanded past the limit', function () {
    // "Show all" already handed back everything, so the affordance must go — and
    // the total it would count against is the set in hand.
    $panel = srpPanel(srpTable()->subRowsLimit(2), ['total' => 7, 'showAll' => true], rendered: 7);

    expect($panel->total)->toBe(7)
        ->and($panel->remaining)->toBe(0);
});

it('never offers a negative remainder', function () {
    // A limit raised between renders, or a show-all that outran the total taken
    // before it: "Show -2 more" is the visible half of that.
    $panel = srpPanel(srpTable()->subRowsLimit(2), ['total' => 1], rendered: 4);

    expect($panel->remaining)->toBe(0);
});

// ─── The colspan the panel spans ─────────────────────────────────────────────

it('spans the indent spacer as well as the columns', function () {
    // The empty-state message and every subtotal row are laid out on this: one
    // column short and they stop before the edge they are meant to reach.
    expect(srpPanel(srpTable())->columnCount)->toBe(3); // 2 columns + spacer
});

it('spans the overflow cell when the children carry actions', function () {
    $table = srpTable()->subRowActions([Action::make('edit')]);

    expect(srpPanel($table)->columnCount)->toBe(4)
        ->and(srpPanel($table)->hasActions)->toBeTrue();
});

it('spans only the columns this viewer may see', function () {
    $table = Table::make()
        ->model(SrpItem::class)
        ->columns([Column::make('number')])
        ->subRows('items')
        ->subRowColumns([
            Column::make('product'),
            Column::make('cost')->authorizeUsing(fn () => false),
        ]);

    $panel = srpPanel($table);

    expect($panel->columns)->toHaveCount(1)
        ->and($panel->columnCount)->toBe(2);
});

// ─── Subtotals ───────────────────────────────────────────────────────────────

it('is as tall as the column declaring the most subtotals', function () {
    $panel = srpPanel(srpTable(), [
        'summaries' => [
            // Tallest first, deliberately: a height taken from whichever column
            // came last would still read 2 here and lose a row the other way round.
            'amount' => [['label' => 'Sum', 'value' => 60], ['label' => 'Max', 'value' => 30]],
            'product' => [['label' => 'Count', 'value' => 3]],
        ],
    ], rendered: 3);

    // Two rows, not one: a shorter column simply has no cell on the lower row.
    expect($panel->summaryRowCount)->toBe(2)
        ->and($panel->showsSummaries)->toBeTrue();
});

it('draws no footer under an empty child set', function () {
    // Subtotals of nothing describe nothing — the "no children" message already
    // says it, and says it once.
    $panel = srpPanel(srpTable(), [
        'summaries' => ['amount' => [['label' => 'Sum', 'value' => 0]]],
    ], rendered: 0);

    expect($panel->showsSummaries)->toBeFalse();
});

it('draws no footer when no column declares a subtotal', function () {
    expect(srpPanel(srpTable(), [], rendered: 3)->showsSummaries)->toBeFalse();
});

// ─── The filter bar ──────────────────────────────────────────────────────────

it('shows no filter bar on a table that is not sub-row filterable', function () {
    expect(srpPanel(srpTable())->hasFilterBar)->toBeFalse();
});

it('keeps the filter bar even where every child column is hidden from this viewer', function () {
    // The bar is per *configured* column: a column hidden from this viewer takes
    // its own control away, it does not decide the bar exists.
    $table = Table::make()
        ->model(SrpItem::class)
        ->columns([Column::make('number')])
        ->subRows('items')
        ->subRowColumns([Column::make('product')->filterable()->authorizeUsing(fn () => false)])
        ->subRowsFilterable();

    $panel = srpPanel($table);

    expect($panel->hasFilterBar)->toBeTrue()
        ->and($panel->columns)->toBeEmpty();
});

it('reads "a filter is active" off the host, never off the slot contents', function () {
    // The rule lives in SubRowFilters, which knows the slots are seeded. A panel
    // that re-decided it from $filterValues is exactly the second copy this
    // class was extracted to delete.
    $table = srpTable()->subRowsFilterable();

    $seeded = srpPanel($table, ['filterValues' => ['product' => [], 'amount' => null]]);
    $chosen = srpPanel($table, ['filterValues' => ['product' => ['bolt']], 'hasActiveFilter' => true]);

    expect($seeded->hasActiveFilter)->toBeFalse()
        ->and($seeded->filterValues)->toBe(['product' => [], 'amount' => null])
        ->and($chosen->hasActiveFilter)->toBeTrue();
});
