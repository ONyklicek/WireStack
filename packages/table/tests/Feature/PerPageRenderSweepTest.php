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

class PerPageSweepPost extends Model
{
    protected $table = 'per_page_sweep_posts';

    protected $guarded = [];
}

class PerPageSweepHost extends Component
{
    use WithTable;

    public bool $cached = false;

    public bool $poll = false;

    public bool $queryString = false;

    public bool $lazyTable = false;

    public bool $editable = false;

    public bool $selectable = false;

    public string $mode = 'default';

    public function table(Table $table): Table
    {
        $t = $table
            ->model(PerPageSweepPost::class)
            ->paginated()
            ->perPage(2)
            ->perPageOptions([2, 5, 10])
            ->columns([
                $this->editable
                    ? TextColumn::make('title')->sortable()->editable()
                    : TextColumn::make('title')->sortable(),
            ]);

        if ($this->mode === 'simple') {
            $t->simplePagination();
        }

        if ($this->cached) {
            $t->cacheQuery(600);
        }

        if ($this->poll) {
            $t->poll('2s')->pollChangeDetection();
        }

        if ($this->queryString) {
            $t->queryString();
        }

        if ($this->lazyTable) {
            $t->lazy();
        }

        if ($this->selectable) {
            $t->selectable();
        }

        return $t;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    config()->set('cache.default', 'array');

    Schema::create('per_page_sweep_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });

    for ($i = 1; $i <= 12; $i++) {
        PerPageSweepPost::create(['id' => $i, 'title' => 'T'.$i]);
    }
});

afterEach(function () {
    Schema::dropIfExists('per_page_sweep_posts');
});

/** Rows actually present in the HTML the browser receives. */
function perPageSweepRows(string $html): int
{
    return preg_match_all('/<tr[^>]*data-row-key=/', $html);
}

dataset('configs', [
    'plain' => [[]],
    'cached' => [['cached' => true]],
    'poll' => [['poll' => true]],
    'queryString' => [['queryString' => true]],
    'lazy' => [['lazyTable' => true]],
    'editable' => [['editable' => true]],
    'selectable' => [['selectable' => true]],
    'simple pagination' => [['mode' => 'simple']],
    'everything' => [['cached' => true, 'poll' => true, 'queryString' => true, 'editable' => true, 'selectable' => true]],
]);

it('renders the new page size in the same response', function (array $params) {
    $c = Livewire::test(PerPageSweepHost::class, $params);

    if ($params['lazyTable'] ?? false) {
        $c->call('loadTable');
    }

    expect(perPageSweepRows($c->html()))->toBe(2);

    $c->set('tableState.pagination.perPage', '5');

    expect(perPageSweepRows($c->html()))->toBe(5);

    $c->set('tableState.pagination.perPage', '10');

    expect(perPageSweepRows($c->html()))->toBe(10);
})->with('configs');

it('renders the new page size when the change comes from page 2', function (array $params) {
    $c = Livewire::test(PerPageSweepHost::class, $params);

    if ($params['lazyTable'] ?? false) {
        $c->call('loadTable');
    }

    $c->call('nextPage');
    expect(perPageSweepRows($c->html()))->toBe(2);

    $c->set('tableState.pagination.perPage', '10');

    expect(perPageSweepRows($c->html()))->toBe(10);
})->with('configs');

it('renders the new page size when a poll lands in the same request', function () {
    $c = Livewire::test(PerPageSweepHost::class, ['poll' => true]);

    // Warm the checksum so an unchanged poll would skip.
    $c->call('refreshTable');
    expect(perPageSweepRows($c->html()))->toBe(2);

    // Pooled commit: the select's update and the poll tick in one request.
    $c->set('tableState.pagination.perPage', '10');
    $c->call('refreshTable');

    expect(perPageSweepRows($c->html()))->toBe(10);
});

it('renders the new page size when a poll ran in the request before', function () {
    $c = Livewire::test(PerPageSweepHost::class, ['poll' => true]);

    $c->call('refreshTable');
    $c->call('refreshTable');

    $c->set('tableState.pagination.perPage', '10');

    expect(perPageSweepRows($c->html()))->toBe(10);
});

it('keeps the select in sync with the rendered rows', function () {
    $c = Livewire::test(PerPageSweepHost::class);

    $c->set('tableState.pagination.perPage', '5');

    $html = $c->html();
    expect($html)->toContain('<option value="5" selected');
});
