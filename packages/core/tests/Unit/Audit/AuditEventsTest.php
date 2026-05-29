<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Audit\Contracts\AuditableEvent;
use NyonCode\WireCore\Audit\Events\BulkActionExecuted;
use NyonCode\WireCore\Audit\Events\InlineCellUpdated;
use NyonCode\WireCore\Audit\Events\RecordCreated;
use NyonCode\WireCore\Audit\Events\RecordDeleted;
use NyonCode\WireCore\Audit\Events\RecordUpdated;

// ─── RecordCreated ───────────────────────────────────────────────────────────

it('RecordCreated implements AuditableEvent', function () {
    $record = new class extends Model
    {
        protected $table = 'orders';

        protected $guarded = [];
    };
    $record->forceFill(['id' => 1, 'name' => 'Test']);

    $event = new RecordCreated($record, ['name' => 'Test']);

    expect($event)->toBeInstanceOf(AuditableEvent::class)
        ->and($event->getAuditEventType())->toBe('created')
        ->and($event->getAuditableType())->not->toBeNull()
        ->and($event->getAuditableId())->toBe(1)
        ->and($event->getOldValues())->toBeNull()
        ->and($event->getNewValues())->toBe(['name' => 'Test'])
        ->and($event->getMetadata())->toBe([]);
});

it('RecordCreated falls back to model attributes when newValues is empty', function () {
    $record = new class extends Model
    {
        protected $table = 'orders';

        protected $guarded = [];
    };
    $record->forceFill(['id' => 5, 'status' => 'pending']);

    $event = new RecordCreated($record);

    expect($event->getNewValues())->toHaveKey('id')
        ->and($event->getNewValues())->toHaveKey('status');
});

// ─── RecordUpdated ───────────────────────────────────────────────────────────

it('RecordUpdated implements AuditableEvent', function () {
    $record = new class extends Model
    {
        protected $table = 'orders';

        protected $guarded = [];
    };
    $record->forceFill(['id' => 2]);

    $event = new RecordUpdated(
        $record,
        ['status' => 'pending'],
        ['status' => 'approved'],
        ['ip' => '10.0.0.1'],
    );

    expect($event)->toBeInstanceOf(AuditableEvent::class)
        ->and($event->getAuditEventType())->toBe('updated')
        ->and($event->getAuditableId())->toBe(2)
        ->and($event->getOldValues())->toBe(['status' => 'pending'])
        ->and($event->getNewValues())->toBe(['status' => 'approved'])
        ->and($event->getMetadata())->toBe(['ip' => '10.0.0.1']);
});

// ─── RecordDeleted ───────────────────────────────────────────────────────────

it('RecordDeleted implements AuditableEvent', function () {
    $record = new class extends Model
    {
        protected $table = 'orders';

        protected $guarded = [];
    };
    $record->forceFill(['id' => 3, 'name' => 'Deleted']);

    $event = new RecordDeleted($record, ['name' => 'Deleted']);

    expect($event)->toBeInstanceOf(AuditableEvent::class)
        ->and($event->getAuditEventType())->toBe('deleted')
        ->and($event->getAuditableId())->toBe(3)
        ->and($event->getOldValues())->toBe(['name' => 'Deleted'])
        ->and($event->getNewValues())->toBeNull();
});

// ─── BulkActionExecuted ──────────────────────────────────────────────────────

it('BulkActionExecuted implements AuditableEvent', function () {
    $event = new BulkActionExecuted(
        actionName: 'delete',
        modelType: 'App\\Models\\Order',
        recordIds: [1, 2, 3],
        success: true,
    );

    expect($event)->toBeInstanceOf(AuditableEvent::class)
        ->and($event->getAuditEventType())->toBe('bulk_action')
        ->and($event->getAuditableType())->toBe('App\\Models\\Order')
        ->and($event->getAuditableId())->toBeNull()
        ->and($event->getOldValues())->toBeNull()
        ->and($event->getNewValues())->toBe([
            'action' => 'delete',
            'record_ids' => [1, 2, 3],
            'success' => true,
        ]);
});

// ─── InlineCellUpdated ───────────────────────────────────────────────────────

it('InlineCellUpdated implements AuditableEvent', function () {
    $event = new InlineCellUpdated(
        modelType: 'App\\Models\\Order',
        recordId: 42,
        column: 'status',
        oldValue: 'pending',
        newValue: 'approved',
    );

    expect($event)->toBeInstanceOf(AuditableEvent::class)
        ->and($event->getAuditEventType())->toBe('cell_updated')
        ->and($event->getAuditableType())->toBe('App\\Models\\Order')
        ->and($event->getAuditableId())->toBe(42)
        ->and($event->getOldValues())->toBe(['status' => 'pending'])
        ->and($event->getNewValues())->toBe(['status' => 'approved']);
});

// ─── Metadata ────────────────────────────────────────────────────────────────

it('all events support custom metadata', function () {
    $record = new class extends Model
    {
        protected $table = 'orders';

        protected $guarded = [];
    };
    $record->forceFill(['id' => 1]);
    $meta = ['ip' => '192.168.1.1', 'user_agent' => 'TestBot'];

    expect((new RecordCreated($record, [], $meta))->getMetadata())->toBe($meta)
        ->and((new RecordUpdated($record, [], [], $meta))->getMetadata())->toBe($meta)
        ->and((new RecordDeleted($record, [], $meta))->getMetadata())->toBe($meta)
        ->and((new BulkActionExecuted('x', 'Y', [], true, $meta))->getMetadata())->toBe($meta)
        ->and((new InlineCellUpdated('Y', 1, 'c', 'a', 'b', $meta))->getMetadata())->toBe($meta);
});
