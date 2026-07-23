<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Audit\AuditEntry;

// ─── Factory ─────────────────────────────────────────────────────────────────

it('uses the audit_logs table', function () {
    $entry = new AuditEntry;

    expect($entry->getTable())->toBe('audit_logs');
});

it('does not use updated_at timestamp', function () {
    expect(AuditEntry::UPDATED_AT)->toBeNull();
});

it('casts values correctly', function () {
    $entry = new AuditEntry([
        'event' => 'updated',
        'auditable_type' => 'App\\Models\\Order',
        'auditable_id' => 1,
        'old_values' => ['status' => 'pending'],
        'new_values' => ['status' => 'approved'],
        'metadata' => ['ip' => '127.0.0.1'],
    ]);

    // JSON columns are cast to arrays via setters/getters
    expect($entry->event)->toBe('updated')
        ->and($entry->auditable_type)->toBe('App\\Models\\Order')
        ->and($entry->auditable_id)->toBe(1);
});

// ─── getChangeDiff() ────────────────────────────────────────────────────────────

it('does not shadow Eloquent getChanges(); the diff lives on getChangeDiff()', function () {
    // Regression M7: the old/new diff was named getChanges(), overriding
    // Eloquent's Model::getChanges() (flat persisted changes) with a different
    // shape the framework does not expect.
    $entry = new AuditEntry;
    $entry->old_values = ['status' => 'draft'];
    $entry->new_values = ['status' => 'published'];

    expect($entry->getChanges())->toBe([])
        ->and($entry->getChangeDiff())->toBe(['status' => ['old' => 'draft', 'new' => 'published']]);
});

it('computes changes diff from old and new values', function () {
    $entry = new AuditEntry;
    $entry->old_values = ['name' => 'Alice', 'email' => 'alice@example.com'];
    $entry->new_values = ['name' => 'Bob', 'email' => 'alice@example.com'];

    $changes = $entry->getChangeDiff();

    expect($changes)->toHaveKey('name')
        ->and($changes['name'])->toBe(['old' => 'Alice', 'new' => 'Bob'])
        ->and($changes)->not->toHaveKey('email');
});

it('handles null old values (created event)', function () {
    $entry = new AuditEntry;
    $entry->old_values = null;
    $entry->new_values = ['name' => 'Alice', 'status' => 'active'];

    $changes = $entry->getChangeDiff();

    expect($changes)->toHaveCount(2)
        ->and($changes['name'])->toBe(['old' => null, 'new' => 'Alice'])
        ->and($changes['status'])->toBe(['old' => null, 'new' => 'active']);
});

it('handles null new values (deleted event)', function () {
    $entry = new AuditEntry;
    $entry->old_values = ['name' => 'Alice'];
    $entry->new_values = null;

    $changes = $entry->getChangeDiff();

    expect($changes)->toHaveCount(1)
        ->and($changes['name'])->toBe(['old' => 'Alice', 'new' => null]);
});

it('returns empty changes when both values are null', function () {
    $entry = new AuditEntry;
    $entry->old_values = null;
    $entry->new_values = null;

    expect($entry->getChangeDiff())->toBe([]);
});

it('returns empty changes when values are identical', function () {
    $entry = new AuditEntry;
    $entry->old_values = ['name' => 'Alice'];
    $entry->new_values = ['name' => 'Alice'];

    expect($entry->getChangeDiff())->toBe([]);
});

// ─── Scopes ─────────────────────────────────────────────────────────────────

it('scopes to a record by morph type and key', function () {
    $record = new AuditScopeOrder;
    $record->forceFill(['id' => 7])->syncOriginal();

    $query = AuditEntry::forRecord($record);

    expect($query->toSql())->toContain('"auditable_type" = ?')
        ->and($query->getBindings())->toBe([AuditScopeOrder::class, 7]);
});

it('scopes to an event type', function () {
    expect(AuditEntry::forEvent('deleted')->getBindings())->toBe(['deleted']);
});

it('scopes to older entries by day count', function () {
    expect(AuditEntry::olderThan(30)->toSql())->toContain('"created_at" < ?');
});

it('scopes to an integer user id', function () {
    expect(AuditEntry::byUser(42)->getBindings())->toBe([42]);
});

/**
 * Regression: the actor key may be a UUID/ULID — `resolveUserId()` returns
 * int|string and the column is a string — but scopeByUser() type-hinted int, so
 * a UUID actor made the scope fatal under strict_types.
 */
it('scopes to a string (UUID/ULID) user id', function () {
    $uuid = '9c4a0f2e-1b7d-4c3a-9f11-2a5b8c7d6e01';

    expect(AuditEntry::byUser($uuid)->getBindings())->toBe([$uuid]);
});

class AuditScopeOrder extends Model
{
    protected $table = 'audit_scope_orders';

    protected $guarded = [];
}
