<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use NyonCode\WireCore\WireCoreServiceProvider;
use NyonCode\WireForms\WireFormsServiceProvider;
use NyonCode\WireSortable\WireSortableServiceProvider;
use NyonCode\WireTable\WireTableServiceProvider;

/**
 * Static delivery, checked with all four providers booted — because the promise is
 * that the whole stack lands under `public/vendor` on its own, with nothing for the
 * application to run, configure or host-tune.
 */
it('mirrors every installed package on a page render, with no command run first', function () {
    // A fresh public/, exactly like a deploy that uploaded vendor/ and nothing else.
    $public = sys_get_temp_dir().'/wire-mirror-e2e-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($public);
    $this->app->usePublicPath($public);

    try {
        $html = Blade::render('@wireStackScripts');

        // Every bundle now comes off disk — the request a web server answers for
        // these URLs never has to reach PHP, which is the whole point on a host
        // whose nginx answers `.js` from `try_files $uri =404`.
        expect($html)
            ->toContain('/vendor/wire-core/wire-core-dropdown.js?id=')
            ->toContain('/vendor/wire-forms/wire-forms-image.js?id=')
            ->toContain('/vendor/wire-table/wire-table-records.js?id=')
            ->toContain('/vendor/wire-sortable/wire-sortable.js?id=')
            ->not->toContain('/wire-core/assets/')
            ->not->toContain('console.warn');

        foreach ([
            'vendor/wire-core/wire-core-dropdown.js',
            'vendor/wire-core/wire-core-chart.js',       // on-request, still mirrored
            'vendor/wire-forms/wire-forms-image.js',
            'vendor/wire-table/wire-table-records.js',
            'vendor/wire-sortable/wire-sortable.js',
        ] as $file) {
            expect($public.'/'.$file)->toBeFile();
        }
    } finally {
        File::deleteDirectory($public);
    }
});

it('mirrors the TipTap bundle with its shared chunk beside it', function () {
    // The editor's entry imports `./chunk-<hash>.js`, resolved by the browser against
    // the entry's own directory. The chunk is never asked for through PHP, so only a
    // verbatim directory mirror keeps the editor loading at all.
    $public = sys_get_temp_dir().'/wire-mirror-e2e-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($public);
    $this->app->usePublicPath($public);

    try {
        Blade::render('@wireStackScripts');

        $tiptap = $public.'/vendor/wire-forms/tiptap';

        expect($tiptap.'/tiptap-editor.js')->toBeFile()
            ->and(glob($tiptap.'/chunk-*.js'))->not->toBeEmpty();
    } finally {
        File::deleteDirectory($public);
    }
});

it('registers every package dist under Laravel\'s conventional assets tag', function () {
    // The toolkit adds `laravel-assets` to each package's own `{short}::assets` tag.
    // Publishing is no longer required, but it stays supported: it is the same copy
    // done ahead of time, which moves it off the first request after a deploy, and
    // `laravel-assets` is what the skeleton's composer `post-update-cmd` runs.
    // Compared by suffix, not against public_path(): the publish is registered at
    // boot, so it holds whatever the public path was then — which the suite moves
    // afterwards to keep the mirror out of the repo.
    $paths = ServiceProvider::pathsToPublish(null, 'laravel-assets');

    foreach ([
        WireCoreServiceProvider::ASSETS_PATH => 'vendor/wire-core',
        WireFormsServiceProvider::ASSETS_PATH => 'vendor/wire-forms',
        WireTableServiceProvider::ASSETS_PATH => 'vendor/wire-table',
        WireSortableServiceProvider::ASSETS_PATH => 'vendor/wire-sortable',
    ] as $dist => $target) {
        expect($paths[$dist] ?? null)->toEndWith($target);
    }
});

it('leaves the per-package assets tag publishing the same files', function () {
    // One `publishes()` call carrying both groups, so they cannot drift apart and an
    // app that already publishes `wire-core::assets` keeps working unchanged.
    foreach (['wire-core', 'wire-forms', 'wire-table', 'wire-sortable'] as $package) {
        $laravelTag = array_filter(
            ServiceProvider::pathsToPublish(null, 'laravel-assets'),
            static fn (string $target): bool => str_ends_with($target, 'vendor/'.$package),
        );

        expect(ServiceProvider::pathsToPublish(null, $package.'::assets'))->toBe($laravelTag);
    }
});

it('keeps assets out of the per-package install commands', function () {
    // Nothing to install: the mirror runs itself. An installer step would only be a
    // second way to do the same copy, and it covered one package of four.
    $public = sys_get_temp_dir().'/wire-mirror-e2e-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($public);
    $this->app->usePublicPath($public);

    try {
        foreach (['wire-core', 'wire-forms', 'wire-table', 'wire-sortable'] as $package) {
            $this->artisan($package.':install --no-interaction')->assertSuccessful();
        }

        expect(is_dir($public.'/vendor/wire-core'))->toBeFalse();
    } finally {
        File::deleteDirectory($public);
    }
});
