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

it('switches on the forms plugin and the class-based dark variant in a fresh stylesheet', function () {
    // What `laravel new` writes: Tailwind, sources, a theme — and neither rule.
    // Without the plugin the field views have no border and no padding; without
    // the variant the shell's theme switch never reaches a `dark:` class.
    $base = isBase();

    try {
        mkdir($base.'/resources/css', 0755, true);
        file_put_contents($base.'/resources/css/app.css', <<<'CSS'
        @import 'tailwindcss';

        @source '../../storage/framework/views/*.php';

        @theme {
            --font-sans: 'Instrument Sans', ui-sans-serif;
        }

        CSS);

        expect((new InstallScaffold($base))->stylesheetBase())->toBe(InstallOutcome::Created);

        $contents = (string) file_get_contents($base.'/resources/css/app.css');

        expect($contents)->toContain(InstallScaffold::BASE_RULES['@tailwindcss/forms'])
            ->toContain(InstallScaffold::BASE_RULES['@custom-variant dark'])
            // Below the leading at-rules and above the theme, where Tailwind reads them.
            ->and(strpos($contents, '@plugin'))->toBeGreaterThan(strpos($contents, '@source'))
            ->and(strpos($contents, '@custom-variant'))->toBeLessThan(strpos($contents, '@theme'));

        // Idempotent: a second run finds both.
        expect((new InstallScaffold($base))->stylesheetBase())->toBe(InstallOutcome::AlreadyPresent)
            ->and(substr_count((string) file_get_contents($base.'/resources/css/app.css'), '@plugin'))->toBe(1);
    } finally {
        isRemove($base);
    }
});

it('adds only the rule the stylesheet is missing, and keeps one written in the application s words', function () {
    $base = isBase();

    try {
        mkdir($base.'/resources/css', 0755, true);
        file_put_contents($base.'/resources/css/app.css', "@import \"tailwindcss\";\n@custom-variant dark (&:is(.dark *));\n");

        expect((new InstallScaffold($base))->stylesheetBase())->toBe(InstallOutcome::Created);

        $contents = (string) file_get_contents($base.'/resources/css/app.css');

        expect($contents)->toContain('@plugin "@tailwindcss/forms";')
            ->toContain('@custom-variant dark (&:is(.dark *));')
            ->and(substr_count($contents, '@custom-variant'))->toBe(1);
    } finally {
        isRemove($base);
    }
});

it('refuses the base rules for a missing stylesheet and for one that is not Tailwind', function () {
    $base = isBase();

    try {
        expect(fn () => (new InstallScaffold($base))->stylesheetBase())
            ->toThrow(AdminInstallException::class, '@tailwindcss/forms');

        mkdir($base.'/resources/css', 0755, true);
        file_put_contents($base.'/resources/css/app.css', "body { margin: 0; }\n");

        expect(fn () => (new InstallScaffold($base))->stylesheetBase())
            ->toThrow(AdminInstallException::class);
    } finally {
        isRemove($base);
    }
});

it('puts the forms plugin into package.json, so the build that follows installs it', function () {
    $base = isBase();

    try {
        file_put_contents($base.'/package.json', json_encode([
            'private' => true,
            'devDependencies' => ['vite' => '^7.0.0', 'tailwindcss' => '^4.0.0'],
        ]));

        expect((new InstallScaffold($base))->formsPackage())->toBe(InstallOutcome::Created);

        $json = json_decode((string) file_get_contents($base.'/package.json'), true);

        expect($json['devDependencies'])->toHaveKey('@tailwindcss/forms')
            ->and($json['devDependencies']['vite'])->toBe('^7.0.0')
            ->and($json['private'])->toBeTrue()
            // Sorted, the way npm itself writes the section.
            ->and(array_key_first($json['devDependencies']))->toBe('@tailwindcss/forms');

        expect((new InstallScaffold($base))->formsPackage())->toBe(InstallOutcome::AlreadyPresent);
    } finally {
        isRemove($base);
    }
});

it('leaves the forms plugin alone where the application already depends on it', function () {
    $base = isBase();

    try {
        file_put_contents($base.'/package.json', json_encode(['dependencies' => ['@tailwindcss/forms' => '0.5.7']]));

        expect((new InstallScaffold($base))->formsPackage())->toBe(InstallOutcome::AlreadyPresent);
    } finally {
        isRemove($base);
    }
});

it('reports a package.json it cannot read instead of writing into it', function () {
    $base = isBase();

    try {
        expect(fn () => (new InstallScaffold($base))->formsPackage())
            ->toThrow(AdminInstallException::class, 'npm install -D @tailwindcss/forms');

        file_put_contents($base.'/package.json', '{ not json');

        expect(fn () => (new InstallScaffold($base))->formsPackage())
            ->toThrow(AdminInstallException::class, 'not valid JSON');
    } finally {
        isRemove($base);
    }
});

// ─── The dashboard the admin lands on ────────────────────────────────────────

/*
 * Why the installer writes one at all: without a dashboard the shell's own
 * address has nothing of its own to show, so it forwards to whichever screen
 * sorts first in the sidebar. Measured on a fresh `wire:install --all` — zero
 * dashboards registered, and the admin's address led to the media library.
 */

/**
 * The published config, shaped the way wire-core actually publishes it.
 *
 * The comment block matters and is not decoration: the real file explains the
 * key by writing it out — `|   'dashboards' => [ … ]` — and the first version of
 * this installer matched *that* one. It wrote the entry inside the comment,
 * where it parses fine, changes nothing and still reports success. A fixture
 * without the block passed every assertion here while a clean install came out
 * with an empty list.
 */
function isConfigWith(string $base, string $dashboards): void
{
    mkdir($base.'/config', 0755, true);
    file_put_contents($base.'/config/wire-core.php', <<<PHP
        <?php

        return [
            'resources' => [
                //
            ],

            /*
            |----------------------------------------------------------------
            | Dashboards
            |----------------------------------------------------------------
            |
            |   'dashboards' => [
            |       App\Dashboards\SalesDashboard::class,
            |   ],
            |
            */
            'dashboards' => [
        {$dashboards}
            ],
        ];
        PHP);
}

it('writes a dashboard and the page that mounts it', function () {
    $base = isBase();

    try {
        $outcome = (new InstallScaffold($base))->dashboard();

        $dashboard = (string) file_get_contents($base.'/app/Dashboards/OverviewDashboard.php');
        $page = (string) file_get_contents($base.'/app/Livewire/Dashboards/ShowOverview.php');

        expect($outcome)->toBe(InstallOutcome::Created)
            ->and($dashboard)->toContain('class OverviewDashboard extends Dashboard')
            // The three that put it at the admin's own address rather than
            // under it, which is what makes signing in open a dashboard.
            ->and($dashboard)->toContain('return self::ROOT;')
            ->and($dashboard)->toContain("'index' => ShowOverview::class")
            ->and($dashboard)->toContain('ConfiguresRoutes')
            // Ungrouped, so it renders above every group a module declares.
            ->and($dashboard)->not->toContain('->group(')
            ->and($page)->toContain('extends DashboardPage')
            ->and($page)->toContain('OverviewDashboard::class');
    } finally {
        isRemove($base);
    }
});

it('leaves a dashboard somebody already has, page included', function () {
    $base = isBase();

    try {
        mkdir($base.'/app/Dashboards', 0755, true);
        file_put_contents($base.'/app/Dashboards/OverviewDashboard.php', '<?php // mine');

        $outcome = (new InstallScaffold($base))->dashboard();

        expect($outcome)->toBe(InstallOutcome::AlreadyPresent)
            ->and(file_get_contents($base.'/app/Dashboards/OverviewDashboard.php'))->toBe('<?php // mine')
            // And the page is not written back underneath an application that
            // deleted it and mounts the dashboard its own way.
            ->and(is_file($base.'/app/Livewire/Dashboards/ShowOverview.php'))->toBeFalse();
    } finally {
        isRemove($base);
    }
});

it('refuses when the dashboard directory cannot be made, and says which file', function () {
    // `app/` present as a *file* is the shape that makes mkdir fail, the same
    // one the layout step is checked with. The message names the file it was
    // writing rather than the layout stub, which is the whole reason this does
    // not share the layout's exception.
    $base = isBase();

    try {
        file_put_contents($base.'/app', 'not a directory');

        expect(fn () => (new InstallScaffold($base))->dashboard())
            ->toThrow(AdminInstallException::class, 'OverviewDashboard.php] could not be written');
    } finally {
        isRemove($base);
    }
});

it('registers the dashboard in the published config, replacing the placeholder', function () {
    $base = isBase();

    try {
        isConfigWith($base, '        //');

        $outcome = (new InstallScaffold($base))->registerDashboard();
        $config = (string) file_get_contents($base.'/config/wire-core.php');

        expect($outcome)->toBe(InstallOutcome::Created)
            ->and($config)->toContain('\App\Dashboards\OverviewDashboard::class,')
            // The list it was not asked about is untouched, placeholder and all.
            ->and($config)->toContain("'resources' => [");

        // The `//` a published list carries would read as commented-out code
        // once something sits under it, so the dashboards list loses it — and
        // only that one.
        preg_match("/'dashboards' => \[(.*?)\]/s", $config, $block);

        expect($block[1])->not->toContain('//');

        // And the file is still PHP that returns the list with the class in it.
        $parsed = require $base.'/config/wire-core.php';

        expect($parsed['dashboards'])->toBe(['App\Dashboards\OverviewDashboard']);
    } finally {
        isRemove($base);
    }
});

it('keeps a dashboard list somebody has already written in', function () {
    $base = isBase();

    try {
        isConfigWith($base, '        \App\Dashboards\SalesDashboard::class,');

        $outcome = (new InstallScaffold($base))->registerDashboard();
        $parsed = require $base.'/config/wire-core.php';

        expect($outcome)->toBe(InstallOutcome::Created)
            ->and($parsed['dashboards'])->toBe([
                'App\Dashboards\SalesDashboard',
                'App\Dashboards\OverviewDashboard',
            ]);
    } finally {
        isRemove($base);
    }
});

it('says so rather than registering twice', function () {
    $base = isBase();

    try {
        isConfigWith($base, '        \App\Dashboards\OverviewDashboard::class,');

        expect((new InstallScaffold($base))->registerDashboard())->toBe(InstallOutcome::AlreadyPresent);
    } finally {
        isRemove($base);
    }
});

it('reports an unpublished config instead of silently registering nothing', function () {
    $base = isBase();

    try {
        expect(fn () => (new InstallScaffold($base))->registerDashboard())
            ->toThrow(AdminInstallException::class, 'vendor:publish');
    } finally {
        isRemove($base);
    }
});

it('refuses a config with no dashboards list rather than mangling it', function () {
    $base = isBase();

    try {
        mkdir($base.'/config', 0755, true);
        file_put_contents($base.'/config/wire-core.php', "<?php\n\nreturn ['resources' => []];\n");

        expect(fn () => (new InstallScaffold($base))->registerDashboard())
            ->toThrow(AdminInstallException::class, 'by hand');
    } finally {
        isRemove($base);
    }
});

it('writes into the list rather than into the comment that describes it', function () {
    $base = isBase();

    try {
        isConfigWith($base, '        //');

        (new InstallScaffold($base))->registerDashboard();

        $config = (string) file_get_contents($base.'/config/wire-core.php');
        $parsed = require $base.'/config/wire-core.php';

        // What the first version did: the class landed between the `|` lines,
        // the file still parsed, and the list stayed empty.
        expect($parsed['dashboards'])->toBe(['App\Dashboards\OverviewDashboard'])
            ->and($config)->toContain('|       App\Dashboards\SalesDashboard::class,')
            ->and($config)->not->toContain('|       App\Dashboards\OverviewDashboard::class,');
    } finally {
        isRemove($base);
    }
});
