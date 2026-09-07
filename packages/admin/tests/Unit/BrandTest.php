<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/*
 * The logo.
 *
 * The gap this closed: a shell whose only answer to "where do I put our logo?"
 * was "write your own layout" asks an application to re-implement the sidebar
 * header to change one image. So the image is configuration and the markup
 * around it is not — and the fallback is a brand rather than an empty corner.
 */

/**
 * The rendered brand with its whitespace squeezed out.
 *
 * Blade keeps the indentation of the view, so a `>A<` assertion against the raw
 * output tests the template's formatting rather than what it drew.
 */
function btRender(): string
{
    return (string) preg_replace('/\s+/', ' ', Blade::render('<x-wire-admin::brand />'));
}

/**
 * Only what the collapsed rail draws: everything before the wide form begins.
 *
 * Split on `data-rail-hide`, which is the attribute that marks the wide form —
 * the two used to be picked between by `x-show`, and picking after the first
 * paint is what made the corner draw the wordmark and swap it for the square one
 * frame later.
 */
function btRail(): string
{
    return explode('data-rail-hide', btRender())[0];
}

it('falls back to the application initial when nothing is configured', function () {
    config()->set('wire-admin.brand', []);
    config()->set('app.name', 'Acme Admin');

    $html = btRender();

    expect($html)->toContain('Acme Admin')
        // The square, not an empty corner: a header with nothing in it reads as
        // a half-built page rather than as a deliberate absence.
        ->and($html)->toContain('> A <')
        ->and($html)->not->toContain('<img');
});

it('takes the first letter as a letter, not as a byte', function () {
    // "Účetnictví"[0] is half of a two-byte character, and the one place in the
    // page that cannot afford a replacement glyph is the brand.
    config()->set('wire-admin.brand', []);
    config()->set('app.name', 'Účetnictví');

    expect(btRender())->toContain('> Ú <');
});

it('draws the configured logo, and a second one for the dark ground', function () {
    config()->set('wire-admin.brand', [
        'logo' => 'images/logo.svg',
        'logo_dark' => 'images/logo-dark.svg',
        'height' => 36,
    ]);

    $html = btRender();

    expect($html)->toContain('images/logo.svg')
        ->and($html)->toContain('images/logo-dark.svg')
        // Height in pixels rather than as a class, because a class named in a
        // config file is a class Tailwind never scans and never compiles.
        ->and($html)->toContain('height: 36px')
        ->and($html)->toContain('dark:hidden')
        ->and($html)->toContain('hidden dark:block');
});

it('leaves an address that is already complete alone', function () {
    config()->set('wire-admin.brand', [
        'logo' => 'https://cdn.example.com/logo.svg',
        'mark' => '/img/mark.svg',
        'logo_dark' => 'data:image/svg+xml;utf8,<svg/>',
    ]);

    $html = btRender();

    // Running asset() over any of these would prefix a host onto a URL that
    // already had one — or had deliberately been given none.
    expect($html)->toContain('src="https://cdn.example.com/logo.svg"')
        ->and($html)->toContain('src="/img/mark.svg"')
        ->and($html)->toContain('data:image/svg+xml');
});

it('prefixes a relative path with the application asset URL', function () {
    config()->set('wire-admin.brand', ['logo' => 'images/logo.svg']);

    expect(btRender())->toContain(asset('images/logo.svg'));
});

it('gives the rail its own square, and the initial when there is no mark', function () {
    config()->set('wire-admin.brand', ['logo' => 'images/logo.svg', 'name' => 'Wire']);

    // A wordmark scaled into 64 pixels is unreadable rather than small, so the
    // rail asks for `mark` — and falls back to the initial, which is the common
    // case rather than an error.
    expect(btRail())->toContain('> W <')
        ->and(btRail())->not->toContain('images/logo.svg');

    config()->set('wire-admin.brand.mark', 'images/mark.svg');

    expect(btRail())->toContain('images/mark.svg');
});

it('decides which form to draw before the page paints, not from a store', function () {
    // Measured, while this was `x-show`: with the rail collapsed the first frame
    // after DOMContentLoaded drew the *wordmark*, clipped to 31 pixels by the
    // header's own overflow, and swapped it for the square one frame later — on
    // every load and every wire:navigate, in the one corner of the page the
    // view's own comment calls unmissable.
    config()->set('wire-admin.brand', ['name' => 'Wire']);

    $html = btRender();

    expect($html)->toContain('data-rail-only')
        ->and($html)->toContain('data-rail-hide')
        // The state comes from <html data-rail>, stamped before the body is
        // parsed. This file names no store at all any more.
        ->and($html)->not->toContain('wireAdmin');
});

it('centres the mark on the same axis the menu icons sit on', function () {
    // `justify-center` on a 32-pixel anchor centres it inside itself and leaves
    // it wherever the header put it — hard against the left edge, 16 pixels off
    // the column every icon below it sits on. `flex-1` is what makes the
    // centring mean anything, and `data-rail-row` is the same attribute the menu
    // rows use for exactly this.
    expect(btRender())->toContain('data-rail-row')
        ->and(btRender())->toContain('flex-1');
});

it('links home unless the application says otherwise', function () {
    config()->set('wire-admin.brand', []);
    expect(btRender())->toContain('href="/"');

    config()->set('wire-admin.brand.url', '/admin');
    expect(btRender())->toContain('href="/admin"');
});

it('refuses a height that is not a number', function () {
    config()->set('wire-admin.brand', ['logo' => 'l.svg', 'height' => 'tall']);

    expect(btRender())->toContain('height: 28px');
});
