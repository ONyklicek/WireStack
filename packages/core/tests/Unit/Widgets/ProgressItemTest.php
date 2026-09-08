<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Widgets\ProgressItem;

// ─── Construction ────────────────────────────────────────────────────────────

it('carries a label and defaults to a target of 100', function () {
    $item = ProgressItem::make('New MRR');

    expect($item->getLabel())->toBe('New MRR')
        ->and($item->getValue())->toBe(0.0)
        ->and($item->getTarget())->toBe(100.0);
});

it('accepts ints and floats for both readings', function () {
    $item = ProgressItem::make('Storage')->value(3)->target(7.5);

    expect($item->getValue())->toBe(3.0)
        ->and($item->getTarget())->toBe(7.5);
});

// ─── The fraction ────────────────────────────────────────────────────────────

it('computes the fraction of the target reached', function () {
    expect(ProgressItem::make('a')->value(30)->target(120)->getPercentage())->toBe(25.0);
});

it('clamps an over-delivered target to a full bar', function () {
    // Not a bar wider than its track: the fill is a width in a fixed box.
    expect(ProgressItem::make('a')->value(300)->target(120)->getPercentage())->toBe(100.0);
});

it('clamps a negative reading to an empty bar', function () {
    expect(ProgressItem::make('a')->value(-40)->target(120)->getPercentage())->toBe(0.0);
});

it('reads a target of zero as nothing achieved, not as complete', function () {
    // "0 of 0" is an unconfigured row far more often than a finished one, and a
    // full bar would announce a success nobody had.
    expect(ProgressItem::make('a')->value(0)->target(0)->getPercentage())->toBe(0.0)
        ->and(ProgressItem::make('a')->value(5)->target(-1)->getPercentage())->toBe(0.0);
});

it('knows when the reading has reached its target', function () {
    expect(ProgressItem::make('a')->value(120)->target(120)->isComplete())->toBeTrue()
        ->and(ProgressItem::make('a')->value(119)->target(120)->isComplete())->toBeFalse();
});

// ─── What the row prints ─────────────────────────────────────────────────────

it('prints the rounded percentage by default', function () {
    // Deliberately not number_format($value): a separator and a decimal mark are
    // a locale decision a widget must not make for the caller.
    expect(ProgressItem::make('a')->value(30)->target(120)->getFormattedValue())->toBe('25%');
});

it('prints whatever the caller formatted instead', function () {
    expect(ProgressItem::make('a')->value(30)->formattedValue('1.2M / 2M')->getFormattedValue())
        ->toBe('1.2M / 2M');
});

// ─── Presentation ────────────────────────────────────────────────────────────

it('routes the bar colour through the canonical palette', function () {
    $item = ProgressItem::make('a')->color('success');

    expect($item->getBarColorClass())->toContain('bg-')
        // No caller-supplied name reaches Tailwind verbatim.
        ->and(ProgressItem::make('a')->color('not-a-colour')->getBarColorClass())
        ->not->toContain('not-a-colour');
});

it('accents the printed value with the row colour', function () {
    $item = ProgressItem::make('a')->color('success');

    expect($item->getValueColorClass())->toContain('text-')
        ->and($item->getValueColorClass())->not->toBe('text-gray-900 dark:text-white')
        ->and(ProgressItem::make('a')->color('not-a-colour')->getValueColorClass())
        ->not->toContain('not-a-colour');
});

it('falls back to primary for the bar and to heading text for the value', function () {
    $item = ProgressItem::make('a');

    expect($item->getBarColorClass())->toBe(ProgressItem::make('b')->color('primary')->getBarColorClass())
        ->and($item->getValueColorClass())->toBe('text-gray-900 dark:text-white');
});

it('accepts an Icon case as well as a name', function () {
    expect(ProgressItem::make('a')->icon('outline:chart-bar')->getIcon())->toBe('outline:chart-bar')
        ->and(ProgressItem::make('a')->icon(Icon::chartBar)->getIcon())->toBe(Icon::chartBar->value())
        ->and(ProgressItem::make('a')->icon(null)->getIcon())->toBeNull();
});

it('carries a description and extra attributes', function () {
    $item = ProgressItem::make('a')
        ->description('vs. last quarter')
        ->extraAttributes(['data-testid' => 'mrr']);

    expect($item->getDescription())->toBe('vs. last quarter')
        ->and($item->getExtraAttributes())->toBe(['data-testid' => 'mrr']);
});
