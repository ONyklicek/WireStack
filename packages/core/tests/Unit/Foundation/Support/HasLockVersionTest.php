<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Foundation\Support\HasLockVersion;
use NyonCode\WireCore\Foundation\Support\RecordVersion;

/*
 * The exact half of the optimistic lock. `updated_at` is stored to the second,
 * so on its own it cannot tell two writes inside one second apart — the case a
 * grid that several people fill in produces all day.
 */

class LvRecord extends Model
{
    use HasLockVersion;

    protected $table = 'lv_records';

    protected $guarded = [];
}

class LvCustomColumn extends Model
{
    use HasLockVersion;

    protected $table = 'lv_records';

    protected $guarded = [];

    protected string $lockVersionColumn = 'revision';
}

beforeEach(function () {
    Schema::create('lv_records', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('city')->nullable();
        $table->unsignedBigInteger('lock_version')->default(0);
        $table->unsignedBigInteger('revision')->default(0);
        $table->timestamps();
    });

    $this->version = new RecordVersion;
});

afterEach(function () {
    Schema::dropIfExists('lv_records');
});

it('starts a new record at version 1', function () {
    expect(LvRecord::create(['name' => 'a'])->lock_version)->toBe(1);
});

it('raises the version by one on every update and the model holds the real number', function () {
    $record = LvRecord::create(['name' => 'a']);

    $record->update(['name' => 'b']);
    $record->update(['city' => 'Brno']);

    expect($record->lock_version)->toBe(3)
        ->and($record->fresh()->lock_version)->toBe(3)
        ->and($record->isDirty())->toBeFalse();
});

it('does not raise the version for a save that changed nothing', function () {
    $record = LvRecord::create(['name' => 'a']);

    $record->save();

    expect($record->fresh()->lock_version)->toBe(1);
});

it('raises the version in the database, not in PHP, so two concurrent writes get two versions', function () {
    // Two copies loaded at the same version, both saved without a row lock —
    // computing `N + 1` in PHP would give both the same number.
    $first = LvRecord::create(['name' => 'a']);
    $second = LvRecord::find($first->id);

    $first->update(['name' => 'b']);
    $second->update(['city' => 'Brno']);

    expect($first->lock_version)->toBe(2)
        ->and($second->lock_version)->toBe(3)
        ->and($first->fresh()->lock_version)->toBe(3);
});

it('reports the changes with the written number, not the expression', function () {
    $record = LvRecord::create(['name' => 'a']);

    $record->update(['name' => 'b']);

    expect($record->getChanges())->toMatchArray(['name' => 'b', 'lock_version' => 2]);
});

it('honours a custom column name', function () {
    $record = LvCustomColumn::create(['name' => 'a']);

    $record->update(['name' => 'b']);

    expect($record->revision)->toBe(2)
        ->and($record->fresh()->lock_version)->toBe(0);
});

it('puts the version into the stamp beside updated_at', function () {
    $record = LvRecord::create(['name' => 'a']);

    expect($this->version->stamp($record))->toBe('1.'.$record->updated_at->getTimestamp());
});

it('catches a second write inside the same second as a conflict', function () {
    $this->freezeSecond();

    $record = LvRecord::create(['name' => 'a']);
    $held = $this->version->stamp($record);

    // Somebody else, in the same second: `updated_at` does not move at all.
    LvRecord::find($record->id)->update(['city' => 'Brno']);

    $current = LvRecord::find($record->id);

    expect($current->updated_at->getTimestamp())->toBe($record->updated_at->getTimestamp())
        ->and($this->version->conflicts($current, $held))->toBeTrue();
});

it('still catches a query-builder write, which raises no version', function () {
    $record = LvRecord::create(['name' => 'a']);
    $held = $this->version->stamp($record);

    $this->travel(5)->seconds();
    LvRecord::query()->whereKey($record->id)->update(['city' => 'Brno']);

    $current = LvRecord::find($record->id);

    expect($current->lock_version)->toBe(1)
        ->and($this->version->conflicts($current, $held))->toBeTrue();
});

it('does not read an earlier write of the same request as a conflict', function () {
    $record = LvRecord::create(['name' => 'a']);
    $held = $this->version->stamp($record);

    // First call of the request: checks, then writes.
    $locked = LvRecord::find($record->id);
    expect($this->version->conflicts($locked, $held))->toBeFalse();
    $locked->update(['name' => 'b']);

    // Second call of the same request, carrying the render's version.
    expect($this->version->conflicts(LvRecord::find($record->id), $held))->toBeFalse();
});

it('falls back to updated_at alone when the version is not loaded', function () {
    $record = (new LvRecord)->forceFill(['updated_at' => now()]);

    expect($this->version->stamp($record))->toBe((string) $record->updated_at->getTimestamp());
});
