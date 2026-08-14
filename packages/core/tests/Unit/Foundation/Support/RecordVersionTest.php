<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Support\RecordVersion;

/*
 * The optimistic-locking convention every inline-edit surface shares. It was
 * written out twice — table and panel — and the three rules below all had to
 * agree across both without anything checking that they did.
 */

class RvRecord extends Model
{
    protected $guarded = [];
}

class RvCustomStamp extends Model
{
    const UPDATED_AT = 'modified_at';

    protected $guarded = [];
}

beforeEach(function () {
    $this->version = new RecordVersion;
});

test('the stamp is the updated_at timestamp as a string', function () {
    $record = (new RvRecord)->forceFill(['updated_at' => now()]);

    expect($this->version->stamp($record))->toBe((string) $record->updated_at->getTimestamp());
});

test('a record with no timestamp has no version', function () {
    expect($this->version->stamp(new RvRecord))->toBeNull();
});

test('a moved record conflicts', function () {
    $record = (new RvRecord)->forceFill(['updated_at' => now()]);

    expect($this->version->conflicts($record, '1'))->toBeTrue()
        ->and($this->version->conflicts($record, $this->version->stamp($record)))->toBeFalse();
});

test('the zero sentinel means the client never had a version', function () {
    // Not a timestamp — treating it as one would reject every first edit.
    $record = (new RvRecord)->forceFill(['updated_at' => now()]);

    expect($this->version->conflicts($record, '0'))->toBeFalse()
        ->and($this->version->conflicts($record, null))->toBeFalse();
});

test('an untimestamped record cannot conflict', function () {
    // Nothing to compare against, so the edit proceeds.
    expect($this->version->conflicts(new RvRecord, '12345'))->toBeFalse();
});

test('a custom UPDATED_AT column is still versioned', function () {
    // The hand-rolled copies read ->updated_at literally, so a model naming the
    // column something else had no version — and was therefore unguarded.
    $record = (new RvCustomStamp)->forceFill(['modified_at' => now()]);

    expect($this->version->stamp($record))->toBe((string) $record->modified_at->getTimestamp())
        ->and($this->version->conflicts($record, '1'))->toBeTrue();
});

/*
 * Edits the SAME request made are not someone else's edit.
 *
 * Livewire bundles calls made in one tick into one request and runs them in
 * order, so tabbing out of one cell straight into another on the same row sends
 * both together. The first write moves `updated_at`; the second still carries the
 * stamp from the render and, compared only against the record's current value,
 * looks stale — and used to be refused as "modified by another user".
 *
 * The record is re-created between the calls on purpose: each call resolves and
 * locks the row for itself, so the second sees a different object with a newer
 * stamp, which is exactly what the baseline has to survive.
 */
test('a version this request superseded itself is not a conflict', function () {
    $record = (new RvRecord)->forceFill(['id' => 7, 'updated_at' => now()->subMinute()]);
    $held = $this->version->stamp($record);

    // The request's first write: the client is up to date, so this is allowed —
    // and it is what records the baseline.
    expect($this->version->conflicts($record, $held))->toBeFalse();

    // ...and it moved the record. A sibling cell rendered at the same time is
    // still holding the older stamp; the broadcast that would teach it the new
    // one fires from the response handler, far too late for this request.
    $moved = (new RvRecord)->forceFill(['id' => 7, 'updated_at' => now()]);

    expect($this->version->stamp($moved))->not->toBe($held)
        ->and($this->version->conflicts($moved, $held))->toBeFalse();
});

test('a version that was never current is still a conflict after our own write', function () {
    // The forgiveness is for ONE stamp — the one the record carried when the
    // request opened. Anything else is as stale as it ever was.
    $record = (new RvRecord)->forceFill(['id' => 8, 'updated_at' => now()->subMinute()]);
    $this->version->conflicts($record, $this->version->stamp($record));

    $moved = (new RvRecord)->forceFill(['id' => 8, 'updated_at' => now()]);

    expect($this->version->conflicts($moved, '1'))->toBeTrue();
});

test('a record another request moved before this one is still a conflict', function () {
    // The cross-client guarantee, which is the whole point of the lock: the first
    // stamp this request sees already includes the other write, so the client's
    // older version matches neither it nor the baseline.
    $record = (new RvRecord)->forceFill(['id' => 9, 'updated_at' => now()]);
    $stale = (string) now()->subHour()->getTimestamp();

    expect($this->version->conflicts($record, $stale))->toBeTrue();
});

test('the baselines do not outlive a flush', function () {
    // Only Octane reaches this: the singleton outlives the request there, and a
    // baseline held into the next one would forgive a genuinely stale version.
    $record = (new RvRecord)->forceFill(['id' => 10, 'updated_at' => now()->subMinute()]);
    $held = $this->version->stamp($record);
    $this->version->conflicts($record, $held);

    $moved = (new RvRecord)->forceFill(['id' => 10, 'updated_at' => now()]);
    expect($this->version->conflicts($moved, $held))->toBeFalse();

    $this->version->flush();

    expect($this->version->conflicts($moved, $held))->toBeTrue();
});
