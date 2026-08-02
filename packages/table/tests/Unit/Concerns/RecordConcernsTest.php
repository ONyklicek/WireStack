<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use NyonCode\WireTable\Concerns\EvaluatesRecordClosures;
use NyonCode\WireTable\Concerns\HasRecordVersion;
use NyonCode\WireTable\Concerns\InteractsWithRecordDisabledState;

class RecordConcernsModel extends Model
{
    protected $guarded = [];
}

/** A model that names its timestamp column something other than `updated_at`. */
class RenamedTimestampModel extends Model
{
    public const UPDATED_AT = 'modified_at';

    protected $guarded = [];
}

class RecordDisabledStateHost
{
    use InteractsWithRecordDisabledState;
}

class EvaluatesRecordClosuresHost
{
    use EvaluatesRecordClosures;

    public function call(mixed $value, Model $record): mixed
    {
        return $this->evaluateForRecord($value, $record);
    }
}

class RecordVersionHost
{
    use HasRecordVersion;

    public function version(Model $record): string
    {
        return $this->recordVersion($record);
    }
}

test('record disabled state resolves a static bool', function () {
    $host = new RecordDisabledStateHost;
    $record = new RecordConcernsModel;

    expect($host->isDisabled($record))->toBeFalse();

    expect($host->disabled())->toBe($host);
    expect($host->isDisabled($record))->toBeTrue();

    $host->disabled(false);
    expect($host->isDisabled($record))->toBeFalse();
});

test('record disabled state resolves a per-record closure', function () {
    $host = (new RecordDisabledStateHost)
        ->disabled(fn (Model $record) => $record->getAttribute('locked') === true);

    $locked = (new RecordConcernsModel)->forceFill(['locked' => true]);
    $open = (new RecordConcernsModel)->forceFill(['locked' => false]);

    expect($host->isDisabled($locked))->toBeTrue()
        ->and($host->isDisabled($open))->toBeFalse();
});

test('evaluateForRecord returns static values as-is and invokes closures with record + column', function () {
    $host = new EvaluatesRecordClosuresHost;
    $record = (new RecordConcernsModel)->forceFill(['name' => 'Ada']);

    expect($host->call('static', $record))->toBe('static');

    expect($host->call(fn (Model $r, $column) => $r->getAttribute('name').':'.get_class($column), $record))
        ->toBe('Ada:'.EvaluatesRecordClosuresHost::class);
});

test('record version is the updated_at timestamp, or 0 when not timestamped', function () {
    $host = new RecordVersionHost;

    $stamped = (new RecordConcernsModel)->forceFill(['updated_at' => Carbon::createFromTimestamp(1717171717)]);
    expect($host->version($stamped))->toBe('1717171717');

    expect($host->version(new RecordConcernsModel))->toBe('0');
});

test('record version follows a model that renames its timestamp column', function () {
    // The '0' this used to return is the client's "I never had a version"
    // sentinel, and RecordVersion::conflicts() reads it as "nothing to compare"
    // — so a renamed timestamp column did not merely leave the cell unstamped,
    // it turned the optimistic lock OFF for every inline edit on that model,
    // silently. The server has always resolved the column properly; the render
    // side read the literal attribute and disagreed.
    $host = new RecordVersionHost;

    $record = (new RenamedTimestampModel)->forceFill([
        'modified_at' => Carbon::createFromTimestamp(1717171717),
    ]);

    expect($host->version($record))->toBe('1717171717')
        ->and($host->version($record))->not->toBe('0');
});
