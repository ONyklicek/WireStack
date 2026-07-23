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

class MobRow extends Model
{
    protected $table = 'mob_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class MobComponent extends Component
{
    use WithTable;

    public bool $stacked = true;

    public bool $selectable = true;

    public bool $sortable = true;

    public bool $paginate = true;

    public bool $withActions = false;

    public bool $collapseActions = false;

    public bool $withSummary = true;

    public function table(Table $table): Table
    {
        $amount = TextColumn::make('amount')->alignRight();

        if ($this->withSummary) {
            $amount->summarizeSum('Total');
        }

        $table
            ->model(MobRow::class)
            ->columns([
                TextColumn::make('name')->sortable($this->sortable),
                TextColumn::make('note'),
                $amount,
            ])
            ->stackedOnMobile($this->stacked)
            ->selectable($this->selectable)
            ->sortable($this->sortable)
            ->paginated($this->paginate)
            ->perPage(2);

        if ($this->withActions) {
            $table->actions([
                Action::make('edit')->label('Edit row'),
                Action::make('archive')->label('Archive row'),
            ]);
        }

        if ($this->collapseActions) {
            $table->collapseActionsOnMobile(threshold: 1);
        }

        return $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('mob_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('note');
        $table->integer('amount');
    });

    foreach (range(1, 5) as $i) {
        MobRow::create(['name' => "Row {$i}", 'note' => "Note {$i}", 'amount' => $i * 10]);
    }
});

afterEach(function () {
    Schema::dropIfExists('mob_rows');
});

// ─── Select-all on the card view (layer D) ───────────────────────────────────

it('renders an always-visible select-all strip above the cards', function () {
    Livewire::test(MobComponent::class)
        ->assertSee('table-card-select-all', escape: false)
        ->assertSee(__('wire-table::messages.select_all'));
});

it('renders no strip when the table is not selectable', function () {
    Livewire::test(MobComponent::class, ['selectable' => false])
        ->assertDontSee('table-card-select-all', escape: false);
});

it('renders no strip when the table does not stack', function () {
    Livewire::test(MobComponent::class, ['stacked' => false])
        ->assertDontSee('table-card-select-all', escape: false);
});

it('drives the strip from the same tri-state as the desktop header checkbox', function () {
    // Both read allSelected / someSelected, so the two views can never disagree.
    $html = Livewire::test(MobComponent::class)->html();

    expect($html)->toContain('allSelected ? \'true\' : (someSelected ? \'mixed\' : \'false\')');
});

// ─── The scope line (layer D) ────────────────────────────────────────────────

it('offers the whole filtered set once more rows exist than the page shows', function () {
    Livewire::test(MobComponent::class)
        ->assertSee('table-selection-scope', escape: false)
        ->assertSee('table-select-all-matching', escape: false)
        ->assertSee(__('wire-table::messages.selection_select_all_matching', ['count' => 5]));
});

it('omits the scope line when everything already fits on one page', function () {
    Livewire::test(MobComponent::class, ['paginate' => false])
        ->assertDontSee('table-selection-scope', escape: false);
});

it('offers the way back from an all-matching selection', function () {
    Livewire::test(MobComponent::class)
        ->assertSee('table-select-only-page', escape: false)
        ->assertSee(__('wire-table::messages.selection_only_this_page'));
});

// ─── Mobile sort (layer C) ───────────────────────────────────────────────────

it('renders a sort control for the card view, which has no sortable header row', function () {
    Livewire::test(MobComponent::class)
        ->assertSee('table-mobile-sort', escape: false)
        ->assertSee(__('wire-table::messages.sort_by'));
});

it('lists only the sortable columns', function () {
    Livewire::test(MobComponent::class)
        ->assertSee('table-mobile-sort-name', escape: false)
        ->assertDontSee('table-mobile-sort-note', escape: false);
});

it('names the active sort on the trigger', function () {
    Livewire::test(MobComponent::class)
        ->call('sortTable', 'name')
        ->assertSee('aria-pressed="true"', escape: false);
});

it('renders no sort control when the table does not stack', function () {
    Livewire::test(MobComponent::class, ['stacked' => false])
        ->assertDontSee('table-mobile-sort', escape: false);
});

it('renders no sort control when nothing is sortable', function () {
    Livewire::test(MobComponent::class, ['sortable' => false])
        ->assertDontSee('table-mobile-sort', escape: false);
});

// ─── Card actions keep the identity readable ─────────────────────────────────

it('puts labelled actions on their own row, not beside the title', function () {
    // Two labelled buttons sharing the title line take the width the identity
    // needs, and min-w-0 then collapses the name to nothing.
    Livewire::test(MobComponent::class, ['withActions' => true])
        ->assertSee('table-card-actions', escape: false)
        ->assertSee('Edit row');
});

it('keeps a collapsed action group beside the title, where one icon fits', function () {
    Livewire::test(MobComponent::class, ['withActions' => true, 'collapseActions' => true])
        ->assertDontSee('table-card-actions', escape: false);
});

// ─── Totals on the card view (layer E) ───────────────────────────────────────

it('renders totals under the cards, which the hidden table footer never could', function () {
    Livewire::test(MobComponent::class)
        ->assertSee('table-card-summary', escape: false)
        ->assertSee('Total')
        // 10+20+30+40+50 over the whole filtered set, not just the page of 2.
        ->assertSee('150');
});

it('offers the same scope toggle as the desktop footer', function () {
    Livewire::test(MobComponent::class)
        ->assertSee('card-summary-scope-query', escape: false)
        ->assertSee('card-summary-scope-page', escape: false)
        ->assertSee(__('wire-table::messages.summary_scope_label'));
});

it('follows the scope toggle to the page', function () {
    // Regression: the page scope handed a paginator to computeSummaries(), which
    // takes a Collection — a TypeError on every paginated table, desktop included.
    Livewire::test(MobComponent::class)
        ->call('setSummaryScope', 'page')
        // Page of two rows: 10 + 20.
        ->assertSee('30');
});

it('computes a page-scoped summary on a paginated table without unwrapping by hand', function () {
    $component = new MobComponent;
    $component->mountWithTable();
    $component->setSummaryScope('page');

    $summaries = $component->computeTableSummaries('page');

    expect((string) $summaries['amount'][0]['value'])->toContain('30');
});

it('renders no totals block when no column has a summary', function () {
    Livewire::test(MobComponent::class, ['withSummary' => false])
        ->assertDontSee('table-card-summary', escape: false);
});
