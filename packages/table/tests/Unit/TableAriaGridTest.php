<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * ARIA grid semantics (selection gestures rollout, step 26). A table the
 * keyboard drives row by row must also say so: how many rows there are in
 * total, where each row sits in that total, whether several can be selected,
 * and which ones are.
 */
class AriaGridRow extends Model
{
    protected $table = 'aria_grid_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class AriaGridComponent extends Component
{
    use WithTable;

    public string $mode = 'selectable';

    public function mount(string $mode = 'selectable'): void
    {
        $this->mode = $mode;
    }

    public function table(Table $table): Table
    {
        $table->model(AriaGridRow::class);

        match ($this->mode) {
            // 25 rows over pages of 10, so page 2 starts at row 11.
            'paged' => $table->selectable()->perPage(10)->columns([TextColumn::make('name')]),
            'column-filters' => $table->selectable()->paginated(false)
                ->columns([TextColumn::make('name')->filterable()]),
            'plain' => $table->paginated(false)->columns([TextColumn::make('name')]),
            default => $table->selectable()->paginated(false)->columns([TextColumn::make('name')]),
        };

        return $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('aria_grid_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
    });

    AriaGridRow::insert(array_map(fn (int $i) => ['name' => 'Row '.$i], range(1, 25)));
});

afterEach(fn () => Schema::dropIfExists('aria_grid_rows'));

it('declares the grid, its total row count and that several rows can be selected', function () {
    $html = Livewire::test(AriaGridComponent::class)->html();

    // 25 records + one header row.
    expect($html)->toContain('role="grid"')
        ->toContain('aria-rowcount="26"')
        ->toContain('aria-multiselectable="true"');
});

it('numbers the header row first and the body rows after it', function () {
    $html = Livewire::test(AriaGridComponent::class)->html();

    expect($html)->toContain('aria-rowindex="1"')   // header
        ->toContain('aria-rowindex="2"')            // first record
        ->toContain('aria-rowindex="26"')           // last of 25
        ->not->toContain('aria-rowindex="27"');
});

it('counts the column-filter row as a header row so the body indices do not slip', function () {
    $html = Livewire::test(AriaGridComponent::class, ['mode' => 'column-filters'])->html();

    // Two header rows now, so the first record is 3 and the last is 27.
    expect($html)->toContain('aria-rowcount="27"')
        ->toContain('aria-rowindex="2"')
        ->toContain('aria-rowindex="3"')
        ->toContain('aria-rowindex="27"');
});

it('keeps row indices counting through the whole result set, not the page', function () {
    // An ARIA row index is a position in the grid; restarting it at 1 on every
    // page would tell a screen-reader user they are back at the top.
    $page2 = Livewire::test(AriaGridComponent::class, ['mode' => 'paged'])
        ->set('paginators.page', 2)
        ->html();

    expect($page2)->toContain('aria-rowcount="26"')
        ->toContain('aria-rowindex="12"')   // first row of page 2 = header + 11
        ->toContain('aria-rowindex="21"')   // last row of page 2
        ->not->toContain('aria-rowindex="2"');
});

it('binds aria-selected instead of printing it', function () {
    $html = Livewire::test(AriaGridComponent::class)->html();

    // A printed value would snap back to the server's truth on the next morph,
    // leaving the row lying about whether it is selected.
    expect($html)->toContain(':aria-selected="isSelected(')
        ->not->toContain('aria-selected="false"');
});

it('renders an empty selection live region that is present from the first paint', function () {
    $html = Livewire::test(AriaGridComponent::class)->html();

    // Present, polite, atomic, silent — and NOT inside the bulk bar, which is
    // behind x-show (a hidden live region announces nothing, and it vanishes at
    // zero selected, so "selection cleared" would never be heard).
    expect($html)->toContain('data-testid="selection-live"')
        ->toContain('aria-live="polite"')
        ->toContain('aria-atomic="true"')
        ->toContain('x-text="announcement"');

    preg_match('/data-testid="selection-live"[^>]*>(.*?)<\/div>/s', $html, $m);

    expect(trim($m[1] ?? 'not found'))->toBe('');
});

it('hands the announcement sentences to the component from PHP', function () {
    $html = Livewire::test(AriaGridComponent::class)->html();

    // Whole sentences, because only the server can translate them; the counts
    // are substituted client-side, where the selection lives.
    expect($html)->toContain('announcements')
        ->toContain('of :total selected')
        ->toContain('Selection cleared');
});

it('adds no grid semantics to a table the keyboard does not drive', function () {
    $html = Livewire::test(AriaGridComponent::class, ['mode' => 'plain'])->html();

    expect($html)->not->toContain('role="grid"')
        ->not->toContain('aria-rowcount')
        ->not->toContain('aria-rowindex')
        ->not->toContain('aria-multiselectable')
        ->not->toContain('data-testid="selection-live"');
});
