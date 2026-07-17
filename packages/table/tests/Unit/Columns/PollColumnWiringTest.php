<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\PollColumn;

/*
 * refreshMethod() and keepContentWhileLoading() were dead setters: the poll
 * directive hardcoded refreshRow(), and the stale value was always shown next to
 * the spinner.
 */

function pollRecord(array $attributes = ['id' => 3, 'status' => 'pending']): Model
{
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill($attributes);

    return $record;
}

it('polls the host method refreshRow by default', function () {
    $html = PollColumn::make('status')->pollWhilePending()->renderCell(pollRecord());

    expect($html)->toContain("refreshRow('3')");
});

it('polls the method named by refreshMethod', function () {
    $html = PollColumn::make('status')->pollWhilePending()->refreshMethod('reloadJob')->renderCell(pollRecord());

    expect($html)->toContain("reloadJob('3')")
        ->and($html)->not->toContain('refreshRow');
});

// The loading indicator is on by default, so these only vary the flag under test.
it('keeps the value visible while refreshing by default', function () {
    $html = PollColumn::make('status')->pollWhilePending()->renderCell(pollRecord());

    expect($html)->toContain('wire:loading')
        ->and($html)->not->toContain('wire:loading.remove');
});

it('hides the stale value while refreshing when asked to', function () {
    $html = PollColumn::make('status')->pollWhilePending()
        ->keepContentWhileLoading(false)->renderCell(pollRecord());

    expect($html)->toContain('wire:loading.remove');
});

// maxPolls() advertised a safety cap and enforced nothing: a column cannot see
// how many times it has polled — that count would have to live in the host, and
// no host ever kept one. onComplete() had the same problem in reverse: without
// per-record polling memory it could not tell "just finished" from "was already
// finished", so it would have fired on every render of a completed row.
// Both removed rather than half-built; stopWhen()/pollWhile()/stopOnComplete()
// are the conditions the column can actually evaluate.
it('exposes no poll cap or completion callback it cannot honour', function () {
    expect(method_exists(PollColumn::class, 'maxPolls'))->toBeFalse()
        ->and(method_exists(PollColumn::class, 'onComplete'))->toBeFalse()
        ->and(method_exists(PollColumn::class, 'handlePollComplete'))->toBeFalse();
});
