<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use NyonCode\WireCore\Foundation\Enums\Density;
use NyonCode\WireCore\Foundation\Enums\Shape;

/**
 * Through a request rather than `Blade::render()`, for the reason LayoutTest
 * records: rendering a slotted layout by hand leaks an output buffer per slot.
 */
function densityHtml(): string
{
    View::addLocation(__DIR__.'/../fixtures/views');
    Route::get('/density-probe', fn () => view('bare'));

    return test()->get('/density-probe')->getContent();
}

/**
 * The fixed half of compact: a config value reaching the document.
 *
 * The rules themselves are CSS and only a browser can judge them —
 * `workbench/scripts/verify-density.mjs` measures a table row tightening and the
 * top bar holding. What is checkable here is the wiring: that the attribute the
 * rules key on says what the application asked for, and that the rules are on
 * the page at all.
 */
it('resolves the shipped spacing when nothing is configured', function () {
    expect(Density::configured())->toBe(Density::Normal);
});

it('resolves what the application asked for', function () {
    config()->set('wire-core.density', 'compact');

    expect(Density::configured())->toBe(Density::Compact);
});

it('falls back to normal for a value nobody recognises', function () {
    // A typo must not leave the page in an unnamed state.
    config()->set('wire-core.density', 'cozy');

    expect(Density::configured())->toBe(Density::Normal)
        ->and(Density::resolve(null))->toBe(Density::Normal);
});

it('puts the choice on the document, where the rules key on it', function () {
    config()->set('wire-core.density', 'compact');

    expect(densityHtml())->toContain('data-density="compact"');
});

it('carries the rules themselves, not only the attribute', function () {
    // An attribute with no stylesheet behind it is a page that says it is
    // compact and is not.
    $html = densityHtml();

    expect($html)->toContain('data-wire-density')
        ->and($html)->toContain('--spacing');
});

it('stops at the chrome on a phone, where a thumb needs the target the switch just took away', function () {
    // At 0.175rem the menu handle, the bell, both switches and a notification
    // row's verbs all came out 27 pixels square, and the drawer 202 instead of
    // 288 — a menu you have to aim at. Compact tightens what you read on a phone,
    // not what you touch.
    $html = densityHtml();

    expect($html)->toContain('@media (width < 40rem)')
        ->toContain('[data-density="compact"] :is(header, aside, [role="dialog"])');
});

it('leaves the top band alone, height and furniture both', function () {
    // The band is a fixed 4rem at either setting — the bar, and the sidebar's
    // own <header> beside it. Tightening what is inside it therefore buys no
    // rows anywhere and only shrinks the targets: the search trigger 34 → 30px,
    // the avatar 40 → 28px, the bar's inset 16 → 11px. So the rule restores the
    // scale as well as pinning the height, and it names an element rather than
    // a class, which is what keeps the logo row level with the bar.
    $html = densityHtml();

    expect($html)->toContain('[data-density="compact"] header {')
        ->and($html)->toContain('min-height: 4rem;');

    $rule = Str::between($html, '[data-density="compact"] header {', '}');

    expect($rule)->toContain('--spacing: 0.25rem;');
});

it('leaves type alone, which is the one thing compact must not touch', function () {
    // The whole block, not a fixed number of characters from the start of it: a
    // rule added at the top used to push the ones this is about out of the window,
    // and the test kept passing over text it was no longer reading.
    $html = densityHtml();
    $start = strpos($html, '<style data-wire-density');
    $style = substr($html, $start, strpos($html, '</style>', $start) - $start);

    expect($style)->not->toContain('font-size')
        ->and($style)->not->toContain('--text-');
});

// ─── Shape ──────────────────────────────────────────────────────

it('resolves the shipped corners when nothing is configured', function () {
    expect(Shape::configured())->toBe(Shape::Rounded);
});

it('resolves the shape the application asked for', function () {
    config()->set('wire-core.shape', 'sharp');

    expect(Shape::configured())->toBe(Shape::Sharp)
        ->and(Shape::resolve('square'))->toBe(Shape::Rounded)
        ->and(Shape::resolve(null))->toBe(Shape::Rounded);
});

it('puts the shape on the document and carries its rules', function () {
    config()->set('wire-core.shape', 'sharp');

    $html = densityHtml();

    expect($html)->toContain('data-shape="sharp"')
        ->and($html)->toContain('data-wire-shape')
        ->and($html)->toContain('--radius-lg: 0');
});

it('squares the pills a token cannot reach, and not the avatar', function () {
    // `rounded-full` compiles to calc(infinity * 1px) and reads no token, so the
    // badges are named. The avatar is deliberately not among them: a sharp theme
    // that squares the faces reads as broken rather than sharp.
    $style = substr(densityHtml(), strpos(densityHtml(), '<style data-wire-shape'), 1400);

    expect($style)->toContain('[data-wire="table-badge"]')
        ->and($style)->toContain('[data-wire="table-tag"]')
        ->and($style)->not->toContain('admin-avatar');
});
