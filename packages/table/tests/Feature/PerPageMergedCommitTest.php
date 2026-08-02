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
 * updateTableCell() used to call skipRender() unconditionally — so a morph could
 * not destroy the Alpine cell state — and the response came back with no HTML at
 * all. The per-page change IS in the snapshot, so nothing looked broken on the
 * server; the browser simply never received the rows it asked for, and the new
 * page size only appeared on whatever the user did next.
 *
 * The default has since flipped: a write renders, because everything derived
 * from the written value is stale without one, and the cell protects its own
 * state (`wire:ignore.self` plus its sync node). `refreshAfterEdit(false)` opts
 * back out — and the merged-commit hazard is the same for the opt-out, which is
 * why it is exercised here too.
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

    public bool $refreshAfterEdit = true;

    public function table(Table $table): Table
    {
        $t = $table
            ->model(MergedCommitPost::class)
            ->paginated()
            ->perPage(2)
            ->perPageOptions([2, 5, 10])
            ->fillHandle()
            ->refreshAfterEdit($this->refreshAfterEdit)
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

it('renders a cell edit that changes no table state, so the rest of the row can follow it', function () {
    // The write is not only about the cell that took it: summaries, rollups and
    // any column derived from the edited value are stale the moment it lands.
    $c = Livewire::test(MergedCommitHost::class);

    $result = mergedCommit(
        $c->snapshot,
        [],
        [['method' => 'updateTableCell', 'params' => [1, 'title', 'Edited', null]]],
    );

    expect($result['html'])->not->toBeNull()
        ->and($result['html'])->toContain('Edited');
});

it('renders a fill that changes no table state', function () {
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

    expect($result['html'])->not->toBeNull()
        ->and($result['html'])->toContain('Filled');
});

it('skips the render for a cell edit when the table opted out', function () {
    // refreshAfterEdit(false) is the way back to the old behaviour for a table
    // where a query per edit is not worth it — the cell still reconciles itself
    // from the response, nothing around it does.
    $c = Livewire::test(MergedCommitHost::class, ['refreshAfterEdit' => false]);

    $result = mergedCommit(
        $c->snapshot,
        [],
        [['method' => 'updateTableCell', 'params' => [1, 'title', 'Edited', null]]],
    );

    expect($result['html'])->toBeNull();
});

it('skips the render for a fill when the table opted out', function () {
    $c = Livewire::test(MergedCommitHost::class, ['refreshAfterEdit' => false]);

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

it('renders the per-page change even when the table opted out of refreshing after an edit', function () {
    // The opt-out may never swallow a change the user made to the VIEW. Livewire
    // merges both into one commit, updates first, and skipping there answers with
    // no HTML at all — the new page size sits in the snapshot while the browser
    // keeps the old rows.
    $c = Livewire::test(MergedCommitHost::class, ['refreshAfterEdit' => false]);

    $result = mergedCommit(
        $c->snapshot,
        ['tableState.pagination.perPage' => '10'],
        [['method' => 'updateTableCell', 'params' => [1, 'title', 'Edited', null]]],
    );

    expect(mergedCommitRows($result['html']))->toBe(10);
});

/*
 * A page change is not a property update, and that difference is the whole test.
 *
 * The per-page select posts an update, and Livewire applies every update before
 * any call — so the table always learns the view changed before an inline edit
 * gets to ask for a skip. `setPage()` is a *call*, ordered by when the browser
 * queued it, and the browser queues the edit FIRST: clicking a pagination link
 * blurs the input on the way, and the blur is what commits the cell. The skip is
 * therefore already granted by the time the page changes, which is why marking
 * the request has to be able to take a skip back rather than merely refuse to
 * grant one later.
 *
 * Only reachable with refreshAfterEdit(false): a table on the default renders
 * after a write anyway and has no skip to take back.
 */
it('renders the new page when the cell edit was queued before it', function () {
    $c = Livewire::test(MergedCommitHost::class, ['refreshAfterEdit' => false]);

    $result = mergedCommit(
        $c->snapshot,
        [],
        [
            ['method' => 'updateTableCell', 'params' => [1, 'title', 'Edited', null]],
            ['method' => 'setPage', 'params' => [2, 'page']],
        ],
    );

    expect($result['snapshot']['data']['paginators'][0]['page'])->toBe(2);
    expect(mergedCommitRows($result['html']))->toBe(2);
    expect($result['html'])->toContain('T3');
});

it('renders the new page when the page change was queued before the edit', function () {
    $c = Livewire::test(MergedCommitHost::class, ['refreshAfterEdit' => false]);

    $result = mergedCommit(
        $c->snapshot,
        [],
        [
            ['method' => 'setPage', 'params' => [2, 'page']],
            ['method' => 'updateTableCell', 'params' => [1, 'title', 'Edited', null]],
        ],
    );

    expect($result['snapshot']['data']['paginators'][0]['page'])->toBe(2);
    expect(mergedCommitRows($result['html']))->toBe(2);
    expect($result['html'])->toContain('T3');
});

it('renders the new page when a fill drag shares the request with it', function () {
    $c = Livewire::test(MergedCommitHost::class, ['refreshAfterEdit' => false]);

    $result = mergedCommit(
        $c->snapshot,
        [],
        [
            ['method' => 'fillTableCells', 'params' => [[[
                'column' => 'title',
                'value' => 'Filled',
                'records' => ['1' => null],
            ]]]],
            ['method' => 'setPage', 'params' => [2, 'page']],
        ],
    );

    expect(mergedCommitRows($result['html']))->toBe(2);
});
