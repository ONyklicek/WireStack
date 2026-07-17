<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\IconColumn;

/*
 * A BadgeColumn or IconColumn pointed at an ordinary JSON-cast attribute took
 * the whole table render down: getIconForState() indexed its map with an
 * unguarded isset(), and isset($map[$array]) throws rather than answering false.
 * No icons() call was needed to trigger it. Column::formatValue() had already
 * been hardened for exactly this state; the icon ladder was missed.
 *
 * These drive renderCell(), not the resolver — the resolver is not where it broke.
 */

class SifTicket extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}

function sifRecord(): SifTicket
{
    // Pass the array itself: forceFill() with a JSON string double-encodes it
    // under an array cast, and the state arrives back as a string.
    return (new SifTicket)->forceFill(['meta' => ['a' => 1]]);
}

test('a badge cell over a json-cast attribute renders instead of fatalling', function () {
    expect(BadgeColumn::make('meta')->renderCell(sifRecord()))->toBeString();
});

test('an icon cell over a json-cast attribute renders instead of fatalling', function () {
    expect(IconColumn::make('meta')->renderCell(sifRecord()))->toBeString();
});

test('a non-scalar state resolves to no icon rather than throwing', function () {
    expect(BadgeColumn::make('meta')->getIconForState(['a' => 1]))->toBeNull()
        ->and(IconColumn::make('meta')->getIconForState(new stdClass))->toBeNull();
});

test('an unmapped state falls back to the column icon', function () {
    // ->icon() used to be dead on these columns: nothing read getIcon().
    expect(BadgeColumn::make('status')->icon('star')->getIconForState('unknown'))->toBe('star')
        ->and(BadgeColumn::make('status')->getIconForState('unknown'))->toBeNull();
});
