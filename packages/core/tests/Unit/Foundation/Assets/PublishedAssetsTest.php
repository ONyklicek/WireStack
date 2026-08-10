<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Support\PackageAssets;
use NyonCode\WireCore\Foundation\Assets\Bundle;

/**
 * wire-core's half of static delivery. The mirror itself — incremental copying,
 * atomic rename, walking files nobody resolved a URL for — belongs to
 * `nyoncode/laravel-package-toolkit` and is covered by its suite, as does the tag.
 * What is ours is the declaration: that the bundles are `classic()` with no `defer`,
 * and that a package whose `public/` cannot be written still serves them from the
 * route ADR 0024 put behind the static files.
 */
beforeEach(function () {
    $root = sys_get_temp_dir().'/wire-published-'.bin2hex(random_bytes(6));

    $this->publicPath = $root.'/public';
    $this->dist = $root.'/package/dist';

    File::ensureDirectoryExists($this->publicPath);
    File::ensureDirectoryExists($this->dist);

    $this->app->usePublicPath($this->publicPath);

    File::put($this->dist.'/wire-fixture-bundle.js', '/* bundle */');

    $this->publishedPath = fn (string $relative): string => $this->publicPath.'/vendor/wire-fixture/'.$relative;

    // Declare the fixture the way a provider would, straight onto the renderer:
    // what is under test is the declaration's consequences, not provider boot.
    $this->declare = function (string $package = 'wire-fixture'): void {
        app(PackageAssets::class)->declare(
            package: $package,
            directory: $this->dist,
            entries: [Bundle::make('wire-fixture-bundle.js')],
            base: null,
            mirrored: true,
            fallback: Bundle::servedByRoute($package),
        );
    };

    // A public/ that no user can create, root included: its parent is a file.
    File::put($root.'/not-a-directory', '');
    $this->blockedPath = $root.'/not-a-directory/public';

    afterEach(fn () => File::deleteDirectory($root));
});

it('emits the mirrored file rather than the package route', function () {
    ($this->declare)();

    expect(app(PackageAssets::class)->url('wire-fixture', 'wire-fixture-bundle.js'))->toBe(
        asset('vendor/wire-fixture/wire-fixture-bundle.js')
        .'?id='.filemtime(($this->publishedPath)('wire-fixture-bundle.js'))
    );
});

it('keeps the cache-buster, so data-navigate-track still reloads on an upgrade', function () {
    ($this->declare)();

    expect(app(PackageAssets::class)->scripts('wire-fixture')->toHtml())
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

    // wire-core, because the fallback resolves that package's real asset route.
    ($this->declare)('wire-core');

    expect(app(PackageAssets::class)->url('wire-core', 'wire-fixture-bundle.js'))
        ->toContain('/wire-core/assets/fixture-bundle.js');
});

it('keeps the tag rather than dropping it when nothing is published', function () {
    // The failure this exists to prevent is silent: without a fallback the renderer
    // emits no tag at all, and the page loses its behaviour with nothing to see.
    $this->app->usePublicPath($this->blockedPath);

    ($this->declare)('wire-core');

    // The tag, not `resolution()`: the report asks whether the mirror *could* write
    // by walking up to the nearest existing ancestor, and this fixture blocks the
    // path with a regular file rather than with permissions — so the walk steps over
    // the blockage into a writable ancestor and reports `shipped`. Harmless here
    // (it only drives diagnostics, and the tag below is what the page gets) but it
    // is why the assertion is on delivery.
    expect(app(PackageAssets::class)->scripts('wire-core')->toHtml())
        ->toContain('<script src=')
        ->toContain('/wire-core/assets/fixture-bundle.js');
});

it('declares every bundle classic, and without the defer classic() would add', function () {
    // Both halves matter and neither is cosmetic. A module never reaches `window`,
    // so the registration idiom silently registers nothing; `defer` is a timing
    // change no browser check has covered, so the migration does not take it.
    ($this->declare)();

    expect(app(PackageAssets::class)->scripts('wire-fixture')->toHtml())
        ->not->toContain('type="module"')
        ->not->toContain(' defer')
        ->toContain('data-navigate-once');
});
