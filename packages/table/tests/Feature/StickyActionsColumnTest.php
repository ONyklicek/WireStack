<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * The actions column pinned against the horizontal scroll.
 *
 * One `stickyActions()` call has to reach FIVE cells that are drawn by four
 * different files — the header, the column-filter row, the body row's compiled
 * skeleton, a group subtotal and the summary footer — and they only look like
 * one pinned pane if every one of them pins with the same offsets. A cell that
 * is missed does not fail visibly at rest; it fails as a gap in the pane the
 * first time somebody scrolls sideways, which is why the sweep below asks all
 * five in a single render.
 *
 * What Pest cannot see is whether the pane is actually opaque while scrolling,
 * and whether an action's dropdown still opens above it — the class strings can
 * all be right and the layering still wrong. That is
 * workbench/scripts/verify-sticky-actions.mjs.
 */

class StickyActionsUser extends Model
{
    protected $table = 'sticky_actions_users';

    protected $guarded = [];

    public $timestamps = false;
}

class StickyActionsHost extends Component
{
    use WithTable;

    public bool $sticky = true;

    public string $position = 'end';

    public function table(Table $table): Table
    {
        return $table
            ->model(StickyActionsUser::class)
            ->columns([
                TextColumn::make('name')->filterable(),
                TextColumn::make('role'),
                TextColumn::make('score')->summarizeSum('Total'),
            ])
            ->actions([Action::make('open')->label('Open')->action(fn () => null)])
            ->actionsPosition($this->position)
            ->stickyActions($this->sticky)
            ->groupBy('role')
            ->groupSummaries()
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('sticky_actions_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('role');
        $table->integer('score');
    });

    StickyActionsUser::create(['name' => 'Ada', 'role' => 'admin', 'score' => 3]);
    StickyActionsUser::create(['name' => 'Grace', 'role' => 'editor', 'score' => 5]);
});

afterEach(fn () => Schema::dropIfExists('sticky_actions_users'));

/** How many cells in this render pin themselves to the table's edge. */
function pinnedCellCount(string $html, string $edge = 'sticky right-0'): int
{
    return mb_substr_count($html, $edge);
}

it('pins every cell of the actions column, not only the row', function () {
    $html = Livewire::test(StickyActionsHost::class)->html();

    // Header, column-filter row, two records, one group subtotal per group, and
    // the summary footer. The count is asserted as a floor rather than exactly:
    // what matters is that no surface was missed, and a table that grows a row
    // must not fail this.
    expect(pinnedCellCount($html))->toBeGreaterThanOrEqual(7)
        // The opaque surface and the layer that inherits the row's colour back
        // on top of it, once per pinned cell.
        ->and(mb_substr_count($html, 'pointer-events-none absolute inset-0 bg-white dark:bg-gray-800'))
        ->toBe(pinnedCellCount($html));
});

it('pins the header one tier above the rows', function () {
    $html = Livewire::test(StickyActionsHost::class)->html();

    // The header cells (there are two: the label row and the column-filter row)
    // take z-10, the body and footer cells z-[1]. Both sit under the sticky
    // <thead>'s own stacking context.
    expect(mb_substr_count($html, 'sticky right-0 bg-inherit border-l border-gray-200 dark:border-gray-700 z-10'))->toBe(2)
        ->and(mb_substr_count($html, 'z-[1]'))->toBeGreaterThanOrEqual(5);
});

it('lets the header row inherit the thead colour so a pinned header has a backdrop', function () {
    // The header's colour sits on the <thead>; the inheritance chain a pinned
    // cell reads its backdrop from runs thead → tr → th, and stops dead at a
    // <tr> that declares nothing.
    expect(Livewire::test(StickyActionsHost::class)->html())
        ->toContain('<tr class="bg-inherit"');
});

it('pins to the left edge when the actions column sits at the start', function () {
    $html = Livewire::test(StickyActionsHost::class, ['position' => 'start'])->html();

    expect(pinnedCellCount($html, 'sticky left-0'))->toBeGreaterThanOrEqual(7)
        ->and($html)->toContain('border-r border-gray-200')
        ->and(pinnedCellCount($html))->toBe(0);
});

it('leaves the table alone when the column is not pinned', function () {
    $html = Livewire::test(StickyActionsHost::class, ['sticky' => false])->html();

    expect(pinnedCellCount($html))->toBe(0)
        ->and(pinnedCellCount($html, 'sticky left-0'))->toBe(0)
        ->and($html)->not->toContain('pointer-events-none absolute inset-0 bg-white dark:bg-gray-800')
        // The cell is the one it always was, buttons and all.
        ->and($html)->toContain('flex flex-wrap items-center gap-1');
});

it('keeps the pinned cell content above its own layers', function () {
    $html = Livewire::test(StickyActionsHost::class)->html();

    // The layers are absolutely positioned over the cell's background; anything
    // the cell shows has to be positioned too, or it is painted underneath them.
    expect($html)->toContain('<div class="relative flex flex-wrap items-center gap-1')
        ->and($html)->toContain('<span class="relative">');
});
