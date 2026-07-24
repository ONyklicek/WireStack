<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Table;

class SelRow extends Model
{
    protected $table = 'sel_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class SelComponent extends Component
{
    use WithTable;

    public int $perPage = 2;

    public ?int $bulkMax = null;

    /** @var array<int, int> */
    public static array $touched = [];

    public function table(Table $table): Table
    {
        $table
            ->model(SelRow::class)
            ->columns([TextColumn::make('name'), TextColumn::make('status')])
            ->filters([SelectFilter::make('status')->options(['open' => 'Open', 'done' => 'Done'])])
            ->selectable()
            ->paginated()
            ->perPage($this->perPage)
            ->bulkActions([
                BulkAction::make('touch')->label('Touch')->action(function ($records) {
                    self::$touched = $records->pluck('id')->all();
                }),
            ]);

        if ($this->bulkMax !== null) {
            $table->bulkMaxRecords($this->bulkMax);
        }

        return $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function selComponent(int $perPage = 2, ?int $bulkMax = null): SelComponent
{
    $component = new SelComponent;
    $component->perPage = $perPage;
    $component->bulkMax = $bulkMax;
    $component->mountWithTable();

    return $component;
}

beforeEach(function () {
    SelComponent::$touched = [];

    Schema::create('sel_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('status');
    });

    foreach (range(1, 6) as $i) {
        SelRow::create(['name' => "Row {$i}", 'status' => $i <= 4 ? 'open' : 'done']);
    }
});

afterEach(function () {
    Schema::dropIfExists('sel_rows');
});

// ─── Keys mode ───────────────────────────────────────────────────────────────

it('toggles a single record in and out of the selection', function () {
    $c = selComponent();

    $c->toggleRecordSelection('1');
    expect($c->isRecordSelected('1'))->toBeTrue()
        ->and($c->getSelectedRecordsCount())->toBe(1);

    $c->toggleRecordSelection('1');
    expect($c->isRecordSelected('1'))->toBeFalse()
        ->and($c->getSelectedRecordsCount())->toBe(0);
});

it('unions the page into the selection instead of replacing it', function () {
    // Driven through Livewire because paging needs its page resolver; the old
    // behaviour overwrote the set, so paging silently discarded work.
    $test = Livewire::test(SelComponent::class)
        ->call('selectAllRecords');

    expect($test->instance()->getSelectedRecordKeys())->toBe(['1', '2']);

    $test->call('setPage', 2)->call('selectAllRecords');

    expect($test->instance()->getSelectedRecordKeys())->toBe(['1', '2', '3', '4'])
        ->and($test->instance()->getSelectedRecordsCount())->toBe(4);
});

it('removes only the current page when deselecting a page', function () {
    $test = Livewire::test(SelComponent::class)
        ->call('selectAllRecords')
        ->call('setPage', 2)
        ->call('selectAllRecords')
        ->call('deselectPageRecords');

    expect($test->instance()->getSelectedRecordKeys())->toBe(['1', '2']);
});

it('reports a partial page selection as indeterminate', function () {
    $c = selComponent();
    $c->toggleRecordSelection('1');

    expect($c->areAllVisibleSelected())->toBeFalse()
        ->and($c->areSomeVisibleSelected())->toBeTrue();

    $c->toggleRecordSelection('2');

    expect($c->areAllVisibleSelected())->toBeTrue()
        ->and($c->areSomeVisibleSelected())->toBeFalse();
});

it('treats an empty page as nothing selected', function () {
    $c = selComponent();
    SelRow::query()->delete();
    $c->invalidateTable();

    expect($c->areAllVisibleSelected())->toBeFalse()
        ->and($c->areSomeVisibleSelected())->toBeFalse();
});

// ─── All-matching mode ───────────────────────────────────────────────────────

it('selects every matching record as a mode, not a key list', function () {
    $c = selComponent();
    $c->selectAllMatchingRecords();

    expect($c->selectsAllMatching())->toBeTrue()
        // The point of the mode: no key list is stored, whatever the row count.
        ->and($c->tableState->get('selection.records'))->toBe([])
        ->and($c->getSelectedRecordsCount())->toBe(6)
        ->and($c->isRecordSelected('5'))->toBeTrue();
});

it('counts only the rows the active filter matches', function () {
    $c = selComponent();
    $c->tableState->set('filters', ['status' => ['value' => 'open']]);
    $c->selectAllMatchingRecords();

    expect($c->getSelectedRecordsCount())->toBe(4);
});

it('holds exclusions rather than the selection in all mode', function () {
    $c = selComponent();
    $c->selectAllMatchingRecords();
    $c->toggleRecordSelection('3');

    expect($c->isRecordSelected('3'))->toBeFalse()
        ->and($c->isRecordSelected('4'))->toBeTrue()
        ->and($c->tableState->get('selection.records'))->toBe(['3'])
        ->and($c->getSelectedRecordsCount())->toBe(5);
});

it('never reports a negative count when exclusions outnumber the matches', function () {
    $c = selComponent();
    $c->selectAllMatchingRecords();
    foreach (range(1, 6) as $i) {
        $c->toggleRecordSelection((string) $i);
    }
    $c->toggleRecordSelection('99'); // a key no longer in the result set

    expect($c->getSelectedRecordsCount())->toBe(0);
});

it('narrows all-matching back to the current page', function () {
    $c = selComponent();
    $c->selectAllMatchingRecords();
    $c->selectOnlyPageRecords();

    expect($c->selectsAllMatching())->toBeFalse()
        ->and($c->getSelectedRecordKeys())->toBe(['1', '2']);
});

it('hands back no keys in all mode, since the selection is a query', function () {
    $c = selComponent();
    $c->selectAllMatchingRecords();

    expect($c->getSelectedRecordKeys())->toBe([]);
});

// ─── The scope reset that keeps "everything" honest ──────────────────────────

it('drops all-matching when a filter changes', function () {
    $c = selComponent();
    $c->selectAllMatchingRecords();

    $c->updatedTableState(['status' => ['value' => 'done']], 'filters.status.value');

    expect($c->selectsAllMatching())->toBeFalse()
        ->and($c->getSelectedRecordsCount())->toBe(0);
});

it('drops all-matching when the search changes', function () {
    $c = selComponent();
    $c->selectAllMatchingRecords();

    $c->updatedTableState('row 1', 'search');

    expect($c->selectsAllMatching())->toBeFalse();
});

it('keeps all-matching across a sort change, which does not change the set', function () {
    $c = selComponent();
    $c->selectAllMatchingRecords();

    $c->updatedTableState('name', 'sort.column');

    expect($c->selectsAllMatching())->toBeTrue()
        ->and($c->getSelectedRecordsCount())->toBe(6);
});

it('keeps all-matching across pagination, which does not change the set', function () {
    $c = selComponent();
    $c->selectAllMatchingRecords();

    $c->setPage(2);

    expect($c->selectsAllMatching())->toBeTrue()
        ->and($c->getSelectedRecordsCount())->toBe(6);
});

it('returns to keys mode when everything is deselected', function () {
    $c = selComponent();
    $c->selectAllMatchingRecords();
    $c->deselectAllRecords();

    expect($c->selectsAllMatching())->toBeFalse()
        ->and($c->getSelectedRecordsCount())->toBe(0);
});

it('turns an all-matching selection into just this page when the page is selected', function () {
    $c = selComponent();
    $c->selectAllMatchingRecords();

    // From "everything", selecting the page has to mean *only* the page —
    // carrying the exclusions across would leave a nonsense set behind.
    $c->selectAllRecords();

    expect($c->selectsAllMatching())->toBeFalse()
        ->and($c->getSelectedRecordKeys())->toBe(['1', '2']);
});

it('clears the selection when a page is deselected out of all-matching', function () {
    $c = selComponent();
    $c->selectAllMatchingRecords();

    $c->deselectPageRecords();

    expect($c->selectsAllMatching())->toBeFalse()
        ->and($c->getSelectedRecordKeys())->toBe([]);
});

it('refuses an oversized selection through the form-data path too', function () {
    Livewire::test(SelComponent::class, ['bulkMax' => 2])
        ->call('selectAllMatchingRecords')
        ->call('executeBulkActionWithData', 'touch', []);

    expect(SelComponent::$touched)->toBe([]);
});

it('runs the form-data path over an all-matching selection', function () {
    Livewire::test(SelComponent::class)
        ->call('selectAllMatchingRecords')
        ->call('executeBulkActionWithData', 'touch', []);

    expect(SelComponent::$touched)->toBe([1, 2, 3, 4, 5, 6]);
});

// ─── The selection as a query ────────────────────────────────────────────────

it('resolves keyed selections through a whereIn', function () {
    $c = selComponent();
    $c->toggleRecordSelection('2');
    $c->toggleRecordSelection('5');

    expect($c->selectedRecordsQuery()->pluck('id')->all())->toBe([2, 5]);
});

it('resolves all-matching through the filtered query minus exclusions', function () {
    $c = selComponent();
    $c->tableState->set('filters', ['status' => ['value' => 'open']]);
    $c->selectAllMatchingRecords();
    $c->toggleRecordSelection('2');

    expect($c->selectedRecordsQuery()->pluck('id')->all())->toBe([1, 3, 4]);
});

it('walks the selection in chunks without materialising it', function () {
    $c = selComponent();
    $c->selectAllMatchingRecords();

    $seen = [];
    $c->eachSelectedRecord(function (SelRow $row) use (&$seen) {
        $seen[] = $row->id;
    }, chunk: 2);

    expect($seen)->toBe([1, 2, 3, 4, 5, 6]);
});

it('walks nothing when nothing is selected', function () {
    $c = selComponent();

    $seen = 0;
    $c->eachSelectedRecord(function () use (&$seen) {
        $seen++;
    });

    expect($seen)->toBe(0);
});

// ─── The cap that keeps one request survivable ───────────────────────────────

it('refuses to materialise a selection over the cap', function () {
    $c = selComponent(bulkMax: 3);
    $c->selectAllMatchingRecords();

    expect($c->getSelectedRecordsCount())->toBe(6)
        ->and($c->hasTooManySelectedRecords())->toBeTrue()
        // Empty rather than truncated: a half-set is worse than a refusal.
        ->and($c->getSelectedRecords())->toHaveCount(0);
});

it('materialises a selection inside the cap', function () {
    $c = selComponent(bulkMax: 10);
    $c->selectAllMatchingRecords();

    expect($c->hasTooManySelectedRecords())->toBeFalse()
        ->and($c->getSelectedRecords())->toHaveCount(6);
});

it('lifts the cap when it is set to null', function () {
    $c = selComponent(bulkMax: null);
    $c->getTable()->bulkMaxRecords(null);
    $c->selectAllMatchingRecords();

    expect($c->hasTooManySelectedRecords())->toBeFalse();
});

// ─── Bulk actions over each mode ─────────────────────────────────────────────

it('runs a bulk action over every matching record', function () {
    $test = Livewire::test(SelComponent::class)
        ->call('selectAllMatchingRecords')
        ->call('executeBulkAction', 'touch');

    expect(SelComponent::$touched)->toBe([1, 2, 3, 4, 5, 6]);
    $test->assertOk();
});

it('runs a bulk action over the filtered set only', function () {
    Livewire::test(SelComponent::class)
        ->set('tableState.filters', ['status' => ['value' => 'done']])
        ->call('selectAllMatchingRecords')
        ->call('executeBulkAction', 'touch');

    expect(SelComponent::$touched)->toBe([5, 6]);
});

it('refuses a bulk action over an oversized selection instead of truncating it', function () {
    Livewire::test(SelComponent::class, ['bulkMax' => 2])
        ->call('selectAllMatchingRecords')
        ->call('executeBulkAction', 'touch');

    expect(SelComponent::$touched)->toBe([]);
});

it('does nothing when a bulk action runs with an empty selection', function () {
    Livewire::test(SelComponent::class)->call('executeBulkAction', 'touch');

    expect(SelComponent::$touched)->toBe([]);
});

// ─── Query cost ──────────────────────────────────────────────────────────────

it('counts the matching set once per request', function () {
    $c = selComponent();
    $c->selectAllMatchingRecords();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $c->getSelectedRecordsCount();
    $c->getSelectedRecordsCount();
    $c->getSelectedRecordsCount();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(1);
});

it('costs no query at all while the selection is keyed', function () {
    $c = selComponent();
    $c->toggleRecordSelection('1');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $c->getSelectedRecordsCount();
    $c->isRecordSelected('1');

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(0);
});

// ─── All-matching over a joined query (ambiguous-column regression) ───────────

class SelJoinUser extends Model
{
    protected $table = 'sel_join_users';

    protected $guarded = [];

    public $timestamps = false;

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(SelJoinTag::class, 'sel_join_tag_user', 'user_id', 'tag_id');
    }
}

class SelJoinTag extends Model
{
    protected $table = 'sel_join_tags';

    protected $guarded = [];

    public $timestamps = false;
}

class SelJoinComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        // Base query is itself a join (the pivot table carries its own `id`), so
        // every all-matching clause must qualify the primary key or the SQL is
        // ambiguous.
        return $table
            ->query(SelJoinUser::first()->tags()->getQuery()->select('sel_join_tags.*'))
            ->columns([TextColumn::make('name')])
            ->selectable()
            ->paginated()
            ->perPage(2);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

it('excludes rows from an all-matching selection over a joined query without ambiguity', function () {
    Schema::create('sel_join_users', function (Blueprint $table) {
        $table->id();
    });
    Schema::create('sel_join_tags', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    Schema::create('sel_join_tag_user', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('tag_id');
    });

    $user = SelJoinUser::create([]);
    $alpha = SelJoinTag::create(['name' => 'Alpha']);
    $beta = SelJoinTag::create(['name' => 'Beta']);
    $user->tags()->attach([$alpha->id, $beta->id]);

    $c = new SelJoinComponent;
    $c->mountWithTable();

    $c->selectAllMatchingRecords();
    // Exclude Beta; the query gains whereNotIn on the primary key over the join.
    $c->toggleRecordSelection((string) $beta->id);

    // Before the fix this threw "ambiguous column name: id" (pivot + tags both
    // have `id`). It must run and return only the still-selected Alpha row.
    $rows = $c->selectedRecordsQuery()->get();

    expect($rows->pluck('id')->all())->toBe([$alpha->id]);

    Schema::dropIfExists('sel_join_tag_user');
    Schema::dropIfExists('sel_join_tags');
    Schema::dropIfExists('sel_join_users');
});
