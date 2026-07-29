<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use NyonCode\WireCore\Foundation\Assets\AssetManager;
use NyonCode\WireSortable\WireSortableServiceProvider;

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
        ->assertSee('/wire-sortable/assets/sortable.js', false)
        ->assertDontSee('<table', false);
});

it('registers the bundle under its own package, not core', function () {
    // wire-core owns the registry but must never learn that wire-sortable exists;
    // the provider pushes its own declaration. Narrowing to the package proves the
    // registration is filed under `wire-sortable` rather than leaking into core's.
    $html = app(AssetManager::class)->renderScripts('wire-sortable')->toHtml();

    expect($html)
        ->toContain('/wire-sortable/assets/sortable.js')
        ->not->toContain('/wire-core/assets/');
});

it('cache-busts the bundle by its mtime', function () {
    expect(app(AssetManager::class)->url('wire-sortable', 'sortable'))
        ->toBe(route('wire-sortable.asset', ['asset' => 'sortable'])
            .'?id='.filemtime(WireSortableServiceProvider::ASSETS_PATH.'/wire-sortable.js'));
});

it('resolves to the URL its own route actually serves', function () {
    // The route maps `{asset}` to `wire-{asset}.js`, unlike core's `wire-core-{asset}.js`,
    // so the registered id and the route's file map have to agree. Fetching it proves
    // they do — a mismatched id would 404 rather than fail a string comparison.
    $this->get(app(AssetManager::class)->url('wire-sortable', 'sortable'))
        ->assertOk()
        ->assertHeader('content-type', 'application/javascript; charset=utf-8');
});

it('marks the bundle so a rebuild busts the SPA cache', function () {
    $html = app(AssetManager::class)->renderScripts('wire-sortable')->toHtml();

    // data-navigate-track reloads the page when the query string changes (the mtime
    // buster supplies it); data-navigate-once stops Livewire re-running the tag.
    expect($html)
        ->toContain('data-navigate-track')
        ->toContain('data-navigate-once');
});
