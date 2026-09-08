<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Foundation\Icons\WireIconSet;

/*
 * The framework's own glyph set.
 *
 * It exists because the rating star crosses a package boundary: wire-forms draws
 * it in the `Rating` field, wire-table in the read-only `RatingColumn`. Held by
 * either package it is the other one's dependency on a neighbour's private art,
 * and for a while the two drew different stars — the same rating looking like two
 * different things depending on whether you could edit it.
 */

it('is always available, whatever the consumer configured', function () {
    // Registered in the IconManager constructor rather than from config or a
    // package's boot, for the same reason `outline:` is: a published config that
    // predates the set must not make a rating render the missing-icon
    // placeholder.
    $icons = new IconManager;

    expect($icons->has('wire:star'))->toBeTrue()
        ->and($icons->has('wire:star-outline'))->toBeTrue()
        ->and($icons->has('wire:nope'))->toBeFalse();
});

it('describes its own format, so the glyphs are not scaled as 20x20 solid', function () {
    $set = new WireIconSet;
    $icon = $set->getIcon('star');

    expect($icon)->not->toBeNull()
        ->and($icon->viewBox)->toBe('0 0 24 24')
        ->and($icon->attributes)->toBe(['fill' => 'currentColor'])
        ->and($set->getIcon('nope'))->toBeNull()
        ->and($set->getPath('nope'))->toBeNull()
        ->and($set->names())->toBe(['star', 'star-outline']);
});

it('outlines its star by stroking the path, not by a second glyph', function () {
    // The half state is drawn by clipping a filled star over the empty one, so
    // the set needs two entries and not three — and the outline says `fill=none`
    // on its own path, since the set's root is fill-based.
    expect((new WireIconSet)->getPath('star-outline'))
        ->toContain('fill="none"')
        ->toContain('stroke="currentColor"');
});

it('stays smaller than the Heroicon it replaced, which is the whole reason', function () {
    // The Rating field emits all three states of every position and switches them
    // with x-show: twenty <svg> per field at the default max. Heroicons' star
    // measured 14 718 B per field against a 10 400 ceiling in
    // FormFieldPayloadTest, which is what sent both surfaces here.
    $set = new WireIconSet;
    $heroicon = app(IconManager::class)->resolve('star');

    expect(strlen((string) $set->getPath('star')))
        ->toBeLessThan(strlen($heroicon->body) / 2);
});
