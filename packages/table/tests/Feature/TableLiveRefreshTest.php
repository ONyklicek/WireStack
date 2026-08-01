<?php

declare(strict_types=1);

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Events\TableRecordsChanged;
use NyonCode\WireTable\Support\LiveChannel;
use NyonCode\WireTable\Table;

/*
 * `Table::live()` — the table shows what the database holds, for everyone
 * looking at it.
 *
 * Two transports, and the split matters: the poll is what makes it WORK (no
 * infrastructure, always there), the broadcast only makes it FASTER. So the
 * broadcast is opt-in, carries nothing, and a session that never receives one
 * must still converge on the next tick.
 */
class LivePost extends Model
{
    protected $table = 'live_posts';

    protected $guarded = [];
}

class LiveHost extends Component
{
    use WithTable;

    public bool $broadcast = false;

    /** Test probe: the poll skip decision is protected on the trait. */
    public function pollWouldSkipRender(): bool
    {
        return $this->shouldSkipPollRender();
    }

    public function table(Table $table): Table
    {
        return $table
            ->model(LivePost::class)
            ->paginated(false)
            ->live('2s', broadcast: $this->broadcast)
            ->fillHandle()
            ->columns([TextInputColumn::make('title')]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    // The write generation lives in the cache, because the sessions it tells
    // about each other are separate processes.
    config()->set('cache.default', 'array');

    Schema::create('live_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });

    LivePost::create(['id' => 1, 'title' => 'T1']);
});

afterEach(function () {
    Schema::dropIfExists('live_posts');
});

it('turns on both halves of a live table in one call', function () {
    $table = (new LiveHost)->getTable();

    expect($table->isPolling())->toBeTrue()
        ->and($table->getPollingInterval())->toBe('2s')
        // Without change detection a short interval is a full query, summaries
        // and a DOM morph per client per tick — the reason live() is one call
        // and not two settings the caller can half-apply.
        ->and($table->getPollChangeDetection())->toBeTrue();
});

it('re-renders on the tick that follows another session\'s edit', function () {
    $us = Livewire::test(LiveHost::class);

    // Warm the checksum: with nothing else going on, the next tick reports
    // "unchanged" and costs nothing but the checksum query.
    $us->call('refreshTable');
    expect($us->instance()->pollWouldSkipRender())->toBeTrue();

    // Another session, editing the same record — the whole case live() exists
    // for. Same row count, same second: only the write generation separates this
    // from nothing having happened at all.
    Livewire::test(LiveHost::class)->call('updateTableCell', 1, 'title', 'Written elsewhere');

    expect($us->instance()->pollWouldSkipRender())->toBeFalse()
        ->and($us->call('refreshTable')->html())->toContain('Written elsewhere');
});

it('re-renders after another session fills a range', function () {
    $us = Livewire::test(LiveHost::class);
    $us->call('refreshTable');
    expect($us->instance()->pollWouldSkipRender())->toBeTrue();

    Livewire::test(LiveHost::class)->call('fillTableCells', [[
        'column' => 'title',
        'value' => 'Filled elsewhere',
        'records' => ['1' => null],
    ]]);

    expect($us->instance()->pollWouldSkipRender())->toBeFalse();
});

it('re-renders after another session runs an action', function () {
    $us = Livewire::test(LiveHost::class);
    $us->call('refreshTable');
    expect($us->instance()->pollWouldSkipRender())->toBeTrue();

    $them = Livewire::test(LiveHost::class);
    LivePost::create(['id' => 2, 'title' => 'T2']);
    $them->call('invalidateTable');

    expect($us->instance()->pollWouldSkipRender())->toBeFalse();
});

it('picks up a write made outside any table once the clock moves on', function () {
    // A job, a migration, another app — nothing bumped the generation, so this
    // rests on COUNT + MAX(updated_at) alone. `updated_at` is stored to the
    // second, and that is the residual limit: a write landing inside the same
    // second as the last checksum is indistinguishable from no write, and stays
    // so until something moves the timestamp into a new second. Pass a detector
    // to pollChangeDetection() when a table must see those too.
    $us = Livewire::test(LiveHost::class);
    $us->call('refreshTable');
    expect($us->instance()->pollWouldSkipRender())->toBeTrue();

    LivePost::query()->whereKey(1)->update([
        'title' => 'Written by a job',
        'updated_at' => now()->addMinute(),
    ]);

    expect($us->instance()->pollWouldSkipRender())->toBeFalse()
        ->and($us->call('refreshTable')->html())->toContain('Written by a job');
});

it('says nothing to other sessions unless the table asked it to', function () {
    Event::fake([TableRecordsChanged::class]);

    Livewire::test(LiveHost::class)->call('updateTableCell', 1, 'title', 'Quietly');

    Event::assertNotDispatched(TableRecordsChanged::class);
});

it('announces an inline edit to the other sessions when broadcasting is on', function () {
    Event::fake([TableRecordsChanged::class]);

    Livewire::test(LiveHost::class, ['broadcast' => true])
        ->call('updateTableCell', 1, 'title', 'Loudly');

    Event::assertDispatched(
        TableRecordsChanged::class,
        fn (TableRecordsChanged $e) => $e->scope === LivePost::class,
    );
});

it('says nothing when the write was refused', function () {
    // A rejected edit changed nothing, and waking every other session to re-read
    // an unchanged table is the sort of chatter that makes people turn the
    // feature off.
    Event::fake([TableRecordsChanged::class]);

    $result = Livewire::test(LiveHost::class, ['broadcast' => true])
        ->call('updateTableCell', 1, 'title', 'Ignored', '1')  // stale version
        ->effects['returns'][0] ?? null;

    expect($result['success'])->toBeFalse()
        ->and($result['conflict'])->toBeTrue();

    Event::assertNotDispatched(TableRecordsChanged::class);
});

it('names a channel an app can read and authorize', function () {
    // A hash would be shorter and would leave the app authorizing something it
    // cannot identify.
    expect(TableRecordsChanged::channelFor(LivePost::class))
        ->toBe('wire-table.LivePost')
        ->and((new TableRecordsChanged(LivePost::class))->broadcastOn())
        ->toBe(['private-wire-table.LivePost'])
        ->and((new TableRecordsChanged(LivePost::class))->broadcastAs())
        ->toBe('wire-table.changed');
});

it('keeps the scope to one channel segment, so a wildcard can match it', function () {
    // Laravel compiles `{scope}` to `([^\.]+)`. A dotted class in the name would
    // make LiveChannel::PATTERN match nothing, and every model would need its own
    // hand-written Broadcast::channel() line — spelled right, forever, with a
    // typo costing a silently dead push that polling covers for.
    $name = LiveChannel::for('App\\Models\\Invoice');

    expect($name)->toBe('wire-table.App-Models-Invoice')
        ->and(substr_count($name, '.'))->toBe(1);

    $pattern = '/^'.preg_replace('/\{(.*?)\}/', '([^\.]+)', LiveChannel::PATTERN).'$/';
    expect(preg_match($pattern, $name))->toBe(1);
});

it('turns a channel segment back into the class it names', function () {
    // The app's callback is handed a class name, never the wire format — so the
    // encoding stays an implementation detail of this one class.
    $class = 'App\\Models\\Invoice';
    $segment = substr(LiveChannel::for($class), strlen('wire-table.'));

    expect(LiveChannel::scopeFrom('App-Models-Invoice'))->toBe($class)
        // Round-trips, which is the property that matters: `-` cannot occur in a
        // PHP class name and `\` cannot occur in a channel name, so neither
        // direction is ambiguous.
        ->and(LiveChannel::scopeFrom($segment))->toBe($class);
});

it('broadcasts without going through a queue', function () {
    // `ShouldBroadcast` would hand this to the app's queue, and the very common
    // setup of a configured queue with no worker running for it would swallow the
    // push half entirely — silently, because polling covers for it and the table
    // still refreshes on the interval. Found exactly that way: against a real
    // Reverb the event never arrived, and the run looked healthy because the tick
    // did the work a second later.
    //
    // Pinned as the interface rather than as a timing assertion, because the
    // failure it guards is a class swap someone could make in one word.
    expect(new TableRecordsChanged(LivePost::class))
        ->toBeInstanceOf(ShouldBroadcastNow::class);
});

it('still renders when the cache store it wants is not there', function () {
    // The generation is an optimisation on both sides — the query cache still
    // expires on its TTL, change detection still has COUNT + MAX(updated_at). A
    // store that is misconfigured or briefly down must cost a feature, never the
    // page: `database` here with no `cache` table, which is exactly what an app
    // that never ran the cache migration has.
    config()->set('cache.default', 'database');

    $us = Livewire::test(LiveHost::class);

    expect($us->call('refreshTable')->html())->toContain('T1')
        // And a write still commits, rather than reporting an error for a save
        // that already landed.
        ->and($us->call('updateTableCell', 1, 'title', 'Written anyway')->html())
        ->toContain('Written anyway');
});

it('only offers a channel to the view when broadcasting is on', function () {
    expect(Livewire::test(LiveHost::class)->instance()->getTableLiveChannel())->toBeNull()
        ->and(Livewire::test(LiveHost::class, ['broadcast' => true])->instance()->getTableLiveChannel())
        ->toBe('wire-table.LivePost');
});
