<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Install;

use NyonCode\WireCore\Foundation\Setup\Answers;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupState;

/**
 * The line that turns registered resources into pages you can open.
 *
 * `wire-admin`'s installer ended with it — "Route::wireResources() in
 * routes/web.php, inside the middleware you want" — and so did every version of
 * `wire:install`. It is the last thing standing between a complete installation
 * and a 404 on every screen, and it was left as a sentence because the prefix
 * and the middleware are genuinely the application's to choose. So this asks
 * for them instead of guessing.
 *
 * ## Into routes/web.php, not into config
 *
 * `wire-panels.routes.enabled` can register the same pages, and writing a
 * config key would have been the tidier edit. It does not work here: this
 * package ships no install command, so its config is never published, and a key
 * written into `vendor/` is a key the next `composer update` throws away.
 * `routes/web.php` is the application's own file, it is where the documentation
 * has always pointed, and `wire-admin`'s installer already edits `app.css` and
 * `bootstrap/providers.php` the same way.
 *
 * Never twice, and never over anything: the file is appended to only when it
 * does not already mention the macro.
 */
final class RegisterResourceRoutes implements SetupStep
{
    /** What we look for to decide the application has already done this. */
    private const MACRO = 'wireResources';

    /** A path under the application root: segments of URL-safe characters, route parameters included. */
    private const PREFIX = '/^[A-Za-z0-9._~{}-]+(\/[A-Za-z0-9._~{}-]+)*$/';

    /** A middleware alias, group or class, with its parameters. */
    private const MIDDLEWARE = '/^[A-Za-z0-9_.:|\\\\-]+$/';

    public function label(): string
    {
        return 'Routes';
    }

    public function state(): SetupState
    {
        if (config('wire-panels.routes.enabled') === true) {
            return SetupState::Done;
        }

        if (! is_file($this->path())) {
            return SetupState::Blocked;
        }

        return $this->alreadyRouted() ? SetupState::Done : SetupState::Pending;
    }

    public function summary(): string
    {
        if (config('wire-panels.routes.enabled') === true) {
            return 'registered from wire-panels.routes';
        }

        if (! is_file($this->path())) {
            return 'no routes/web.php to add them to';
        }

        return $this->alreadyRouted()
            ? 'routes/web.php already calls Route::wireResources()'
            : 'add Route::wireResources() to routes/web.php';
    }

    public function apply(SetupConsole $console): SetupOutcome
    {
        // Both answers are written into PHP source, so both are held to a shape
        // first: a quote in either left `routes/web.php` a parse error, and every
        // request to the application with it.
        $answers = new Answers($console);
        $prefix = $answers->until(
            static fn (): string => trim($console->ask('URL prefix for the admin', 'admin'), '/'),
            static fn (string $prefix): ?string => $prefix === '' || preg_match(self::PREFIX, $prefix) === 1
                ? null
                : "Not a URL prefix: {$prefix} — letters, digits, `-`, `_`, `.`, `{}` and `/` only",
        );
        $middleware = $prefix === null ? null : $answers->until(
            static fn (): string => $console->ask('Middleware, comma separated', 'web,auth'),
            fn (string $list): ?string => $this->middlewareProblem($list),
        );

        if ($prefix === null || $middleware === null) {
            $console->warn('Nothing was written to routes/web.php.');

            return SetupOutcome::Skipped;
        }

        $group = $this->group($prefix, $middleware);

        // Asked first, and the write suppressed: an unwritable `routes/web.php`
        // is a permissions problem, and PHP answers one with a warning rather
        // than a return value — which under a test runner is an exception out
        // of a step that has a perfectly good way to report it.
        if (! is_writable($this->path()) || @file_put_contents($this->path(), $group, FILE_APPEND) === false) {
            $console->warn('Could not write routes/web.php — add this yourself:'.$group);

            return SetupOutcome::Failed;
        }

        $console->note($prefix === ''
            ? 'Your pages are at the application root.'
            : "Your pages are under /{$prefix}.");

        return SetupOutcome::Applied;
    }

    public function package(): string
    {
        return 'nyoncode/wire-panels';
    }

    public function sort(): int
    {
        // After the tables and before the first administrator: the account is
        // the last thing you need, and it is worth being told where to use it.
        return 200;
    }

    /**
     * The group to append, with the choices written into it.
     *
     * A real `Route::middleware(...)->prefix(...)->group(...)` rather than the
     * config key, so the next person reading `routes/web.php` can see what is
     * routed and change it without learning where this framework keeps things.
     */
    private function group(string $prefix, string $middleware): string
    {
        $list = implode(', ', array_map(
            static fn (string $name): string => var_export($name, true),
            $this->middlewareNames($middleware),
        ));

        $prefixCall = $prefix === '' ? '' : '->prefix('.var_export($prefix, true).')';

        return <<<PHP


        // Added by php artisan wire:install. Every resource and dashboard that
        // declares pages is routed here — see Route::wireResource() to name one.
        Route::middleware([{$list}]){$prefixCall}->group(function () {
            Route::wireResources();
        });

        PHP;
    }

    /**
     * What is wrong with a middleware list, or null when nothing is.
     */
    private function middlewareProblem(string $list): ?string
    {
        foreach ($this->middlewareNames($list) as $name) {
            if (preg_match(self::MIDDLEWARE, $name) !== 1) {
                return "Not a middleware name: {$name}";
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function middlewareNames(string $list): array
    {
        return array_values(array_filter(
            array_map(trim(...), explode(',', $list)),
            static fn (string $name): bool => $name !== '',
        ));
    }

    private function alreadyRouted(): bool
    {
        return str_contains((string) file_get_contents($this->path()), self::MACRO);
    }

    private function path(): string
    {
        return base_path('routes/web.php');
    }
}
