<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use NyonCode\WireCore\Foundation\Assets\AssetManager;
use NyonCode\WireCore\Foundation\Assets\Js;

/**
 * `@wireStackScripts` is the one tag an app adds to its layout <head>. Everything
 * else in this file exists because that tag has to be enough on its own.
 */
it('emits the core interaction bundle', function () {
    $html = Blade::render('@wireStackScripts');

    expect($html)
        ->toContain('<script src="')
        ->toContain('/wire-core/assets/dropdown.js?id=');
});

it('marks the bundle so a deploy is picked up across wire:navigate', function () {
    // Livewire full-page-reloads a navigate visit when a tracked asset's query
    // string changed — our `?id=<mtime>` is that query string.
    expect(Blade::render('@wireStackScripts'))
        ->toContain('data-navigate-track')
        ->toContain('data-navigate-once');
});

it('forwards its expression to the canonical owner', function () {
    // A thin passthrough: the whole expression goes to renderScripts(), so an app
    // may narrow the tag to one package without the compiler knowing anything.
    app(AssetManager::class)->register([Js::make('extra', 'https://cdn.example.test/extra.js')], 'wire-core');

    expect(Blade::render("@wireStackScripts('wire-core')"))
        ->toContain('/wire-core/assets/dropdown.js')
        ->toContain('https://cdn.example.test/extra.js');
});

it('puts the controllers in a rendered page that has no wireStack component on it', function () {
    // The acceptance criterion. The page a user navigates *from* is usually a page
    // with no table on it; if the bundle only ships with the table, the visit to
    // the table can lose the race against Alpine on the cached back/forward path.
    Route::get('/no-component', fn (): string => Blade::render(
        '<!DOCTYPE html><html><head>@wireStackScripts</head><body>Nothing here.</body></html>'
    ));

    $this->get('/no-component')
        ->assertOk()
        ->assertSee('Nothing here.')
        ->assertSee('/wire-core/assets/dropdown.js', false);
});
