<?php

declare(strict_types=1);

use NyonCode\WireAdmin\Exceptions\AdminInstallException;
use NyonCode\WireAdmin\Install\InstallOutcome;
use NyonCode\WireAdmin\Install\InstallScaffold;

/*
 * The three steps `composer require` cannot take for an application: writing the
 * layout view it names, putting the provider that names it in the list Laravel
 * reads, and pointing Tailwind at views that live under a gitignored `vendor/`.
 *
 * Both are idempotent and neither overwrites — an installer that eats the edits
 * someone made after the first run is worse than one that does nothing.
 */

function isBase(): string
{
    $base = sys_get_temp_dir().'/wire-admin-install-'.bin2hex(random_bytes(4));
    mkdir($base, 0755, true);

    return $base;
}

function isRemove(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($entries as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }

    rmdir($path);
}

it('writes the layout view the application will name', function () {
    $base = isBase();

    try {
        $outcome = (new InstallScaffold($base))->layout();
        $written = (string) file_get_contents($base.'/resources/views/components/layouts/admin.blade.php');

        expect($outcome)->toBe(InstallOutcome::Created)
            // It is the application's view, so it names the package's component
            // rather than being one.
            ->and($written)->toContain('<x-wire-admin::layout')
            ->and($written)->toContain('<x-slot:brand>');
    } finally {
        isRemove($base);
    }
});

it('leaves a layout that already exists exactly as it is', function () {
    $base = isBase();

    try {
        mkdir($base.'/resources/views/components/layouts', 0755, true);
        file_put_contents($base.'/resources/views/components/layouts/admin.blade.php', 'mine');

        expect((new InstallScaffold($base))->layout())->toBe(InstallOutcome::AlreadyPresent)
            ->and(file_get_contents($base.'/resources/views/components/layouts/admin.blade.php'))->toBe('mine');
    } finally {
        isRemove($base);
    }
});

it('registers the provider in bootstrap/providers.php', function () {
    $base = isBase();

    try {
        mkdir($base.'/bootstrap', 0755, true);
        file_put_contents($base.'/bootstrap/providers.php', <<<'PHP'
        <?php

        return [
            App\Providers\AppServiceProvider::class,
        ];

        PHP);

        $outcome = (new InstallScaffold($base))->registerProvider();
        $written = (string) file_get_contents($base.'/bootstrap/providers.php');

        expect($outcome)->toBe(InstallOutcome::Created)
            ->and($written)->toContain('App\Providers\AppServiceProvider::class,')
            ->and($written)->toContain('App\Providers\WireAdminServiceProvider::class,')
            // Still a valid list, in the same shape it arrived in.
            ->and(array_map(
                static fn (string $line): string => trim($line),
                array_slice(explode("\n", trim($written)), -2),
            ))->toBe(['App\Providers\WireAdminServiceProvider::class,', '];']);
    } finally {
        isRemove($base);
    }
});

it('does not register the same provider twice', function () {
    $base = isBase();

    try {
        mkdir($base.'/bootstrap', 0755, true);
        file_put_contents($base.'/bootstrap/providers.php', "<?php\n\nreturn [\n    App\\Providers\\WireAdminServiceProvider::class,\n];\n");

        expect((new InstallScaffold($base))->registerProvider())->toBe(InstallOutcome::AlreadyPresent);
    } finally {
        isRemove($base);
    }
});

it('refuses rather than guessing when there is no provider list to edit', function () {
    // A Laravel 10 application, or one that keeps its providers elsewhere. The
    // message carries the line to add, because that is the whole remedy — and it
    // is an exception rather than a status so no caller can print a tick beside
    // something that did not happen.
    $base = isBase();

    try {
        expect(fn () => (new InstallScaffold($base))->registerProvider())
            ->toThrow(AdminInstallException::class, 'by hand');
    } finally {
        isRemove($base);
    }
});

it('refuses a provider list that is not the file Laravel generates', function () {
    // Rewriting something this does not understand is worse than not writing.
    $base = isBase();

    try {
        mkdir($base.'/bootstrap', 0755, true);
        file_put_contents($base.'/bootstrap/providers.php', "<?php return require __DIR__.'/providers-generated.php';");

        expect(fn () => (new InstallScaffold($base))->registerProvider())
            ->toThrow(AdminInstallException::class, 'not the list Laravel generates')
            // Untouched: the file it could not read is the file it leaves alone.
            ->and(file_get_contents($base.'/bootstrap/providers.php'))
            ->toBe("<?php return require __DIR__.'/providers-generated.php';");
    } finally {
        isRemove($base);
    }
});

it('refuses when the layout directory cannot be made', function () {
    // `resources/` present as a *file* is the shape that makes mkdir fail. The
    // layout is the file the whole feature hangs on, so this one aborts the run
    // rather than warning past it.
    $base = isBase();

    try {
        file_put_contents($base.'/resources', 'not a directory');

        expect(fn () => (new InstallScaffold($base))->layout())
            ->toThrow(AdminInstallException::class, 'cannot be created');
    } finally {
        isRemove($base);
    }
});

it('points Tailwind at the packages, below the import and above the theme', function () {
    // The failure this prevents is silent: Tailwind skips `vendor/` because
    // .gitignore does, so without this line the shell's classes are never
    // compiled and the sidebar renders with no width and no colour, with no
    // error anywhere. Found by screenshotting the shell, not by a test.
    $base = isBase();

    try {
        mkdir($base.'/resources/css', 0755, true);
        file_put_contents($base.'/resources/css/app.css', <<<'CSS'
        @import "tailwindcss";
        @plugin "@tailwindcss/forms";

        @theme {
            --color-primary-500: var(--color-blue-500);
        }

        CSS);

        $outcome = (new InstallScaffold($base))->stylesheetSources();
        $lines = explode("\n", (string) file_get_contents($base.'/resources/css/app.css'));

        expect($outcome)->toBe(InstallOutcome::Created)
            // After the last at-rule of the opening block, so it is below the
            // Tailwind import that has to come first and above a theme the
            // application wrote.
            ->and($lines[2])->toBe(InstallScaffold::SOURCE_LINE)
            ->and($lines[4])->toBe('@theme {');
    } finally {
        isRemove($base);
    }
});

it('does not add a second source line for the same vendor directory', function () {
    $base = isBase();

    try {
        mkdir($base.'/resources/css', 0755, true);
        // Narrower than the line the installer writes, and deliberately so: an
        // application that already answered this question keeps its answer.
        file_put_contents($base.'/resources/css/app.css', "@import \"tailwindcss\";\n@source \"../../vendor/nyoncode/wire-admin\";\n");

        expect((new InstallScaffold($base))->stylesheetSources())->toBe(InstallOutcome::AlreadyPresent);
    } finally {
        isRemove($base);
    }
});

it('refuses rather than guessing when there is no stylesheet to edit', function () {
    $base = isBase();

    try {
        expect(fn () => (new InstallScaffold($base))->stylesheetSources())
            ->toThrow(AdminInstallException::class, 'renders unstyled');
    } finally {
        isRemove($base);
    }
});

it('refuses a stylesheet that does not import Tailwind', function () {
    // Writing an `@source` into a file Tailwind never reads is worse than not
    // writing: the install prints a tick and the shell still renders unstyled.
    $base = isBase();

    try {
        mkdir($base.'/resources/css', 0755, true);
        file_put_contents($base.'/resources/css/app.css', "body { margin: 0 }\n");

        expect(fn () => (new InstallScaffold($base))->stylesheetSources())
            ->toThrow(AdminInstallException::class, 'does not import Tailwind')
            ->and(file_get_contents($base.'/resources/css/app.css'))->toBe("body { margin: 0 }\n");
    } finally {
        isRemove($base);
    }
});

/*
 * ─── The accent ─────────────────────────────────────────────────
 *
 * `primary` is not a colour this framework ships — it is a name its components
 * use, and until something defines it `bg-primary-600` is not a class at all:
 * the primary button is transparent with white text on it. Invisible, on a fresh
 * install, with no error anywhere. The documentation said "you must define it",
 * which is a fine sentence and a bad default.
 */

it('gives a fresh stylesheet a primary palette', function () {
    $base = isBase();

    try {
        mkdir($base.'/resources/css', 0755, true);
        file_put_contents($base.'/resources/css/app.css', "@import \"tailwindcss\";\n");

        expect((new InstallScaffold($base))->stylesheetPrimary())->toBe(InstallOutcome::Created);

        $css = (string) file_get_contents($base.'/resources/css/app.css');

        expect($css)->toContain('--color-primary-600: var(--color-blue-600);')
            // Every step, because a component reaching for 50 or 950 must not
            // fall through to nothing on the one shade nobody wrote.
            ->and($css)->toContain('--color-primary-50:')
            ->and($css)->toContain('--color-primary-950:')
            // The import stays where Tailwind needs it.
            ->and($css)->toStartWith('@import "tailwindcss";');
    } finally {
        isRemove($base);
    }
});

it('leaves an application that chose its own accent alone', function () {
    // Matched on the token rather than on our block: an app that wrote
    // `--color-primary-500` anywhere has answered this, and a second answer
    // below it would quietly win.
    $base = isBase();

    try {
        mkdir($base.'/resources/css', 0755, true);
        file_put_contents(
            $base.'/resources/css/app.css',
            "@import \"tailwindcss\";\n\n@theme {\n    --color-primary-500: var(--color-rose-500);\n}\n",
        );

        expect((new InstallScaffold($base))->stylesheetPrimary())->toBe(InstallOutcome::AlreadyPresent);

        expect(file_get_contents($base.'/resources/css/app.css'))
            ->toContain('rose-500')
            ->not->toContain('blue-500');
    } finally {
        isRemove($base);
    }
});

it('refuses a stylesheet that is not a Tailwind entrypoint', function () {
    $base = isBase();

    try {
        mkdir($base.'/resources/css', 0755, true);
        file_put_contents($base.'/resources/css/app.css', "body { margin: 0 }\n");

        expect(fn () => (new InstallScaffold($base))->stylesheetPrimary())
            ->toThrow(AdminInstallException::class);
    } finally {
        isRemove($base);
    }
});
