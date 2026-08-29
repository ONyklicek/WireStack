<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\TextColumn;

/*
 * A per-width closure supplies the cell's CONTENT, not the whole cell.
 *
 * `mobileDisplayUsing()` used to return bare escaped text: no record link, no
 * icon, no size class, no copy button, no description. The desktop half of the
 * same column, meanwhile, fell through to renderCell() and kept all of it — so
 * one column rendered with a link and a copy button on a desktop and without
 * them on a phone, which is the width a thumb actually needs them on. A stacked
 * card was worse still: CardRenderer calls renderMobileCell() directly, so a
 * responsive column sat next to an ordinary one that had kept its chrome.
 *
 * Nothing caught it. The existing test asserts the closure's output reaches the
 * markup ("M:Ada"), which was true either way, and every example in the docs
 * declares BOTH closures — which strips both halves symmetrically and hides it.
 *
 * The rule is the one `displayUsing()` already followed: a closure replaces what
 * sits inside the cell, and the chrome the column declared wraps it.
 */

class RccRow extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

function rccRecord(): RccRow
{
    return new RccRow(['name' => 'Ada', 'id' => 1]);
}

/** A column carrying every affordance a cell can have. */
function rccColumn(): TextColumn
{
    return TextColumn::make('name')
        ->textSize('lg')
        ->actionUrl(fn (RccRow $record): string => '/rows/'.$record->id)
        ->copyable()
        ->description('a description');
}

it('keeps the cell chrome on the phone half', function () {
    $html = rccColumn()
        ->mobileDisplayUsing(fn (mixed $state): string => 'M:'.$state)
        ->renderMobileCell(rccRecord());

    expect($html)
        // The closure's content is there…
        ->toContain('M:Ada')
        // …inside the cell the column declared, not instead of it.
        ->toContain('href="/rows/1"')
        ->toContain('data-testid="cell-copy"')
        ->toContain('text-lg')
        ->toContain('a description');
});

it('keeps it on the desktop half too', function () {
    $html = rccColumn()
        ->desktopDisplayUsing(fn (mixed $state): string => 'D:'.$state)
        ->renderDesktopCell(rccRecord());

    expect($html)->toContain('D:Ada')
        ->toContain('href="/rows/1"')
        ->toContain('data-testid="cell-copy"');
});

it('gives both halves the same chrome when only one closure is declared', function () {
    // The asymmetric case, which is where this was visible: one half went through
    // the closure and lost everything, the other fell through to renderCell().
    $column = rccColumn()->mobileDisplayUsing(fn (mixed $state): string => 'M:'.$state);

    $mobile = $column->renderMobileCell(rccRecord());
    $desktop = $column->renderDesktopCell(rccRecord());

    foreach (['href="/rows/1"', 'data-testid="cell-copy"', 'text-lg', 'a description'] as $chrome) {
        expect($mobile)->toContain($chrome)
            ->and($desktop)->toContain($chrome);
    }

    // Only the content differs, which is the whole point of declaring a variant.
    expect($mobile)->toContain('M:Ada')
        ->and($desktop)->toContain('Ada')
        ->not->toContain('M:Ada');
});

it('falls back to the general display closure for the width that has none', function () {
    $column = TextColumn::make('name')
        ->displayUsing(fn (mixed $state): string => 'G:'.$state)
        ->mobileDisplayUsing(fn (mixed $state): string => 'M:'.$state);

    // A width without its own variant is not a width without formatting.
    expect($column->renderMobileCell(rccRecord()))->toContain('M:Ada')
        ->and($column->renderDesktopCell(rccRecord()))->toContain('G:Ada');
});

it('escapes a closure result unless the column opted into html', function () {
    $raw = fn (mixed $state): string => '<b>'.$state.'</b>';

    expect(TextColumn::make('name')->mobileDisplayUsing($raw)->renderMobileCell(rccRecord()))
        ->toContain('&lt;b&gt;Ada&lt;/b&gt;')
        ->and(TextColumn::make('name')->html()->mobileDisplayUsing($raw)->renderMobileCell(rccRecord()))
        ->toContain('<b>Ada</b>');
});

it('hands the closure the state, the record and the column', function () {
    $seen = [];

    TextColumn::make('name')
        ->mobileDisplayUsing(function (mixed $state, mixed $record, mixed $column) use (&$seen): string {
            $seen = [$state, $record::class, $column::class];

            return (string) $state;
        })
        ->renderMobileCell(rccRecord());

    // The three-argument signature the docs teach is unchanged.
    expect($seen)->toBe(['Ada', RccRow::class, TextColumn::class]);
});

it('still wraps the two halves only when they differ', function () {
    $differing = rccColumn()->mobileDisplayUsing(fn (mixed $state): string => 'M:'.$state);
    $same = TextColumn::make('name')->mobileDisplayUsing(fn (mixed $state): string => (string) $state);

    // Two shapes in the document, swapped by the breakpoint…
    expect($differing->renderResponsiveCell(rccRecord()))
        ->toContain('md:hidden')
        ->toContain('hidden md:inline')
        // …but a variant that renders identically is emitted once, as before.
        ->and($same->renderResponsiveCell(rccRecord()))->not->toContain('md:hidden');
});

it('is still nothing at all when the record hides the cell', function () {
    $hidden = rccColumn()
        ->mobileDisplayUsing(fn (mixed $state): string => 'M:'.$state)
        ->visibleForRecord(fn (): bool => false);

    // All three entry points agree: the fast path guards the same way.
    expect($hidden->renderMobileCell(rccRecord()))->toBe('')
        ->and($hidden->renderDesktopCell(rccRecord()))->toBe('')
        ->and($hidden->renderCellFast(rccRecord()))->toBe('');
});
