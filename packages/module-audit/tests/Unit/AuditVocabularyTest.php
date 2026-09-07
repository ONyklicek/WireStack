<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use NyonCode\WireCore\Audit\AuditEntry;
use NyonCode\WireModuleAudit\Support\AuditedRecords;
use NyonCode\WireModuleAudit\Support\AuditEvents;
use NyonCode\WireModuleAudit\Support\Changes;
use NyonCode\WireModuleAudit\Tests\Fixtures\Invoice;

/*
 * The three things every surface of this module says, and the owners that say
 * them: what happened, what it happened to, and what moved.
 *
 * None of this needs a database, and that is the point of having owners at all —
 * the same answers are needed in a column, a filter, a badge and a detail page,
 * and each of those is an expensive place to discover a disagreement.
 */

afterEach(function () {
    // The morph map is process-wide state; an alias left behind would follow the
    // rest of the suite around.
    Relation::morphMap([], false);
});

it('names every event wire-core records', function () {
    expect(AuditEvents::options())->toHaveCount(5)
        ->and(AuditEvents::options())->toHaveKeys(AuditEvents::TYPES)
        ->and(AuditEvents::label('created'))->toBe('Created')
        // The two the module shipped without: they rendered as their raw
        // database string, with no colour and no filter option.
        ->and(AuditEvents::label('bulk_action'))->toBe('Bulk action')
        ->and(AuditEvents::label('cell_updated'))->toBe('Cell edited');
});

it('reads an application\'s own event type rather than printing the key', function () {
    // The set is open — anything may implement AuditableEvent — so an unknown
    // type gets a readable name and the neutral colour instead of
    // `wire-module-audit::messages.event_invoice_sent` on screen.
    expect(AuditEvents::label('invoice_sent'))->toBe('Invoice Sent')
        ->and(AuditEvents::color('invoice_sent'))->toBe('gray');
});

it('maps colours the way a badge resolves them, and the way core already draws them', function () {
    $colors = AuditEvents::colors();

    // Keyed by the rendered label, because that is the state a BadgeColumn
    // resolves its colour from.
    expect($colors)->toHaveKey('Created')
        ->and($colors['Created'])->toBe('success')
        ->and($colors['Deleted'])->toBe('danger')
        ->and($colors['Bulk action'])->toBe('warning')
        // An edited cell is an update, and core's trail draws both blue.
        ->and($colors['Cell edited'])->toBe($colors['Updated'])
        ->and(AuditEvents::color('deleted'))->toBe('danger');
});

it('names the record an entry is about', function () {
    expect(AuditedRecords::label(Invoice::class, 7))->toBe('Invoice #7')
        // A bulk action records the type it ran over and no id at all.
        ->and(AuditedRecords::label(Invoice::class))->toBe('Invoice')
        ->and(AuditedRecords::label(null))->toBe('')
        ->and(AuditedRecords::modelClass(null))->toBeNull()
        ->and(AuditedRecords::modelClass('not-a-class'))->toBeNull()
        ->and(AuditedRecords::typeLabel(null))->toBe('');
});

it('reads a morph alias back through the map that wrote it', function () {
    // `auditable_type` is written from getMorphClass(), so an application with a
    // morph map stores `invoice` and never the class. Without the map lookup
    // that installation gets an unlabelled, unlinkable log.
    Relation::morphMap(['invoice' => Invoice::class]);

    expect(AuditedRecords::modelClass('invoice'))->toBe(Invoice::class)
        ->and(AuditedRecords::label('invoice', 7))->toBe('Invoice #7');
});

it('labels an alias nobody mapped, rather than refusing to draw the row', function () {
    expect(AuditedRecords::typeLabel('legacy_orders'))->toBe('Legacy Orders')
        ->and(AuditedRecords::urlFor('legacy_orders', 3))->toBeNull();
});

it('pairs what a field was with what it became', function () {
    $entry = new AuditEntry([
        'event' => 'updated',
        'old_values' => ['status' => 'draft', 'total' => 100, 'paid' => false, 'note' => null],
        'new_values' => ['status' => 'sent', 'total' => 100, 'paid' => true, 'note' => 'rush'],
    ]);

    // `total` did not move, so it is not a change — that judgement is core's
    // getChangeDiff(), and this module does not make a second one.
    expect(Changes::fields($entry))->toBe(['status', 'paid', 'note'])
        ->and(Changes::rows($entry)[0])->toBe(['field' => 'status', 'before' => 'draft', 'after' => 'sent'])
        // A boolean reads as one instead of as 1 and an empty cell.
        ->and(Changes::rows($entry)[1])->toBe(['field' => 'paid', 'before' => 'false', 'after' => 'true'])
        // And a value that was not there stays null, so the entry renders core's
        // "(empty)" rather than a blank that reads as an unchanged field.
        ->and(Changes::rows($entry)[2]['before'])->toBeNull();
});

it('renders a stored array as text', function () {
    // What a bulk action records: the ids it ran over.
    $entry = new AuditEntry([
        'event' => 'bulk_action',
        'new_values' => ['action' => 'archive', 'record_ids' => [1, 2, 3]],
    ]);

    expect(Changes::rows($entry))->toBe([
        ['field' => 'action', 'before' => null, 'after' => 'archive'],
        ['field' => 'record_ids', 'before' => null, 'after' => '[1,2,3]'],
    ]);
});

it('has nothing to diff on a model that is not an audit entry', function () {
    expect(Changes::rows(new Invoice))->toBe([])
        ->and(Changes::fields(new Invoice))->toBe([]);
});
