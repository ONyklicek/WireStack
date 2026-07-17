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
