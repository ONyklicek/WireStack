<?php

declare(strict_types=1);

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Tenancy\Concerns\BelongsToTenant;
use NyonCode\WireCore\Core\Tenancy\CurrentTenant;
use NyonCode\WireCore\Core\Tenancy\Queue\CarriesTenantThroughQueue;
use NyonCode\WireCore\Core\Tenancy\Tenancy;
use NyonCode\WireCore\Exceptions\TenancyException;

/*
 * Queued work runs in the tenant it was dispatched in (ADR 0040 §9).
 *
 * Both halves matter: a worker — a separate process that knows nothing of the
 * request — has to end up in the right company, and a `sync` job, which runs
 * inside the request, must hand the request its tenant back afterwards.
 */
class TqCompany extends Model
{
    protected $table = 'tq_companies';

    protected $guarded = [];

    public $timestamps = false;
}

class TqInvoice extends Model
{
    use BelongsToTenant;

    protected $table = 'tq_invoices';

    protected $guarded = [];

    public $timestamps = false;
}

class TqJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** @var array<int, array{tenant: int|string|null, invoices: array<int, string>, carried: mixed}> */
    public static array $seen = [];

    public function handle(): void
    {
        self::$seen[] = [
            'tenant' => app(CurrentTenant::class)->key(),
            'invoices' => TqInvoice::query()->pluck('number')->all(),
            'carried' => $this->job?->payload()[CarriesTenantThroughQueue::PAYLOAD] ?? null,
        ];
    }
}

beforeEach(function () {
    Schema::create('tq_companies', fn (Blueprint $t) => $t->id());
    Schema::create('tq_invoices', function (Blueprint $t) {
        $t->id();
        $t->string('number');
        $t->unsignedBigInteger('tenant_id');
    });
    Schema::create('jobs', function (Blueprint $t) {
        $t->id();
        $t->string('queue')->index();
        $t->longText('payload');
        $t->unsignedTinyInteger('attempts');
        $t->unsignedInteger('reserved_at')->nullable();
        $t->unsignedInteger('available_at');
        $t->unsignedInteger('created_at');
    });

    foreach ([1, 2, 3] as $id) {
        TqCompany::query()->create(['id' => $id]);
        TqInvoice::query()->withoutGlobalScopes()->create(['number' => "N-{$id}", 'tenant_id' => $id]);
    }

    config()->set('wire-core.tenancy.enabled', true);
    config()->set('queue.connections.database', ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90]);
    config()->set('queue.failed.driver', 'null');
    // The worker asks the cache which queues are paused; the suite's default
    // cache store is a database table this test does not have.
    config()->set('cache.default', 'array');
    TqJob::$seen = [];
});

afterEach(function () {
    foreach (['jobs', 'tq_invoices', 'tq_companies'] as $table) {
        Schema::dropIfExists($table);
    }

    config()->set('wire-core.tenancy.enabled', false);
});

function tqWork(): void
{
    // What a worker process starts every job with: nothing of the request.
    app()->forgetScopedInstances();

    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true, '--tries' => 1]);
}

it('runs a queued job in the tenant it was dispatched in', function () {
    app(Tenancy::class)->runAs(TqCompany::query()->find(2), function (): void {
        TqJob::dispatch()->onConnection('database');
    });

    expect(app(CurrentTenant::class)->has())->toBeFalse();

    tqWork();

    expect(TqJob::$seen)->toHaveCount(1)
        ->and(TqJob::$seen[0]['tenant'])->toBe(2)
        ->and(TqJob::$seen[0]['invoices'])->toBe(['N-2'])
        ->and(TqJob::$seen[0]['carried'])->toBe(['class' => TqCompany::class, 'key' => 2]);
});

it('runs a job dispatched outside any tenant outside one, seeing nothing', function () {
    TqJob::dispatch()->onConnection('database');

    tqWork();

    expect(TqJob::$seen[0]['tenant'])->toBeNull()
        ->and(TqJob::$seen[0]['invoices'])->toBe([])
        ->and(TqJob::$seen[0]['carried'])->toBeNull();
});

it('hands a sync job the tenant and gives the request its own back', function () {
    app(CurrentTenant::class)->enter(TqCompany::query()->find(1));

    app(Tenancy::class)->runAs(TqCompany::query()->find(3), fn () => TqJob::dispatchSync());

    expect(TqJob::$seen[0]['tenant'])->toBe(3)
        ->and(app(CurrentTenant::class)->key())->toBe(1);

    TqJob::dispatchSync();

    expect(TqJob::$seen[1]['tenant'])->toBe(1)
        ->and(app(CurrentTenant::class)->key())->toBe(1);
});

it('fails a job whose tenant no longer exists instead of running it in none', function () {
    Event::fake([JobFailed::class]);

    app(Tenancy::class)->runAs(TqCompany::query()->find(3), function (): void {
        TqJob::dispatch()->onConnection('database');
    });
    TqCompany::query()->whereKey(3)->delete();

    tqWork();

    expect(TqJob::$seen)->toBe([]);

    Event::assertDispatched(JobFailed::class, fn (JobFailed $event) => $event->exception instanceof TenancyException
        && str_contains($event->exception->getMessage(), 'no longer exists'));
});

it('refuses a payload whose tenant is not a model at all', function () {
    $job = new SyncJob(app(), json_encode([
        CarriesTenantThroughQueue::PAYLOAD => ['class' => stdClass::class, 'key' => 1],
    ]), 'sync', 'default');

    event(new JobProcessing('sync', $job));
})->throws(TenancyException::class, '[stdClass:1]');

it('dispatches a job returned out of runAs inside the tenant, not after it', function () {
    // `fn () => Job::dispatch()` hands the PendingDispatch back to runAs; left
    // to the caller it would queue after the tenant had been left.
    $returned = app(Tenancy::class)->runAs(TqCompany::query()->find(1), fn () => TqJob::dispatch()->onConnection('database'));

    expect($returned)->toBeNull();

    tqWork();

    expect(TqJob::$seen[0]['tenant'])->toBe(1)
        ->and(TqJob::$seen[0]['invoices'])->toBe(['N-1']);
});
