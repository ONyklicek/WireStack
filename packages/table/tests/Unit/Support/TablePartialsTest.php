<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\TextColumn;
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
 * **What is missing, and why.** The branches that build a renderer — the card,
 * and the group subtotals — are not here. CardRenderer resolves a column plan,
 * which asks the host for isColumnVisible(), and there is no host contract to
 * satisfy with a double. That is the same gap V2.1's DoD 2 records against the
 * render branch: the collaborators are testable without Livewire only up to the
 * point where one needs the component. Those branches stay covered end to end
 * by RowPartialsTest, through a real host.
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

    $host = new class($traits)
    {
        /** @param array<string, bool> $traits */
        public function __construct(private array $traits) {}

        public function tableHasSummaries(): bool
        {
            return $this->traits['summaries'] ?? false;
        }

        public function tableHasGroupSummaries(): bool
        {
            return $this->traits['groupSummaries'] ?? false;
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
