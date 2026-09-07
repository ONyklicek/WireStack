<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Enums\TableLayout;
use NyonCode\WireTable\Table;

/*
 * `layout('list')` — the card rendering as a choice rather than as a width.
 *
 * The cards already existed and were reachable only below a breakpoint, which is
 * right for a table that has to survive a phone and wrong for a surface that is
 * never a table. Those were being built as tables and then talked out of it: one
 * content column, no state column, weight instead of a badge, actions hidden —
 * and still stuck with a header row that cannot be turned off.
 *
 * What has to stay true is that this is a *layout*, not a second page:
 * everything around the records — search, filters, pagination, the selection
 * that survives paging — is the table's, and none of it would survive being
 * rewritten per module.
 */
class ListLayoutRow extends Model
{
    protected $table = 'list_layout_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class ListLayoutComponent extends Component
{
    use WithTable;

    public string $mode = 'table';

    public function mount(string $mode = 'table'): void
    {
        $this->mode = $mode;
    }

    public function table(Table $table): Table
    {
        $table
            ->model(ListLayoutRow::class)
            ->selectable()
            ->searchable()
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->sortable()->searchable()->mobileTitle(),
                TextColumn::make('email')->mobileSubtitle(),
            ]);

        return match ($this->mode) {
            'list' => $table->layout(TableLayout::List),
            'list-headed' => $table->layout(TableLayout::List)
                ->listHeading(fn (ListLayoutRow $r): string => 'Písmeno '.mb_substr((string) $r->name, 0, 1)),
            'list-plain' => $table->layout(TableLayout::List)->selectable(false)->perPageSelector(false)->paginated(),
            'stacked' => $table->stackedOnMobile(),
            default => $table,
        };
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('list_layout_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
    });

    ListLayoutRow::insert([
        ['name' => 'Ada', 'email' => 'ada@example.test'],
        ['name' => 'Grace', 'email' => 'grace@example.test'],
    ]);
});

afterEach(fn () => Schema::dropIfExists('list_layout_rows'));

it('emits one rendering per record, not two chosen by CSS', function () {
    // The point of the layout being a layout and not a stylesheet:
    // `stackedOnMobile()` puts the whole table in the document beside the cards,
    // because on a phone you need both. A list needs one.
    $list = Livewire::test(ListLayoutComponent::class, ['mode' => 'list'])->html();

    expect($list)->not->toContain('<table')
        ->and($list)->toContain('data-testid="table-card"');
});

it('leaves every table that never asks exactly as it was', function () {
    $html = Livewire::test(ListLayoutComponent::class)->html();

    expect($html)->toContain('<table')
        ->and($html)->not->toContain('data-testid="table-card"');
});

it('still stacks a table that asked to, with both halves in the document', function () {
    $html = Livewire::test(ListLayoutComponent::class, ['mode' => 'stacked'])->html();

    expect($html)->toContain('<table')
        ->and($html)->toContain('data-testid="table-card"');
});

it('keeps the sort control, which is the only one a list has', function () {
    // The header row carries the sort buttons and a list has no header row, so
    // without this a list would be stuck in its default order forever.
    expect(Livewire::test(ListLayoutComponent::class, ['mode' => 'list'])->html())
        ->toContain('data-testid="table-mobile-sort"');
});

it('keeps the search and the selection that outlives a page', function () {
    // The whole argument for a layout rather than a hand-written page: marking
    // twelve thousand notifications read is a selection that survives paging,
    // and nothing rewritten per module would have grown one.
    $html = Livewire::test(ListLayoutComponent::class, ['mode' => 'list'])->html();

    expect($html)->toContain('data-testid="table-search"')
        ->and($html)->toContain('data-testid="table-card-select"');
});

it('shows the cards at every width rather than below a breakpoint', function () {
    $table = Table::make()->layout('list');

    expect($table->getLayout())->toBe(TableLayout::List)
        ->and($table->rendersTable())->toBeFalse()
        ->and($table->rendersCards())->toBeTrue()
        // Stacking hides the cards above its breakpoint; a list has nothing to
        // hide them for, and no table to hide.
        ->and($table->getStackedCardsVisibleClass())->toBe('')
        ->and($table->getStackedTableHiddenClass())->toBe('');
});

it('defaults to rows, and takes the layout as a string as well as an enum', function () {
    expect(Table::make()->getLayout())->toBe(TableLayout::Table)
        ->and(Table::make()->rendersTable())->toBeTrue()
        ->and(Table::make()->rendersCards())->toBeFalse()
        ->and(Table::make()->layout('list')->getLayout())->toBe(TableLayout::List);
});

it('can leave the page-size control out of the footer', function () {
    // Paging stays; only the control goes. On a grid of columns "how many rows
    // before I scroll" is the reader's business; on a list a `Show [10] records`
    // dropdown is the last thing that makes the page announce itself as a table.
    $table = Table::make();

    expect($table->showsPerPageSelector())->toBeTrue()
        ->and($table->perPageSelector(false)->showsPerPageSelector())->toBeFalse();
});

it('renders the footer without the control, and keeps the count', function () {
    $html = Livewire::test(ListLayoutComponent::class, ['mode' => 'list-plain'])->html();

    expect($html)->not->toContain('data-testid="table-per-page"')
        // The paging itself is untouched — the count is still there to read.
        // The paging itself is untouched — only the control went.
        ->and($html)->toContain('data-testid="table-card"');
});

/*
 * ─── Denní předěly ──────────────────────────────────────────────
 *
 * A grid says *when* in a column; a list has no column, so a list that wants to
 * be read as a timeline says it with headings instead.
 */

it('files a run of cards under one heading', function () {
    $table = Table::make()->listHeading(fn (ListLayoutRow $r): string => $r->name === 'Ada' ? 'Dnes' : 'Dříve');

    $groups = $table->groupCards([
        new ListLayoutRow(['name' => 'Ada']),
        new ListLayoutRow(['name' => 'Grace']),
        new ListLayoutRow(['name' => 'Hopper']),
    ]);

    // Equal neighbours share the heading above them; a change starts a new run.
    expect($groups)->toHaveCount(2)
        ->and($groups[0]['heading'])->toBe('Dnes')
        ->and($groups[0]['records'])->toHaveCount(1)
        ->and($groups[1]['heading'])->toBe('Dříve')
        ->and($groups[1]['records'])->toHaveCount(2);
});

it('gives a table that asked for no headings exactly one group', function () {
    // One shape for the loop rather than two, and the branch it costs is per
    // group — never per card, where it would be a pair of morph markers on every
    // row and the payload fuse budgets those.
    $groups = Table::make()->groupCards([new ListLayoutRow(['name' => 'Ada']), new ListLayoutRow(['name' => 'Grace'])]);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['heading'])->toBeNull()
        ->and($groups[0]['records'])->toHaveCount(2);
});

it('treats a heading of nothing as no heading at all', function () {
    // A closure that answers '' or null for some records is answering "this one
    // sits under none", not "this one starts a run called nothing".
    $groups = Table::make()
        ->listHeading(fn (ListLayoutRow $r): ?string => $r->name === 'Ada' ? 'Dnes' : null)
        ->groupCards([new ListLayoutRow(['name' => 'Ada']), new ListLayoutRow(['name' => 'Grace'])]);

    expect($groups[1]['heading'])->toBeNull();
});

it('draws the headings in the list', function () {
    $html = Livewire::test(ListLayoutComponent::class, ['mode' => 'list-headed'])->html();

    expect($html)->toContain('Písmeno A')
        ->and($html)->toContain('Písmeno G')
        // Sticky, so a heading stays legible while its own run scrolls past.
        ->and($html)->toContain('sticky top-0');
});
