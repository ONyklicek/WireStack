<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Audit\AuditEntry;
use NyonCode\WireCore\Audit\AuditEventSubscriber;
use NyonCode\WireCore\Audit\AuditLogger;
use NyonCode\WireCore\Audit\Concerns\HasAuditable;
use NyonCode\WireCore\Audit\Contracts\AuditableEvent;
use NyonCode\WireCore\Audit\Events\BulkActionExecuted;
use NyonCode\WireCore\Audit\Events\InlineCellUpdated;
use NyonCode\WireCore\Audit\Events\RecordCreated;

/**
 * End-to-end audit pipeline (regression: the package fired AuditableEvents but
 * never registered the subscriber that persists them — every consumer had to
 * add Event::subscribe(AuditEventSubscriber::class) manually).
 */
class AuditPipelineOrder extends Model
{
    use HasAuditable;

    protected $table = 'audit_pipeline_orders';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function () {
    // A previously failed run can leave tables behind on MySQL/Postgres, where
    // DDL is not rolled back with the test — clear before creating.
    Schema::dropIfExists('audit_pipeline_orders');
    Schema::dropIfExists('audit_logs');

    // No `users` table is set up here on purpose: `user_id` is a plain nullable
    // string with no foreign key, precisely so an entry outlives the actor it
    // names. Creating one would imply a constraint the schema does not have.
    (require dirname(__DIR__, 2).'/database/migrations/create_audit_logs_table.php')->up();

    Schema::create('audit_pipeline_orders', function (Blueprint $table) {
        $table->id();
        $table->string('status');
    });
});

afterEach(function () {
    Schema::dropIfExists('audit_pipeline_orders');
    Schema::dropIfExists('audit_logs');
});

it('persists audit entries without any manual subscriber registration', function () {
    $order = AuditPipelineOrder::create(['status' => 'pending']);
    $order->update(['status' => 'paid']);

    expect(AuditEntry::query()->pluck('event')->all())->toBe(['created', 'updated'])
        ->and(AuditEntry::query()->where('event', 'updated')->first()->new_values)->toBe(['status' => 'paid']);
});

it('does not double-log when the app also registers the subscriber manually (pre-1.7.1 setup)', function () {
    Event::subscribe(AuditEventSubscriber::class);

    AuditPipelineOrder::create(['status' => 'pending']);

    expect(AuditEntry::query()->count())->toBe(1);
});

it('persists a hand-dispatched auditable event', function () {
    $order = AuditLogger::withoutAuditing(
        fn () => AuditPipelineOrder::create(['status' => 'pending']),
    );

    event(new RecordCreated($order, ['status' => 'pending']));

    expect(AuditEntry::query()->count())->toBe(1)
        ->and(AuditEntry::query()->first()->auditable_id)->toEqual($order->getKey());
});

/**
 * The two manual events are the documented way to audit a write that bypasses
 * model events (see their class docblocks and `docs/core/audit.md`). Only their
 * contract shape was covered — nothing proved a dispatched one actually reaches
 * audit_logs with the right morph type, key and event name.
 */
it('persists a manually dispatched InlineCellUpdated', function () {
    event(new InlineCellUpdated(
        modelType: AuditPipelineOrder::class,
        recordId: 7,
        column: 'status',
        oldValue: 'draft',
        newValue: 'approved',
    ));

    $entry = AuditEntry::query()->sole();

    expect($entry->event)->toBe('cell_updated')
        ->and($entry->auditable_type)->toBe(AuditPipelineOrder::class)
        ->and($entry->auditable_id)->toEqual(7)
        ->and($entry->old_values)->toBe(['status' => 'draft'])
        ->and($entry->new_values)->toBe(['status' => 'approved']);
});

it('persists a manually dispatched BulkActionExecuted with no auditable id', function () {
    event(new BulkActionExecuted(
        actionName: 'archive',
        modelType: AuditPipelineOrder::class,
        recordIds: [1, 2, 3],
        metadata: ['source' => 'orders-table'],
    ));

    $entry = AuditEntry::query()->sole();

    expect($entry->event)->toBe('bulk_action')
        ->and($entry->auditable_id)->toBeNull()
        // Asserted key by key, not with toBe(): MySQL's native JSON type does not
        // preserve object key order (it reorders members in its binary format), so
        // an order-sensitive comparison passes on SQLite/Postgres and fails on the
        // MySQL matrix leg. Nothing may depend on the key order of old/new_values.
        ->and($entry->new_values)->toMatchArray([
            'action' => 'archive',
            'record_ids' => [1, 2, 3],
            'success' => true,
        ])
        ->and(array_keys($entry->new_values))->toHaveCount(3)
        // The event's own metadata is merged over the request context, not replaced by it.
        ->and($entry->metadata)->toMatchArray(['source' => 'orders-table']);
});

it('honours the configured event-type allow list for manual events', function () {
    config()->set('wire-core.audit.events', ['bulk_action']);

    event(new InlineCellUpdated(AuditPipelineOrder::class, 7, 'status', 'draft', 'approved'));
    event(new BulkActionExecuted('archive', AuditPipelineOrder::class, [1]));

    expect(AuditEntry::query()->pluck('event')->all())->toBe(['bulk_action']);
});

/**
 * The migration deliberately puts no foreign key on user_id: an audit entry must
 * survive the actor's deletion, since who did it is the point of the record. A
 * constrained column would either block the delete or null the actor away.
 */
it('records an actor that no users table backs', function () {
    $entry = new AuditEntry;
    $entry->event = 'created';
    $entry->auditable_type = AuditPipelineOrder::class;
    $entry->auditable_id = 1;
    $entry->user_id = 999999;
    $entry->save();

    expect(AuditEntry::query()->sole()->user_id)->toEqual(999999);
});

/**
 * Regression: the subscriber skipped its own registration whenever
 * `hasListeners(AuditableEvent::class)` was true — which Laravel also reports for
 * *wildcard* listeners. One `Event::listen('*', …)` from Telescope, Pulse,
 * debugbar or an app's own event log booting before wire-core silently killed the
 * whole audit pipeline, while `getListeners()` still showed (wildcard) listeners.
 */
it('audits normally when a catch-all wildcard listener is registered first', function () {
    // Rebuild the pipeline in the order a real app boots it: the wildcard
    // listener exists before wire-core's provider subscribes.
    Event::forget(AuditableEvent::class);
    Event::listen('*', fn () => null);
    Event::subscribe(AuditEventSubscriber::class);

    AuditPipelineOrder::create(['status' => 'pending']);

    expect(AuditEntry::query()->count())->toBe(1);
});

it('does not double-log when a wildcard listener and the subscriber both exist', function () {
    Event::listen('*', fn () => null);
    Event::subscribe(AuditEventSubscriber::class);

    AuditPipelineOrder::create(['status' => 'pending']);

    expect(AuditEntry::query()->count())->toBe(1);
});

it('persists nothing when auditing is disabled', function () {
    config()->set('wire-core.audit.enabled', false);

    AuditPipelineOrder::create(['status' => 'pending']);

    expect(AuditEntry::query()->count())->toBe(0);
});

// ─── wire-core:audit-prune ───────────────────────────────────────

function seedAuditEntry(int $daysOld): void
{
    $entry = new AuditEntry;
    $entry->event = 'created';
    $entry->auditable_type = AuditPipelineOrder::class;
    $entry->auditable_id = 1;
    $entry->save();

    AuditEntry::query()->whereKey($entry->getKey())
        ->update(['created_at' => now()->subDays($daysOld)]);
}

it('prunes entries older than the configured retention period', function () {
    config()->set('wire-core.audit.retention_days', 30);
    seedAuditEntry(45);
    seedAuditEntry(5);

    $this->artisan('wire-core:audit-prune')
        ->expectsOutputToContain('Pruned 1 audit entry.')
        ->assertSuccessful();

    expect(AuditEntry::query()->count())->toBe(1);
});

it('honours a --days override over the configured retention', function () {
    config()->set('wire-core.audit.retention_days', 365);
    seedAuditEntry(45);
    seedAuditEntry(5);

    $this->artisan('wire-core:audit-prune', ['--days' => 10])
        ->expectsOutputToContain('Pruned 1 audit entry.')
        ->assertSuccessful();

    expect(AuditEntry::query()->count())->toBe(1);
});

it('warns and prunes nothing without a retention period', function () {
    seedAuditEntry(45);

    $this->artisan('wire-core:audit-prune')
        ->expectsOutputToContain('No retention period configured')
        ->assertFailed();

    expect(AuditEntry::query()->count())->toBe(1);
});
