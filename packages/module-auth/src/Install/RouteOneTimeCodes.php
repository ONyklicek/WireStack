<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Install;

use NyonCode\WireCore\Foundation\Routing\WireRoutes;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\RoutesFile;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WireModuleAuth\Routing\CodeRoutes;

/**
 * The `auth-codes` route group in routes/web.php — the one-time code flows.
 *
 * Written the way the panel's group is written: into the application's own
 * route file, once, and never where the file or `wire-core.routes.groups`
 * already places the group (ADR 0041).
 * Written even while every flow is off, which routes nothing: switching one on
 * is then a config change, not a config change and a route file to remember.
 * Nothing is asked — the middleware, guard and throttle are Fortify's, read by
 * the macro itself.
 */
final readonly class RouteOneTimeCodes implements SetupStep
{
    private RoutesFile $routes;

    public function __construct(?RoutesFile $routes = null)
    {
        $this->routes = $routes ?? RoutesFile::forApplication();
    }

    public function label(): string
    {
        return 'One-time code routes';
    }

    public function state(): SetupState
    {
        if (! $this->routes->exists()) {
            return SetupState::Blocked;
        }

        return $this->placed() ? SetupState::Done : SetupState::Pending;
    }

    public function summary(): string
    {
        if (! $this->routes->exists()) {
            return 'no routes/web.php to add them to';
        }

        return $this->placed()
            ? 'the auth-codes route group is already placed'
            : "add Route::wire('auth-codes') to routes/web.php";
    }

    public function apply(SetupConsole $console): SetupOutcome
    {
        $group = <<<'PHP'


        // Added by php artisan wire:install. The one-time code flows — each one
        // routed only while its switch under wire-module-auth.codes is on; see
        // docs/modules/auth.md.
        Route::wire('auth-codes');

        PHP;

        if (! $this->routes->append($group)) {
            $console->warn('Could not write routes/web.php — add this yourself:'.$group);

            return SetupOutcome::Failed;
        }

        return SetupOutcome::Applied;
    }

    private function placed(): bool
    {
        return $this->routes->wires(CodeRoutes::key()) || app(WireRoutes::class)->configured(CodeRoutes::key()) !== [];
    }

    public function package(): string
    {
        return 'nyoncode/wire-module-auth';
    }

    public function sort(): int
    {
        // Beside the panel's own routes (200), before the first administrator.
        return 220;
    }
}
