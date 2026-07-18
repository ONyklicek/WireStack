<?php

declare(strict_types=1);

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
