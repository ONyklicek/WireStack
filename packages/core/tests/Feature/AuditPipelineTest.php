<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Audit\AuditEntry;
use NyonCode\WireCore\Audit\AuditEventSubscriber;
use NyonCode\WireCore\Audit\Concerns\HasAuditable;

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

    // The audit_logs migration constrains user_id to users(id). SQLite ignores
    // the reference, MySQL/Postgres enforce it — satisfy it like a real app
    // schema does (and leave an existing users table alone).
    $this->createdUsersTable = false;

    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });
        $this->createdUsersTable = true;
    }

    (require dirname(__DIR__, 2).'/database/migrations/create_audit_logs_table.php')->up();

    Schema::create('audit_pipeline_orders', function (Blueprint $table) {
        $table->id();
        $table->string('status');
    });
});

afterEach(function () {
    Schema::dropIfExists('audit_pipeline_orders');
    Schema::dropIfExists('audit_logs');

    if ($this->createdUsersTable) {
        Schema::dropIfExists('users');
    }
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
