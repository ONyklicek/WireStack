<?php

declare(strict_types=1);

namespace NyonCode\WireAdmin\Install;

use NyonCode\WireAdmin\Exceptions\AdminInstallException;

/**
 * The three things `composer require` cannot do for an application.
 *
 * Installing the package gives it a layout component and a sidebar. What still
 * has to happen is that the application *names* a layout — which is a file in
 * its own `resources/views` and a line in `bootstrap/providers.php` — and this
 * is that step, written so it can be tested rather than described.
 *
 * **Nothing here is done by a service provider**, and that is the line ADR 0028
 * draws: installing must not be the same act as adopting. Running
 * `php artisan wire-admin:install` is the application asking, which is a
 * different thing from having the package on disk.
 *
 * Every step is idempotent and never overwrites: an application that ran the
 * installer, edited its layout and ran it again keeps its edits.
 */
final readonly class InstallScaffold
{
    /**
     * The one line an application needs so Tailwind sees the packages' views.
     *
     * Relative to `resources/css/`, which is where a Laravel stylesheet lives,
     * and naming the vendor directory rather than one package so a module
     * installed tomorrow is covered without another edit.
     */
    public const SOURCE_LINE = '@source "../../vendor/nyoncode";';

    /**
     * The accent every wire component reaches for, mapped to Tailwind's blue.
     *
     * `primary` is not a colour this framework ships — it is a name its
     * components use, and until something defines it the name resolves to
     * nothing: `bg-primary-600` is not a class, so the primary button is
     * transparent with white text on it. Invisible, on a fresh install, with no
     * error anywhere. The documentation said "you must define it", which is a
     * fine sentence and a bad default.
     *
     * Blue because it is the one hue that reads as *the action* in an
     * administration without also meaning something: green, amber and red are
     * spoken for by success, warning and danger, and an accent that collides
     * with a signal is an accent that has to be explained. It is a starting
     * point, not a decision — one edit here and the whole stack follows.
     */
    public const PRIMARY_THEME = <<<'CSS'
        @theme {
            /* wire-admin: the accent every wire component reaches for.
               Point these at any Tailwind palette to rebrand the whole stack. */
            --color-primary-50: var(--color-blue-50);
            --color-primary-100: var(--color-blue-100);
            --color-primary-200: var(--color-blue-200);
            --color-primary-300: var(--color-blue-300);
            --color-primary-400: var(--color-blue-400);
            --color-primary-500: var(--color-blue-500);
            --color-primary-600: var(--color-blue-600);
            --color-primary-700: var(--color-blue-700);
            --color-primary-800: var(--color-blue-800);
            --color-primary-900: var(--color-blue-900);
            --color-primary-950: var(--color-blue-950);
        }
        CSS;

    public function __construct(private string $basePath) {}

    /**
     * Write the application's own layout view, unless it wrote one already.
     *
     * The view is the app's, not the package's — it names
     * `<x-wire-admin::layout>` and fills its slots — so it goes to
     * `resources/views/`, not through `vendor:publish`.
     *
     * @throws AdminInstallException When the directory is not there and cannot be made.
     */
    public function layout(string $relativePath = 'resources/views/components/layouts/admin.blade.php'): InstallOutcome
    {
        $target = $this->basePath.'/'.ltrim($relativePath, '/');

        if (is_file($target)) {
            return InstallOutcome::AlreadyPresent;
        }

        $directory = dirname($target);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw AdminInstallException::layoutDirectoryNotWritable($directory);
        }

        file_put_contents($target, (string) file_get_contents(__DIR__.'/../../stubs/admin-layout.blade.stub'));

        return InstallOutcome::Created;
    }

    /**
     * Point the application's Tailwind entrypoint at the packages' views.
     *
     * Tailwind 4 skips anything in `.gitignore`, and `vendor/` is in it, so a
     * shell installed from Packagist ships views whose classes are never
     * compiled — the sidebar renders with no width, no colour and no spacing,
     * and nothing anywhere reports an error. One `@source` line fixes it, and it
     * covers every `nyoncode/wire-*` package at once, including modules the
     * application adds later.
     *
     * The line goes after the last at-rule in the file's opening block, so it
     * lands below `@import "tailwindcss"` — where Tailwind needs it — and above
     * a `@theme` an application has already written.
     *
     * @throws AdminInstallException When there is no Tailwind entrypoint to edit.
     */
    public function stylesheetSources(string $relativePath = 'resources/css/app.css'): InstallOutcome
    {
        $file = $this->basePath.'/'.ltrim($relativePath, '/');

        if (! is_file($file)) {
            throw AdminInstallException::stylesheetMissing($file, self::SOURCE_LINE);
        }

        $contents = (string) file_get_contents($file);

        // Matched on the path rather than on the whole line: an application that
        // reformatted it, or wrote a narrower source of its own into the same
        // directory, has already answered this question.
        if (str_contains($contents, 'vendor/nyoncode')) {
            return InstallOutcome::AlreadyPresent;
        }

        if (preg_match('/^\s*@import\s+["\']tailwindcss/m', $contents) !== 1) {
            throw AdminInstallException::stylesheetNotTailwind($file, self::SOURCE_LINE);
        }

        // The last of the leading at-rules — `@import`, `@plugin`, `@source`,
        // `@custom-variant` — rather than the first, so repeated runs against a
        // hand-edited file keep the block in one piece.
        preg_match_all('/^@(?:import|plugin|source|custom-variant)[^\n]*\n/m', $contents, $m, PREG_OFFSET_CAPTURE);
        $last = $m[0][array_key_last($m[0])];
        $at = $last[1] + strlen($last[0]);

        file_put_contents($file, substr($contents, 0, $at).self::SOURCE_LINE."\n".substr($contents, $at));

        return InstallOutcome::Created;
    }

    /**
     * Give the stylesheet a `primary` palette, unless it already has one.
     *
     * Appended at the end rather than spliced into the opening block: a
     * `@theme` is a declaration, not an import, and Tailwind reads it wherever
     * it sits — while the end is the one position that cannot land between two
     * at-rules an application meant to keep together.
     *
     * Idempotent on the token rather than on the block, because an application
     * that wrote its own `--color-primary-500` anywhere in the file has already
     * answered this and must not be given a second answer below it.
     *
     * @throws AdminInstallException When there is no Tailwind entrypoint to edit.
     */
    public function stylesheetPrimary(string $relativePath = 'resources/css/app.css'): InstallOutcome
    {
        $file = $this->basePath.'/'.ltrim($relativePath, '/');

        if (! is_file($file)) {
            throw AdminInstallException::stylesheetMissing($file, self::PRIMARY_THEME);
        }

        $contents = (string) file_get_contents($file);

        if (str_contains($contents, '--color-primary-500')) {
            return InstallOutcome::AlreadyPresent;
        }

        if (preg_match('/^\s*@import\s+["\']tailwindcss/m', $contents) !== 1) {
            throw AdminInstallException::stylesheetNotTailwind($file, self::PRIMARY_THEME);
        }

        file_put_contents($file, rtrim($contents, "\n")."\n\n".self::PRIMARY_THEME."\n");

        return InstallOutcome::Created;
    }

    /**
     * Add the published provider to `bootstrap/providers.php`.
     *
     * That file is Laravel 11's provider list and the reason this is not left to
     * the toolkit: its helper appends to `config/app.php`, which a modern
     * application does not have. Absent it — or shaped in a way this cannot read
     * — it throws rather than reporting a success nobody got; the command catches
     * that one case and prints the line to add, because the rest of the install
     * is still worth finishing.
     *
     * The match is on the class name rather than on a formatted line, because an
     * application may have reformatted the file since the last run.
     *
     * @throws AdminInstallException When there is no list to edit, or it is not one this understands.
     */
    public function registerProvider(string $provider = 'App\\Providers\\WireAdminServiceProvider'): InstallOutcome
    {
        $file = $this->basePath.'/bootstrap/providers.php';

        if (! is_file($file)) {
            throw AdminInstallException::providerListMissing($file, $provider);
        }

        $contents = (string) file_get_contents($file);

        if (str_contains($contents, $provider)) {
            return InstallOutcome::AlreadyPresent;
        }

        // Anchored on the closing bracket of the returned array — the one shape
        // every generated `bootstrap/providers.php` has — so a file that has been
        // rewritten into something else is reported rather than mangled.
        if (preg_match('/^(?<body>.*?)(?<indent>[ \t]*)\];\s*$/s', $contents, $m) !== 1) {
            throw AdminInstallException::providerListNotEditable($file, $provider);
        }

        $entry = $m['indent']."    {$provider}::class,\n";

        file_put_contents($file, $m['body'].$entry.$m['indent']."];\n");

        return InstallOutcome::Created;
    }
}
