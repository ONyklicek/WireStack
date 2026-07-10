<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\ToggleColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Table;

/**
 * Guards the stable user-level selectors (data-testid / accessible names) that
 * make the table mappable in Pest Browser Testing. If a refactor drops one of
 * these hooks, a browser test targeting it would silently start matching nothing
 * — so assert they render.
 */
class HooksRow extends Model
{
    protected $table = 'hooks_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class HooksComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(HooksRow::class)
            ->paginated(false)
            ->searchable()
            ->selectable()
            ->columns([
                TextColumn::make('name')->sortable()->filterable(),
                TextColumn::make('role')->filterAsSelect(['admin' => 'Admin']),
            ])
            ->actions([
                Action::make('edit')->label('Edit'),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('hooks_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('role')->nullable();
        $table->boolean('active')->default(false);
    });
    HooksRow::insert([['name' => 'Ann', 'role' => 'admin'], ['name' => 'Bob', 'role' => 'admin']]);
});

afterEach(fn () => Schema::dropIfExists('hooks_rows'));

it('renders stable user-level test hooks for the active parts', function () {
    $html = Livewire::test(HooksComponent::class)->html();

    expect($html)
        ->toContain('data-testid="table-search"')                 // search box
        ->toContain('data-testid="table-column-toggle"')          // column picker
        ->toContain('data-testid="table-sort-name"')              // sortable header
        ->toContain('data-testid="table-filter-name"')            // per-column filter cell
        ->toContain('data-testid="table-filter-role"')
        ->toContain('data-testid="table-row"')                    // each row
        ->toContain('data-row-key="1"')                           // row identity
        ->toContain('data-testid="table-cell-name"')              // a body cell
        ->toContain('data-testid="table-row-select"')             // selection checkbox
        ->toContain('data-testid="action-edit"');                 // row action
});

it('gives icon-friendly controls accessible names', function () {
    $html = Livewire::test(HooksComponent::class)->html();

    // Selection + column toggle + action carry an aria-label / role for AT and
    // role/label-based browser selectors.
    expect($html)
        ->toContain('aria-label="'.__('wire-table::messages.select_row').'"')
        ->toContain('aria-label="'.__('wire-table::messages.toggle_columns').'"')
        ->toContain('role="checkbox"');
});

// A richer table exercising pagination, table filters, bulk/header actions,
// an editable column and a right-click menu.
class HooksRichComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(HooksRow::class)
            ->paginated()->perPage(2)          // 4 rows / 2 = 2 pages
            ->selectable()
            ->columns([
                TextColumn::make('name'),
                ToggleColumn::make('active'),   // inline-editable cell
            ])
            ->filters([
                SelectFilter::make('role')->options(['admin' => 'Admin']),
            ])
            ->bulkActions([
                BulkAction::make('archive')->label('Archive'),
            ])
            ->headerActions([
                HeaderAction::make('create')->label('New'),
            ])
            ->rowContextMenu([
                Action::make('duplicate')->label('Duplicate'),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

it('exposes hooks for pagination, filters, bulk / header actions, editing and the menu', function () {
    HooksRow::insert([['name' => 'C', 'active' => 1], ['name' => 'D', 'active' => 0]]);

    $html = Livewire::test(HooksRichComponent::class)->html();

    expect($html)
        ->toContain('data-testid="table-select-all"')             // header select-all
        ->toContain('data-testid="table-filters-trigger"')        // table filter panel
        ->toContain('data-testid="table-per-page"')               // page-size selector
        ->toContain('data-testid="table-page-2"')                 // a numbered page
        ->toContain('data-testid="table-page-next"')              // next page
        ->toContain('data-testid="bulk-action-archive"')          // bulk action
        ->toContain('data-testid="header-action-create"')         // header action
        ->toContain('data-testid="table-editable-active"')        // inline-edit cell
        ->toContain('data-testid="menu-action-duplicate"');       // context-menu item
});
