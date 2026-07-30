<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * Livewire merges everything queued for one component into ONE commit: an
 * inline-edit call that is in flight (or fired in the same tick) and the
 * per-page select's update travel together, updates applied first, calls after.
 *
 * updateTableCell() then calls skipRender() — deliberately, so a morph cannot
 * destroy the Alpine cell state — and the response comes back with no HTML at
 * all. The per-page change IS in the snapshot, so nothing looks broken on the
 * server; the browser simply never receives the rows it asked for, and the new
 * page size only appears on whatever the user does next.
 */
class MergedCommitPost extends Model
{
    protected $table = 'merged_commit_posts';

    protected $guarded = [];
}

class MergedCommitHost extends Component
{
    use WithTable;

    public bool $poll = false;

    public function table(Table $table): Table
    {
        $t = $table
            ->model(MergedCommitPost::class)
            ->paginated()
            ->perPage(2)
            ->perPageOptions([2, 5, 10])
            ->fillHandle()
            ->columns([TextInputColumn::make('title')]);

        return $this->poll ? $t->poll('2s')->pollChangeDetection() : $t;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    Schema::create('merged_commit_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });

    for ($i = 1; $i <= 12; $i++) {
        MergedCommitPost::create(['id' => $i, 'title' => 'T'.$i]);
    }
});

afterEach(function () {
    Schema::dropIfExists('merged_commit_posts');
});

/**
 * One Livewire request carrying both property updates and method calls — what
 * the browser sends when a commit merges them.
 *
 * @param  array<string, mixed>  $updates
 * @param  array<int, array{method: string, params: array<int, mixed>}>  $calls
 * @return array{html: ?string, snapshot: array<string, mixed>}
 */
function mergedCommit(array $snapshot, array $updates, array $calls): array
{
    $response = test()->withHeader('X-Livewire', 'true')->postJson(
        app('livewire')->getUpdateUri(),
        [
            'components' => [[
                'snapshot' => json_encode($snapshot),
                'updates' => $updates,
                'calls' => array_map(
                    fn (array $call) => $call + ['path' => '', 'id' => (string) random_int(1, 99999)],
                    $calls,
                ),
            ]],
        ],
    );

    $response->assertOk();

    $payload = $response->json('components.0');

    return [
        'html' => $payload['effects']['html'] ?? null,
        'snapshot' => json_decode($payload['snapshot'], true),
    ];
}

function mergedCommitRows(?string $html): int
{
    return $html === null ? 0 : preg_match_all('/<tr[^>]*data-row-key=/', $html);
}

it('renders the new page size when a cell edit shares the request', function () {
    $c = Livewire::test(MergedCommitHost::class);

    $result = mergedCommit(
        $c->snapshot,
        ['tableState.pagination.perPage' => '10'],
        [['method' => 'updateTableCell', 'params' => [1, 'title', 'Edited', null]]],
    );

    expect($result['snapshot']['data']['tableState'][0]['pagination'][0]['perPage'])->toBe(10);

    expect(mergedCommitRows($result['html']))->toBe(10);
});

it('renders the new page size when a cell validation shares the request', function () {
    $c = Livewire::test(MergedCommitHost::class);

    $result = mergedCommit(
        $c->snapshot,
        ['tableState.pagination.perPage' => '10'],
        [['method' => 'validateTableCell', 'params' => [1, 'title', 'Edited']]],
    );

    expect(mergedCommitRows($result['html']))->toBe(10);
});

it('renders the new page size when a fill drag shares the request', function () {
    $c = Livewire::test(MergedCommitHost::class);

    $result = mergedCommit(
        $c->snapshot,
        ['tableState.pagination.perPage' => '10'],
        [['method' => 'fillTableCells', 'params' => [[[
            'column' => 'title',
            'value' => 'Filled',
            'records' => ['1' => null, '2' => null],
        ]]]]],
    );

    expect(mergedCommitRows($result['html']))->toBe(10);
});

it('renders the new page size when a poll tick and a cell edit share the request', function () {
    $c = Livewire::test(MergedCommitHost::class, ['poll' => true]);

    // Warm the checksum, so the poll on its own would report "nothing changed".
    $c->call('refreshTable');

    $result = mergedCommit(
        $c->snapshot,
        ['tableState.pagination.perPage' => '10'],
        [
            ['method' => 'updateTableCell', 'params' => [1, 'title', 'Edited', null]],
            ['method' => 'refreshTable', 'params' => []],
        ],
    );

    expect(mergedCommitRows($result['html']))->toBe(10);
});

it('still skips the render for a cell edit that changes no table state', function () {
    $c = Livewire::test(MergedCommitHost::class);

    $result = mergedCommit(
        $c->snapshot,
        [],
        [['method' => 'updateTableCell', 'params' => [1, 'title', 'Edited', null]]],
    );

    expect($result['html'])->toBeNull();
});

it('still skips the render for a fill that changes no table state', function () {
    $c = Livewire::test(MergedCommitHost::class);

    $result = mergedCommit(
        $c->snapshot,
        [],
        [['method' => 'fillTableCells', 'params' => [[[
            'column' => 'title',
            'value' => 'Filled',
            'records' => ['1' => null],
        ]]]]],
    );

    expect($result['html'])->toBeNull();
});
