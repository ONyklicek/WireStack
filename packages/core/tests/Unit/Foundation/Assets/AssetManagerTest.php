<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NyonCode\WireCore\Exceptions\AssetRegistrationException;
use NyonCode\WireCore\Foundation\Assets\AssetManager;
use NyonCode\WireCore\Foundation\Assets\Contracts\Asset;
use NyonCode\WireCore\Foundation\Assets\Js;
use NyonCode\WireCore\WireCoreServiceProvider;

beforeEach(function () {
    // Only wire-core boots in this suite, but the manager is package-agnostic:
    // an asset's URL comes from the `{package}.asset` route of whoever registered
    // it. Stand those routes up so the convention itself is under test.
    Route::get('/wire-table/assets/{asset}.js', fn () => '')->name('wire-table.asset');
    Route::get('/wire-forms/assets/{asset}.js', fn () => '')->name('wire-forms.asset');
});

it('is bound as a singleton so the registry spans the whole request', function () {
    expect(app(AssetManager::class))->toBe(app(AssetManager::class));
});

it('binds every registered asset to the package that registered it', function () {
    $manager = new AssetManager;
    $manager->register([Js::make('dropdown', '/x.js')], 'wire-core');

    expect($manager->get('wire-core', 'dropdown')->getPackage())->toBe('wire-core');
});

it('emits every package bundle, in registration order', function () {
    $manager = new AssetManager;
    $manager->register([Js::make('dropdown', '/x.js')], 'wire-core');
    $manager->register([
        Js::make('records', '/x.js'),
        Js::make('selection', '/x.js'),
    ], 'wire-table');

    expect(array_map(
        static fn (Asset $asset): string => $asset->getId(),
        $manager->getScripts(),
    ))->toBe(['dropdown', 'records', 'selection']);
});

it('keeps ids of different packages apart', function () {
    // Two packages may both ship an 'image' bundle; neither may swallow the other.
    $manager = new AssetManager;
    $manager->register([Js::make('image', '/x.js')], 'wire-core');
    $manager->register([Js::make('image', '/x.js')], 'wire-forms');

    expect($manager->getScripts())->toHaveCount(2)
        ->and($manager->getScripts('wire-forms'))->toHaveCount(1);
});

it('narrows the set to one package on request', function () {
    $manager = new AssetManager;
    $manager->register([Js::make('dropdown', '/x.js')], 'wire-core');
    $manager->register([Js::make('records', '/x.js')], 'wire-table');

    expect($manager->getScripts('wire-table'))->toHaveCount(1)
        ->and($manager->getScripts('wire-table')[0]->getId())->toBe('records')
        ->and($manager->getScripts('wire-sortable'))->toBe([]);
});

it('leaves on-request assets out of the emitted set but keeps them addressable', function () {
    // §3.C: heavy, optional bodies (a rich-text editor) load on demand; the small
    // controller that loads them never does.
    $manager = new AssetManager;
    $manager->register([
        Js::make('image', '/x.js'),
        Js::make('tiptap', '/x.js')->loadedOnRequest(),
    ], 'wire-forms');

    expect($manager->getScripts())->toHaveCount(1)
        ->and($manager->getScripts()[0]->getId())->toBe('image')
        ->and($manager->get('wire-forms', 'tiptap')->getId())->toBe('tiptap');
});

it('replaces an asset re-registered under the same id', function () {
    $manager = new AssetManager;
    $manager->register([Js::make('dropdown', '/x.js')], 'wire-core');
    $manager->register([Js::make('dropdown', 'https://cdn.example.test/dropdown.js')], 'wire-core');

    expect($manager->getScripts())->toHaveCount(1)
        ->and($manager->url('wire-core', 'dropdown'))->toBe('https://cdn.example.test/dropdown.js');
});

it('renders one script tag per asset', function () {
    $manager = new AssetManager;
    $manager->register([
        Js::make('records', '/x.js')->navigateTrack(),
        Js::make('selection', '/x.js'),
    ], 'wire-table');

    $html = $manager->renderScripts()->toHtml();

    expect(substr_count($html, '<script'))->toBe(2)
        ->and($html)->toContain('/wire-table/assets/records.js')
        ->toContain('/wire-table/assets/selection.js')
        ->toContain('data-navigate-track');
});

it('memoises the rendered markup per package', function () {
    $manager = new AssetManager;
    $manager->register([Js::make('dropdown', '/x.js')], 'wire-core');

    expect($manager->renderScripts())->toBe($manager->renderScripts())
        ->and($manager->renderScripts('wire-core'))->not->toBe($manager->renderScripts());
});

it('drops the memo when a package registers late', function () {
    $manager = new AssetManager;
    $manager->register([Js::make('dropdown', '/x.js')], 'wire-core');
    $manager->renderScripts();

    $manager->register([Js::make('records', '/x.js')], 'wire-table');

    expect($manager->renderScripts()->toHtml())->toContain('/wire-table/assets/records.js');
});

it('resolves the cache-busted URL of one registered asset', function () {
    $manager = new AssetManager;
    $manager->register([
        Js::make('dropdown', WireCoreServiceProvider::ASSETS_PATH.'/wire-core-dropdown.js'),
    ], 'wire-core');

    expect($manager->url('wire-core', 'dropdown'))
        ->toContain('/wire-core/assets/dropdown.js?id=');
});

it('names the fix when an asset was never registered', function () {
    expect(fn () => (new AssetManager)->get('wire-table', 'records'))
        ->toThrow(AssetRegistrationException::class, 'No asset [records] is registered');
});
