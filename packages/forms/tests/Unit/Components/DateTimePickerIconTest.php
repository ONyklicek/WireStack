<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use NyonCode\WireForms\Components\DateTimePicker;

/**
 * DateTimePicker 3d (render-optimization-audit-2026-07-17.md): the 8 calendar/time
 * chevrons moved from `<x-wire::icon>` (a Blade component = one view render each) to
 * the `@icon` directive (a cached IconManager string, zero view renders), and the
 * floating-assets scaffolding is `@once`. This is the standard's "make the render
 * cheap eagerly" — the closed calendar chrome now costs no per-field icon renders.
 */
function dtpIconRenders(DateTimePicker $field): int
{
    $count = 0;
    View::composer('wire-core::foundation.icon', function () use (&$count): void {
        $count++;
    });

    view($field->render()->name(), ['field' => $field])->withErrors(new MessageBag)->render();

    return $count;
}

it('renders the calendar chevrons without any icon-component view render', function () {
    // 8 chevrons (month nav + time steppers) used to each render foundation.icon;
    // now they are @icon strings.
    expect(dtpIconRenders(DateTimePicker::make('published_at')))->toBe(0);
});

it('still emits the chevron SVGs into the calendar markup', function () {
    $html = view(DateTimePicker::make('published_at')->render()->name(), [
        'field' => DateTimePicker::make('published_at'),
    ])->withErrors(new MessageBag)->render();

    // Fewer renders must not mean missing chrome: the chevrons reach the DOM as <svg>
    // (the exact count depends on which time steppers the config enables).
    expect(substr_count($html, '<svg'))->toBeGreaterThanOrEqual(6);
});
