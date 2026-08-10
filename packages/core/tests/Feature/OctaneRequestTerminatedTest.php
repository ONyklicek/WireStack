<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageToolkit\Support\PackageAssets;
use NyonCode\WireCore\Foundation\Assets\Bundle;

/**
 * The Octane hook. Two memos that are per-request everywhere else — the manager's
 * resolved URLs and the toolkit's mirror marks — live as long as the worker here, so
 * a worker that survives a deploy would keep emitting the previous release's
 * `?id=<mtime>` and `data-navigate-track` would never fire for the visitor it exists
 * for. Registered only when Octane's event class is present, hence the stand-in,
 * which has to be declared before the application boots.
 */
require_once __DIR__.'/../Fixtures/OctaneEvents.php';

beforeEach(function () {
    $root = sys_get_temp_dir().'/wire-octane-'.bin2hex(random_bytes(6));

    $this->publicPath = $root.'/public';
    $this->dist = $root.'/package/dist';

    File::ensureDirectoryExists($this->publicPath);
    File::ensureDirectoryExists($this->dist);

    $this->app->usePublicPath($this->publicPath);

    $this->bundle = $this->dist.'/wire-fixture-bundle.js';
    File::put($this->bundle, '/* bundle */');

    $this->published = $this->publicPath.'/vendor/wire-fixture/wire-fixture-bundle.js';

    afterEach(fn () => File::deleteDirectory($root));
});

it('re-mirrors and re-stamps a bundle the deploy replaced under a live worker', function () {
    // The state a worker boots into: a published copy already current, so the URL it
    // memoises carries that copy's mtime. Both files are backdated because the mirror
    // stamps a copy with the moment it made it — two copies inside one second are
    // indistinguishable, and the assertion below is about which release the URL names.
    File::ensureDirectoryExists(dirname($this->published));
    File::put($this->published, '/* bundle */');
    touch($this->bundle, time() - 7200);
    touch($this->published, time() - 3600);

    $manager = app(PackageAssets::class);
    $manager->declare(
        package: 'wire-fixture',
        directory: dirname($this->bundle),
        entries: [Bundle::make(basename($this->bundle))],
        base: null,
        mirrored: true,
    );

    $booted = $manager->scripts('wire-fixture')->toHtml();

    expect($booted)->toContain('?id='.filemtime($this->published));

    // The deploy: the shipped file is replaced under the running worker.
    File::put($this->bundle, '/* next release */');

    // Nothing has ended a request yet, so the URL memo still names the old release and
    // the sync mark still says this package was mirrored — the failure the hook exists
    // to prevent, not an artefact of the test.
    expect($manager->scripts('wire-fixture')->toHtml())->toBe($booted)
        ->and(file_get_contents($this->published))->toBe('/* bundle */');

    Event::dispatch('Laravel\\Octane\\Events\\RequestTerminated');

    expect($manager->scripts('wire-fixture')->toHtml())->not->toBe($booted)
        ->and(file_get_contents($this->published))->toBe('/* next release */');
});
