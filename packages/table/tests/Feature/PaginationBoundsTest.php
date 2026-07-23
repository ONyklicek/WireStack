<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Table;

/*
 * A page number that no longer exists must never render as an empty table.
 * Deletes are only one way to get there — a shared ?page=5 URL, a filter that
 * shrinks the result set, or rows removed by somebody else since the page was
 * opened all strand the user the same way, so the clamp lives in the record
 * fetch rather than in the post-action hook.
 */
class PbPost extends Model
{
    protected $table = 'pb_posts';

    protected $guarded = [];

    public $timestamps = false;
}

class PbComponent extends Component
{
    use WithTable;

    public string $mode = 'standard';

    public function table(Table $table): Table
    {
        $table = $table
            ->model(PbPost::class)
            ->paginated(true)
            ->perPage(2)
            ->columns([TextColumn::make('title')->searchable()])
            ->filters([
                SelectFilter::make('kind')->options(['a' => 'A', 'b' => 'B']),
            ]);

        return match ($this->mode) {
            'simple' => $table->simplePagination(),
            'cursor' => $table->cursorPagination(),
            default => $table,
        };
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    Schema::create('pb_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('kind')->default('a');
    });

    // 6 rows, perPage 2 → 3 pages.
    for ($i = 1; $i <= 6; $i++) {
        PbPost::insert(['id' => $i, 'title' => 'T'.$i, 'kind' => $i > 4 ? 'b' : 'a']);
    }
});

afterEach(function () {
    Schema::dropIfExists('pb_posts');
});

it('re-anchors a deep-linked page that is past the end of the result set', function () {
    // ?page=5 with only 3 pages of data — a bookmark or a shared link.
    $c = Livewire::withQueryParams(['page' => 5])->test(PbComponent::class);

    $records = $c->instance()->getTableRecords();

    expect($c->instance()->getPage())->toBe(3)
        ->and($records->currentPage())->toBe(3)
        ->and($records->count())->toBe(2);
});

it('re-anchors when rows disappear underneath the current page', function () {
    $c = Livewire::test(PbComponent::class)->call('gotoPage', 3);

    expect($c->instance()->getTableRecords()->count())->toBe(2);

    // Somebody else deletes the tail; the next roundtrip must not show an
    // empty page 3.
    PbPost::whereIn('id', [5, 6])->delete();

    $c->call('$refresh');

    expect($c->instance()->getPage())->toBe(2)
        ->and($c->instance()->getTableRecords()->count())->toBe(2);
});

it('lands on a populated page when a filter shrinks the result set', function () {
    $c = Livewire::test(PbComponent::class)->call('gotoPage', 3);

    // Filtering resets to page 1, so force the out-of-range page back on
    // afterwards to isolate the clamp itself.
    $c->set('tableState.filters.kind.value', 'b')->call('gotoPage', 3);

    expect($c->instance()->getPage())->toBe(1)
        ->and($c->instance()->getTableRecords()->count())->toBe(2);
});

it('leaves page 1 alone when the table is genuinely empty', function () {
    PbPost::query()->delete();

    $c = Livewire::test(PbComponent::class);

    expect($c->instance()->getPage())->toBe(1)
        ->and($c->instance()->getTableRecords()->count())->toBe(0);
});

it('leaves an in-range page untouched', function () {
    $c = Livewire::test(PbComponent::class)->call('gotoPage', 2);

    expect($c->instance()->getPage())->toBe(2)
        ->and($c->instance()->getTableRecords()->currentPage())->toBe(2);
});

it('does not clamp simple pagination, which has no last page to compute', function () {
    $c = Livewire::test(PbComponent::class, ['mode' => 'simple'])->call('gotoPage', 5);

    expect($c->instance()->getPage())->toBe(5)
        ->and($c->instance()->getTableRecords()->count())->toBe(0);
});

it('does not clamp cursor pagination', function () {
    $c = Livewire::test(PbComponent::class, ['mode' => 'cursor'])->call('gotoPage', 5);

    expect($c->instance()->getPage())->toBe(5);
});

it('keeps clampPageToBounds() working as the explicit post-mutation hook', function () {
    $c = Livewire::test(PbComponent::class)->call('gotoPage', 3);

    PbPost::whereIn('id', [5, 6])->delete();
    $c->instance()->invalidateTable();
    $c->instance()->clampPageToBounds();

    expect($c->instance()->getPage())->toBe(2);
});
