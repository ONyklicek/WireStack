<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\Jobs\RunActionJob;
use NyonCode\WireCore\Exceptions\QueuedActionException;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * Actions that finish on a worker.
 *
 * The two things worth pinning are what crosses the boundary and what does not.
 * Keys and names cross; models, the action object and the browser do not — and
 * the last of those is stated loudly rather than degraded into no-ops, because a
 * silent $close() is a bug a user reports weeks later.
 */
class QaRow extends Model
{
    protected $table = 'qa_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class QaHost extends Component
{
    use WithTable;

    public bool $queued = true;

    /** What the queued callback did, so a job's effect is observable. */
    public static array $ran = [];

    /** Set by a test to make the callback reach for something a worker lacks. */
    public static ?string $reachesFor = null;

    public function table(Table $table): Table
    {
        $recalculate = Action::make('recalculate')
            ->label('Recalculate')
            ->action(function ($records = null, $record = null) {
                if (QaHost::$reachesFor !== null) {
                    // Deliberately asks for a browser binding.
                    app()->call(fn () => null);
                }

                // Which parameter arrived matters, not just the value: a single
                // record must come through as `record`, the way a synchronous
                // callback receives it, or every callback typed for one breaks
                // the moment its action is queued.
                QaHost::$ran[] = $record !== null
                    ? 'record:'.$record->name
                    : 'records:'.collect($records ?? [])->pluck('name')->implode(',');
            });

        if ($this->queued) {
            $recalculate->queue()->onQueue('reports')->onConnection('redis');
        }

        return $table
            ->model(QaRow::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')])
            ->actions([$recalculate]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('qa_rows', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });

    QaRow::insert([['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']]);

    QaHost::$ran = [];
    QaHost::$reachesFor = null;
});

afterEach(function () {
    Schema::dropIfExists('qa_rows');
});

// ─── What crosses the boundary ───────────────────────────────────────────────

it('dispatches instead of running, and carries keys rather than models', function () {
    // A model serialized at dispatch is stale by the time a worker takes it, and
    // a bulk action over ten thousand would be a megabyte of payload.
    Queue::fake();

    Livewire::test(QaHost::class)->call('executeTableAction', '1', 'recalculate');

    Queue::assertPushed(RunActionJob::class, function (RunActionJob $job): bool {
        return $job->actionName === 'recalculate'
            && $job->recordKeys === [1]
            && $job->host === QaHost::class;
    });
});

it('honours the queue and connection the action names', function () {
    Queue::fake();

    Livewire::test(QaHost::class)->call('executeTableAction', '1', 'recalculate');

    Queue::assertPushed(RunActionJob::class, function (RunActionJob $job): bool {
        return $job->queue === 'reports' && $job->connection === 'redis';
    });
});

it('tells the user it is on its way', function () {
    Queue::fake();

    Livewire::test(QaHost::class)
        ->call('executeTableAction', '1', 'recalculate')
        ->assertDispatched('table-notification');
});

it('runs inline when the action is not queued', function () {
    // The default, and it stays the default: a user clicking Delete expects the
    // row gone when the page comes back.
    Queue::fake();

    Livewire::test(QaHost::class, ['queued' => false])
        ->call('executeTableAction', '1', 'recalculate');

    Queue::assertNothingPushed();
});

// ─── What the job does when it runs ──────────────────────────────────────────

it('says so when the action is gone by the time the job runs', function () {
    // The job carries the name, not the action, so one renamed or removed
    // between dispatch and run has nothing to execute.
    expect(fn () => (new RunActionJob(QaHost::class, 'nope', [1]))->handle())
        ->toThrow(QueuedActionException::class, 'no longer declared');
});

it('says so when the host cannot be rebuilt', function () {
    expect(fn () => (new RunActionJob('App\\Nope', 'x', []))->handle())
        ->toThrow(QueuedActionException::class, 'could not be built');
});

it('resolves the records fresh, from their keys', function () {
    // The whole point of carrying keys: the row is read when the job runs, not
    // when it was queued, so a value edited in between is the one acted on.
    QaRow::find(2)->update(['name' => 'edited after dispatch']);

    (new RunActionJob(QaHost::class, 'recalculate', [2]))->handle();

    expect(QaHost::$ran)->toBe(['record:edited after dispatch']);
});

it('hands a bulk set over as records', function () {
    (new RunActionJob(QaHost::class, 'recalculate', [1, 2]))->handle();

    expect(QaHost::$ran)->toBe(['records:A,B']);
});

it('refuses a browser binding rather than passing a no-op', function () {
    // A no-op $close() would look like it worked; the developer would find out
    // when a user reported the modal never closing.
    $host = new QaHost;
    $job = new RunActionJob(QaHost::class, 'recalculate', [1]);

    $bindings = (new ReflectionMethod($job, 'browserlessBindings'));
    $bindings->setAccessible(true);

    foreach ($bindings->invoke($job) as $name => $binding) {
        expect(fn () => $binding())
            ->toThrow(QueuedActionException::class, 'needs the browser it no longer has');
    }
});

it('reports back with a notification when it finishes', function () {
    // The job outlives the request that queued it, which is why this matters and
    // why the database driver exists: there is no page left to flash into.
    $sent = [];

    NotificationManager::setDefaultDriver(new class($sent) implements NotificationDriver
    {
        public function __construct(public array &$sent) {}

        public function send(Notification $notification, mixed $livewireComponent = null): void
        {
            $this->sent[] = $notification->type.':'.$notification->message;
        }
    });

    (new RunActionJob(QaHost::class, 'recalculate', [1], [], 'Recalculate finished.'))->handle();

    expect($sent)->toBe(['success:Recalculate finished.']);

    NotificationManager::reset();
});

it('says nothing when no completion message was asked for', function () {
    $sent = [];

    NotificationManager::setDefaultDriver(new class($sent) implements NotificationDriver
    {
        public function __construct(public array &$sent) {}

        public function send(Notification $notification, mixed $livewireComponent = null): void
        {
            $this->sent[] = $notification->message;
        }
    });

    (new RunActionJob(QaHost::class, 'recalculate', [1]))->handle();

    expect($sent)->toBe([]);

    NotificationManager::reset();
});
