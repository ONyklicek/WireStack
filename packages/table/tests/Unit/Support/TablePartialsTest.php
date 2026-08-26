<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Contracts\ExpandsTableRows;
use NyonCode\WireTable\Contracts\ShowsTableColumns;
use NyonCode\WireTable\Contracts\SummarisesTable;
use NyonCode\WireTable\Support\TablePartials;
use NyonCode\WireTable\Support\TableRenderPlan;
use NyonCode\WireTable\Table;

/*
 * Which partials a set of changed rows moves.
 *
 * RowPartialsTest already drives this through a Livewire host and asserts the
 * names that come out. What it cannot separate is *why* a name is absent: a
 * table with no summaries, a table not stacked on mobile and a bug all produce
 * the same silence. These ask the owner directly, one decision at a time.
 *
 * The markup itself is not asserted here — the renderers have their own tests,
 * and these closures are deliberately not called: a partial the host decides
 * not to send must cost nothing to have been offered.
 *
 * The host is a double implementing the three contracts the render layer names —
 * ShowsTableColumns, ExpandsTableRows and SummarisesTable. That is what those
 * contracts are for: before them the card branch could not be reached at all
 * without a Livewire component, which is what V2.1's DoD 2 records as unmet.
 */

class TpRow extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

/**
 * A real Table and a real plan — the constructor takes both by type, and
 * loosening that to accept a double would weaken the code to suit the test.
 * Only the host is a stand-in, because the two predicates read off it are the
 * decisions under test.
 *
 * @param  array<string, bool>  $traits
 */
function tpFor(array $traits = []): TablePartials
{
    $table = Table::make()->model(TpRow::class)->columns([TextColumn::make('name')]);

    if ($traits['stacked'] ?? false) {
        $table->stackedOnMobile();
    }

    $host = new class($traits) implements ExpandsTableRows, ShowsTableColumns, SummarisesTable
    {
        /** @param array<string, bool> $traits */
        public function __construct(private array $traits) {}

        public function isColumnVisible(string $column): bool
        {
            return true;
        }

        public function isRowExpanded(mixed $recordKey): bool
        {
            return false;
        }

        public function tableHasSummaries(): bool
        {
            return $this->traits['summaries'] ?? false;
        }

        public function tableHasGroupSummaries(): bool
        {
            return $this->traits['groupSummaries'] ?? false;
        }

        public function computeTableSummaries(string $scope = 'query', mixed $parentRecord = null, ?Collection $subRecords = null): array
        {
            return [];
        }

        public function computeGroupSummaries(mixed $groupValue): array
        {
            return [];
        }

        public function computeSubRowGrandTotals(string $scope = 'query'): array
        {
            return [];
        }

        public function getSummaryScope(): string
        {
            return 'query';
        }

        public function getSummaryScopeOptions(): array
        {
            return [];
        }
    };

    return TablePartials::for($table, $host, TableRenderPlan::build($table, $host, collect()));
}

function tpRecord(int $id): TpRow
{
    return new TpRow(['id' => $id, 'name' => 'r'.$id]);
}
it('offers nothing for an empty change set', function () {
    expect(tpFor(['summaries' => true, 'stacked' => true])->satellites([]))->toBe([]);
});

it('offers no card where the table is not stacked on mobile', function () {
    $partials = tpFor(['summaries' => true])->satellites([1 => tpRecord(1)]);

    expect($partials)->not->toHaveKey('card-1')
        ->and($partials)->toHaveKey('summary')
        // The mobile footer follows the same switch as the card.
        ->and($partials)->not->toHaveKey('summary-mobile');
});

it('offers no totals where the table shows none', function () {
    expect(tpFor()->satellites([1 => tpRecord(1)]))->toBe([]);
});

it('offers the mobile footer only alongside the desktop one', function () {
    // Both follow the same summaries switch; the mobile one additionally needs
    // the stacked layout, so a table with totals and no stacking gets one.
    $partials = tpFor(['summaries' => true])->satellites([1 => tpRecord(1)]);

    expect(array_keys($partials))->toBe(['summary']);
});

it('offers a card per changed record where the table is stacked', function () {
    // Unreachable before the host contracts: this line builds a CardRenderer,
    // which resolves a column plan, which asks the host which columns show.
    $partials = tpFor(['stacked' => true, 'summaries' => true])
        ->satellites([1 => tpRecord(1), 2 => tpRecord(2)]);

    expect($partials)->toHaveKeys(['card-1', 'card-2', 'summary', 'summary-mobile']);
});

it('offers closures, and does not call them', function () {
    // A partial the host drops must not have cost a render to offer.
    expect(tpFor(['stacked' => true])->satellites([1 => tpRecord(1)])['card-1'])->toBeCallable();
});
