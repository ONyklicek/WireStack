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
