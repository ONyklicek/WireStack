<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;
use NyonCode\WireCore\Foundation\Assets\AssetManager;
use NyonCode\WireCore\Foundation\Assets\Js;
use NyonCode\WireCore\WireCoreServiceProvider;

/**
 * wire-core's half of static delivery. The mirror itself — incremental copying,
 * atomic rename, walking files nobody resolved a URL for — belongs to
 * `nyoncode/laravel-package-toolkit` and is covered by its suite. What is ours is
 * what happens around it: the route fallback when `public/` cannot be written, and
 * the warning when a copy is left behind.
 */
beforeEach(function () {
    $root = sys_get_temp_dir().'/wire-published-'.bin2hex(random_bytes(6));

    $this->publicPath = $root.'/public';
    $this->dist = $root.'/package/dist';

    File::ensureDirectoryExists($this->publicPath);
    File::ensureDirectoryExists($this->dist);

    $this->app->usePublicPath($this->publicPath);

    $this->bundle = $this->dist.'/wire-fixture-bundle.js';
    File::put($this->bundle, '/* bundle */');

    $this->publishedPath = fn (string $relative): string => $this->publicPath.'/vendor/wire-fixture/'.$relative;

    // A public/ that no user can create, root included: its parent is a file.
    File::put($root.'/not-a-directory', '');
    $this->blockedPath = $root.'/not-a-directory/public';

    afterEach(fn () => File::deleteDirectory($root));
});

it('emits the mirrored file rather than the package route', function () {
    $url = Js::make('bundle', $this->bundle)->withPackage('wire-fixture')->getUrl();

    expect($url)->toBe(
        asset('vendor/wire-fixture/wire-fixture-bundle.js')
        .'?id='.filemtime(($this->publishedPath)('wire-fixture-bundle.js'))
    );
});

it('keeps the cache-buster, so data-navigate-track still reloads on an upgrade', function () {
    $html = Js::make('bundle', $this->bundle)->withPackage('wire-fixture')->navigateTrack()->toHtml();

    expect($html)
        ->toContain('data-navigate-track')
        ->toContain('vendor/wire-fixture/wire-fixture-bundle.js')
        ->toMatch('/\?id=\d+/');
});

it('falls back to the package route when public cannot be written', function () {
    // Read-only container, Vapor, a hardened deployment: the bundle is still served,
    // by the route each package registers, exactly as before any mirror existed.
    //
    // Simulated by pointing public/ *below a regular file*, so every mkdir under it
    // fails with ENOTDIR. A chmod would not do — the suite must fail the same way
    // when it runs as root in a container, and root walks straight through modes.
    $this->app->usePublicPath($this->blockedPath);

    $url = Js::make('dropdown', $this->bundle)->withPackage('wire-core')->getUrl();

    expect($url)->toContain('/wire-core/assets/dropdown.js')
        ->and($this->blockedPath)->not->toBeDirectory();
});

it('prefers a copy the mirror could not refresh over a route that may be unreachable', function () {
    // The deployment that ends up here is the one whose nginx answers `.js` from
    // `try_files $uri =404` — falling back would trade a release-old bundle for none.
    //
    // The state is forced through the once-per-request sync: resolve first, then age
    // the copy. In production it is reached when `public/` cannot be written back.
    $asset = Js::make('bundle', $this->bundle)->withPackage('wire-fixture');
    $asset->getUrl();

    touch(($this->publishedPath)('wire-fixture-bundle.js'), filemtime($this->bundle) - 10);

    expect(Js::make('bundle', $this->bundle)->withPackage('wire-fixture')->getUrl())
        ->toContain('vendor/wire-fixture/wire-fixture-bundle.js')
        ->and($asset->isStale())->toBeTrue();
});

it('names the stale bundles and the command that fixes them', function (bool $debug) {
    // Not gated on app.debug: reaching this state means the mirror could not write,
    // which happens in production, where a debug-only warning would never be seen.
    config()->set('app.debug', $debug);

    $manager = new AssetManager;
    $manager->register([Js::make('bundle', $this->bundle)], 'wire-fixture');

    // Mirror once, then age the copy behind the per-request memo.
    $manager->renderScripts();
    touch(($this->publishedPath)('wire-fixture-bundle.js'), filemtime($this->bundle) - 10);

    $fresh = new AssetManager;
    $fresh->register([Js::make('bundle', $this->bundle)], 'wire-fixture');

    expect($fresh->renderScripts()->toHtml())
        ->toContain('console.warn')
        ->toContain('wire-fixture/bundle')
        ->toContain('vendor:publish --tag=laravel-assets');
})->with([true, false]);

it('does not warn on the first render after an upgrade, which the mirror repairs', function () {
    // The tags are built before staleness is judged, because resolving a URL is what
    // runs the mirror. Judging first would find every copy out of date on the request
    // that is about to replace them, and warn about a state it repaired one line later.
    File::ensureDirectoryExists(dirname(($this->publishedPath)('wire-fixture-bundle.js')));
    File::put(($this->publishedPath)('wire-fixture-bundle.js'), '/* last release */');
    touch(($this->publishedPath)('wire-fixture-bundle.js'), filemtime($this->bundle) - 10);

    $manager = new AssetManager;
    $manager->register([Js::make('bundle', $this->bundle)], 'wire-fixture');

    expect($manager->renderScripts()->toHtml())->not->toContain('console.warn')
        ->and(file_get_contents(($this->publishedPath)('wire-fixture-bundle.js')))->toBe('/* bundle */');
});

it('says nothing when the mirror is current', function () {
    $manager = new AssetManager;
    $manager->register([Js::make('bundle', $this->bundle)], 'wire-fixture');

    expect($manager->renderScripts()->toHtml())->not->toContain('console.warn');
});

it('never calls a remote or unregistered bundle stale', function () {
    // Neither has a shipped file to compare a published copy against.
    expect(Js::make('cdn', 'https://cdn.example.test/x.js')->withPackage('wire-core')->isStale())->toBeFalse()
        ->and(Js::make('bundle', $this->bundle)->isStale())->toBeFalse();
});

it('forgets resolved URLs and rendered tags, so a long-lived worker can re-resolve', function () {
    // Octane: the manager is a singleton, so its memos would otherwise outlive the
    // deploy that changed the files and the worker would keep emitting last release's
    // `?id=` — the one thing data-navigate-track exists to notice. The
    // RequestTerminated hook calls flushUrls(); this proves it empties both memos.
    //
    // End to end the flush also needs `PublishedAssets::flush()`, one layer down —
    // without it the re-resolve asks the toolkit and gets its memo back. That lands
    // in laravel-package-toolkit 2.3.1; the hook already calls it when present.
    $manager = new AssetManager;
    $manager->register([Js::make('bundle', $this->bundle)], 'wire-fixture');

    $rendered = $manager->renderScripts();
    $asset = $manager->get('wire-fixture', 'bundle');

    expect($manager->renderScripts())->toBe($rendered);

    $manager->flushUrls();

    expect($manager->renderScripts())->not->toBe($rendered)
        ->and($manager->get('wire-fixture', 'bundle'))->not->toBe($asset);
});

it('keeps the registry itself across a flush', function () {
    $manager = new AssetManager;
    $manager->register([Js::make('bundle', $this->bundle)], 'wire-fixture');

    $manager->flushUrls();

    expect($manager->getScripts())->toHaveCount(1)
        ->and($manager->get('wire-fixture', 'bundle')->getId())->toBe('bundle')
        ->and($manager->get('wire-fixture', 'bundle')->getPackage())->toBe('wire-fixture');
});

it('resolves through the shared toolkit registry, not a wire-core one', function () {
    // The resolver is the toolkit's singleton, declared by every package's provider
    // from `hasAssets()`. wire-core owning a second copy is what this replaced.
    expect(app(PublishedAssets::class))->toBe(app(PublishedAssets::class))
        ->and(app(PublishedAssets::class)->url('wire-core', WireCoreServiceProvider::ASSETS_PATH.'/wire-core-dropdown.js'))
        ->toContain('vendor/wire-core/wire-core-dropdown.js');
});
