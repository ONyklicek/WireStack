<?php

declare(strict_types=1);

use NyonCode\WireTable\Columns\TextColumn;
use Workbench\App\Models\User;

/*
 * A status icon's colour is a property of the row, not of the column.
 *
 * `color()` is resolved once for the whole column, which is right for a text
 * tint and wrong for the icon beside a state: green for a payment received, red
 * for one refused. Before `iconColor()` a table could render either every icon
 * in one colour or none at all — so every status icon in the repository was
 * grey, and looked it.
 */
function iconCell(TextColumn $column, User $record): string
{
    return $column->renderCell($record);
}

it('tints the icon per record', function () {
    $column = TextColumn::make('name')
        ->icon('check-circle')
        ->iconColor(fn (User $record): string => $record->name === 'Ada' ? 'success' : 'danger');

    $ada = new User(['name' => 'Ada']);
    $grace = new User(['name' => 'Grace']);

    // Same column, same icon, two colours — which is the whole point, and the
    // thing a static memo would have got wrong by serving row two row one's.
    expect(iconCell($column, $ada))->toContain('emerald')
        ->and(iconCell($column, $grace))->toContain('red')
        ->and(iconCell($column, $ada))->not->toContain('red');
});

it('falls back to the column colour, and then to grey', function () {
    $record = new User(['name' => 'Ada']);

    // Nothing changes for a column that never asks: this is exactly what it
    // rendered before the capability existed.
    expect(iconCell(TextColumn::make('name')->icon('check-circle')->color('primary'), $record))
        ->toContain('primary')
        ->and(iconCell(TextColumn::make('name')->icon('check-circle'), $record))
        ->toContain('text-gray-400');
});

it('takes a plain role as well as a closure', function () {
    expect(iconCell(TextColumn::make('name')->icon('check-circle')->iconColor('warning'), new User(['name' => 'Ada'])))
        ->toContain('amber');
});

it('lets the icon colour win over the column colour', function () {
    // The two are different questions — the text is the column's, the icon is
    // the row's — so the more specific one answers.
    expect(iconCell(TextColumn::make('name')->icon('check-circle')->color('primary')->iconColor('danger'), new User(['name' => 'Ada'])))
        ->toContain('red');
});

/*
 * ─── Dlaždice ───────────────────────────────────────────────────
 *
 * A bare tinted glyph is enough on a grid of columns, where the row is already a
 * line of aligned values. In a list, where the record is a sentence, the tile is
 * what gives the rows a left edge for the eye to run down.
 */

it('seats the icon in a tile tinted by the same role', function () {
    $column = TextColumn::make('name')
        ->icon('check-circle')
        ->iconColor(fn (User $record): string => $record->name === 'Ada' ? 'success' : 'danger')
        ->iconTile();

    $ada = iconCell($column, new User(['name' => 'Ada']));
    $grace = iconCell($column, new User(['name' => 'Grace']));

    // Ground and ink are one decision: the tile carries the colour and the glyph
    // inherits it, so the two cannot drift apart.
    expect($ada)->toContain('bg-emerald-100')
        ->and($ada)->toContain('text-emerald-600')
        ->and($grace)->toContain('bg-red-100')
        ->and($grace)->toContain('rounded-[10px]');
});

it('gives a tile only to a role that means something', function () {
    // A tile is a statement about kind — done, waiting, failed. A raw hue makes
    // no such statement, so it lands on the neutral tile rather than inventing
    // a meaning for itself.
    $record = new User(['name' => 'Ada']);

    expect(iconCell(TextColumn::make('name')->icon('check-circle')->iconColor('fuchsia')->iconTile(), $record))
        ->toContain('bg-gray-100')
        ->and(iconCell(TextColumn::make('name')->icon('check-circle')->iconTile(), $record))
        ->toContain('bg-gray-100');
});

it('leaves the bare icon alone unless a column asks for the tile', function () {
    $bare = iconCell(TextColumn::make('name')->icon('check-circle')->iconColor('success'), new User(['name' => 'Ada']));

    expect($bare)->not->toContain('rounded-[10px]')
        ->and($bare)->toContain('emerald');
});
