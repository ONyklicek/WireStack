<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use NyonCode\LaravelPackageToolkit\Support\PackageAssets;

/*
 * The drag controller has to reach a page the app navigated *to*. Reordering is
 * declared per table, so before this the bundle only ever shipped with a table's
 * own markup — and a bundle that first arrives with a `wire:navigate` response
 * can lose the race on the cached Back/Forward path, where Livewire does not wait
 * for newly injected head scripts before initialising Alpine.
 */

it('ships the drag controller on a page with no sortable table on it', function () {
    Route::get('/no-sortable', fn (): string => Blade::render(
        '<!DOCTYPE html><html><head>@wireStackScripts</head><body>Nothing to drag.</body></html>'
    ));

    $this->get('/no-sortable')
        ->assertOk()
        ->assertSee('Nothing to drag.')
        ->assertSee('/vendor/wire-sortable/wire-sortable.js', false)
        ->assertDontSee('<table', false);
});

it('registers the bundle under its own package, not core', function () {
    // wire-core owns the registry but must never learn that wire-sortable exists;
    // the provider pushes its own declaration. Narrowing to the package proves the
    // registration is filed under `wire-sortable` rather than leaking into core's.
    $html = app(PackageAssets::class)->scripts('wire-sortable')->toHtml();

    expect($html)
        ->toContain('/vendor/wire-sortable/wire-sortable.js')
        ->not->toContain('/wire-core/assets/');
});

it('cache-busts the bundle by the mirrored copy\'s mtime', function () {
    // The buster is the mtime of the copy under public/, stamped when the mirror
    // wrote it — not the shipped file's. That is what moves the query string on an
    // upgrade and makes data-navigate-track reload the app.
    expect(app(PackageAssets::class)->url('wire-sortable', 'wire-sortable.js'))
        ->toBe(asset('vendor/wire-sortable/wire-sortable.js')
            .'?id='.filemtime(public_path('vendor/wire-sortable/wire-sortable.js')));
});

it('falls back to a URL its own route actually serves', function () {
    // The route maps `{asset}` to `wire-{asset}.js`, unlike core's `wire-core-{asset}.js`,
    // so the registered id and the route's file map have to agree. That only matters on
    // the fallback path now, where public/ cannot be written — and fetching it proves
    // they agree, since a mismatched id 404s rather than failing a string comparison.
    //
    // public/ is pointed *below a regular file*, so every mkdir under it fails with
    // ENOTDIR — a chmod would not hold when the suite runs as root in a container.
    $blocker = sys_get_temp_dir().'/wire-sortable-blocked-'.bin2hex(random_bytes(6));
    File::put($blocker, '');
    $this->app->usePublicPath($blocker.'/public');

    try {
        $url = app(PackageAssets::class)->url('wire-sortable', 'wire-sortable.js');

        expect($url)->toContain('/wire-sortable/assets/sortable.js');

        $this->get($url)
            ->assertOk()
            ->assertHeader('content-type', 'application/javascript; charset=utf-8');
    } finally {
        File::delete($blocker);
    }
});

it('marks the bundle so a rebuild busts the SPA cache', function () {
    $html = app(PackageAssets::class)->scripts('wire-sortable')->toHtml();

    // data-navigate-track reloads the page when the query string changes (the mtime
    // buster supplies it); data-navigate-once stops Livewire re-running the tag.
    expect($html)
        ->toContain('data-navigate-track')
        ->toContain('data-navigate-once');
});
