<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use NyonCode\WireCore\Exceptions\AssetRegistrationException;
use NyonCode\WireCore\Foundation\Assets\Js;
use NyonCode\WireCore\WireCoreServiceProvider;

it('serves a package bundle from its named route, cache-busted by the file mtime', function () {
    $path = WireCoreServiceProvider::ASSETS_PATH.'/wire-core-dropdown.js';

    $url = Js::make('dropdown', $path)->withPackage('wire-core')->getUrl();

    expect($url)
        ->toContain('/wire-core/assets/dropdown.js')
        ->toContain('?id='.filemtime($path));
});

it('omits the cache-buster when the bundle is not on disk yet', function () {
    $url = Js::make('missing', '/does/not/exist.js')->withPackage('wire-core')->getUrl();

    expect($url)->toEndWith('/wire-core/assets/missing.js');
});

it('memoises the URL so the route and the mtime are read once', function () {
    $asset = Js::make('dropdown', WireCoreServiceProvider::ASSETS_PATH.'/wire-core-dropdown.js')
        ->withPackage('wire-core');

    expect($asset->getUrl())->toBe($asset->getUrl());
});

it('uses a path that is already a URL verbatim', function (string $path) {
    // Remoteness is detected from the path, exactly like Filament — there is no
    // `remote()` builder to get wrong, and no route or mtime to resolve.
    expect(Js::make('cdn', $path)->getUrl())->toBe($path);
})->with([
    'https://cdn.example.test/sortable.js',
    'http://cdn.example.test/sortable.js',
    '//cdn.example.test/sortable.js',
]);

it('refuses to resolve a URL before it is registered with a package', function () {
    // The package names the route that serves the file, so an unregistered asset
    // has nowhere to point. Failing loudly beats emitting a broken <script src>.
    expect(fn () => Js::make('dropdown', '/tmp/x.js')->getUrl())
        ->toThrow(AssetRegistrationException::class, 'has no owning package');
});

it('renders a plain script tag by default', function () {
    $html = Js::make('dropdown', '/does/not/exist.js')->withPackage('wire-core')->toHtml();

    expect($html)
        ->toStartWith('<script src="')
        ->toEndWith('"></script>')
        ->not->toContain('type="module"')
        ->not->toContain('defer')
        ->not->toContain('data-navigate');
});

it('carries the module, defer and navigation attributes it opts into', function () {
    $html = Js::make('dropdown', '/does/not/exist.js')
        ->module()
        ->defer()
        ->navigateTrack()
        ->navigateOnce()
        ->withPackage('wire-core')
        ->toHtml();

    // data-navigate-track makes Livewire reload the page when the tracked asset's
    // query string changes — which our `?id=<mtime>` supplies on every deploy.
    expect($html)
        ->toContain('type="module"')
        ->toContain(' defer')
        ->toContain('data-navigate-track')
        ->toContain('data-navigate-once');
});

it('memoises its markup', function () {
    $asset = Js::make('dropdown', '/does/not/exist.js')->withPackage('wire-core');

    expect($asset->toHtml())->toBe($asset->toHtml());
});

it('is Htmlable, so a view renders it by echoing it', function () {
    $asset = Js::make('dropdown', '/does/not/exist.js')->withPackage('wire-core');

    expect(Blade::render('{{ $asset }}', ['asset' => $asset]))->toBe($asset->toHtml());
});

it('keeps the declared asset unregistered when bound to a package', function () {
    $declared = Js::make('dropdown', '/does/not/exist.js');
    $registered = $declared->withPackage('wire-core');

    expect($declared->getPackage())->toBeNull()
        ->and($registered->getPackage())->toBe('wire-core')
        ->and($registered)->not->toBe($declared)
        ->and($registered->getId())->toBe('dropdown');
});

it('rebinding to another package rebuilds the URL', function () {
    // The registering package names the route that serves the file, so the URL is
    // not something a rebound copy may inherit.
    Route::get('/wire-table/assets/{asset}.js', fn () => '')->name('wire-table.asset');

    $asset = Js::make('records', '/does/not/exist.js')->withPackage('wire-core');
    $asset->toHtml();

    expect($asset->withPackage('wire-table')->getUrl())->toContain('/wire-table/assets/records.js');
});

it('is part of the always-emitted set unless it opts out', function () {
    expect(Js::make('image', '/x.js')->isLoadedOnRequest())->toBeFalse()
        ->and(Js::make('tiptap', '/x.js')->loadedOnRequest()->isLoadedOnRequest())->toBeTrue();
});
