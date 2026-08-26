<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Support\RowStamps;

/*
 * The decision that gates the whole row-partial path: which rows moved, and
 * whether a partial can describe the move at all.
 *
 * It used to be reachable only through a Livewire poll in a browser, where the
 * three outcomes are indistinguishable from the outside — a full render, a
 * partial render and no render at all all look like "the table is fine". These
 * tests separate them.
 */

class RsRow extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

function rsRow(int $id, string $name): RsRow
{
    return new RsRow(['id' => $id, 'name' => $name]);
}

it('stamps each row by its attributes, keyed by primary key', function () {
    $stamps = RowStamps::of([rsRow(1, 'a'), rsRow(2, 'b')], 'id');

    // Int keys, not string: PHP casts a numeric string array key to an int, and
    // primary keys usually are numeric. The caller compares these against keys
    // of the same array, so both sides agree — but the type is worth pinning,
    // because the partial name is built by concatenating one.
    expect(array_keys($stamps))->toBe([1, 2])
        ->and($stamps[1])->toBeInt()
        ->and('row-'.array_key_first($stamps))->toBe('row-1');
});

it('stamps two loads of an unchanged row identically', function () {
    // The stamp is over the attributes, not the object — otherwise every poll
    // would look like a change and the whole path would be pointless.
    expect(RowStamps::of([rsRow(1, 'a')], 'id'))->toBe(RowStamps::of([rsRow(1, 'a')], 'id'));
});

it('stamps a changed row differently', function () {
    expect(RowStamps::of([rsRow(1, 'a')], 'id'))->not->toBe(RowStamps::of([rsRow(1, 'b')], 'id'));
});

it('names the rows whose stamp moved', function () {
    $before = RowStamps::of([rsRow(1, 'a'), rsRow(2, 'b'), rsRow(3, 'c')], 'id');
    $after = RowStamps::of([rsRow(1, 'a'), rsRow(2, 'CHANGED'), rsRow(3, 'c')], 'id');

    expect(RowStamps::changed($before, $after))->toBe([2]);
});

it('answers an empty list when nothing moved, which is not the same as null', function () {
    // Empty means "render nothing"; null means "render everything". A caller
    // that conflates them either re-renders a table nobody touched or fails to
    // re-render one that changed shape.
    $stamps = RowStamps::of([rsRow(1, 'a')], 'id');

    expect(RowStamps::changed($stamps, $stamps))->toBe([])
        ->and(RowStamps::changed($stamps, $stamps))->not->toBeNull();
});

it('refuses to describe a page whose rows are not the same rows', function () {
    $before = RowStamps::of([rsRow(1, 'a'), rsRow(2, 'b')], 'id');

    // A row left.
    expect(RowStamps::changed($before, RowStamps::of([rsRow(1, 'a')], 'id')))->toBeNull()
        // A row arrived.
        ->and(RowStamps::changed($before, RowStamps::of([rsRow(1, 'a'), rsRow(2, 'b'), rsRow(3, 'c')], 'id')))->toBeNull()
        // The same rows in a different order — the sort moved them, and a
        // per-row partial cannot say so.
        ->and(RowStamps::changed($before, RowStamps::of([rsRow(2, 'b'), rsRow(1, 'a')], 'id')))->toBeNull();
});

it('treats a first poll as a page that cannot be described', function () {
    // Nothing to compare against is the same situation as the page changing.
    expect(RowStamps::changed(null, RowStamps::of([rsRow(1, 'a')], 'id')))->toBeNull()
        ->and(RowStamps::changed('not-an-array', RowStamps::of([rsRow(1, 'a')], 'id')))->toBeNull();
});

it('handles an empty page without calling it a change', function () {
    expect(RowStamps::of([], 'id'))->toBe([])
        ->and(RowStamps::changed([], []))->toBe([]);
});
