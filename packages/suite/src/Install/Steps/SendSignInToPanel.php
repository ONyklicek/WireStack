<?php

declare(strict_types=1);

namespace NyonCode\Wire\Install\Steps;

use Illuminate\Routing\Router;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupState;

/**
 * Point Fortify's `home` at the admin, so signing in lands somewhere.
 *
 * Fortify sends a person who has just signed in, registered or confirmed a
 * code to `fortify.home`, and publishes it as `/home` — a path a clean Laravel
 * application does not route. So an application set up by `wire:install` sent
 * its first sign-in to a 404, and only somebody who happened to arrive through
 * a guarded page (Laravel's "intended" URL) ever got into the admin.
 *
 * **A suite step, because nobody else can own it.** The value lives in
 * Fortify's config, which `wire-module-auth` publishes; the place it should
 * point at is the panel's root, which `wire-panels` routes. Neither package may
 * depend on the other (ADR 0029 §5), and the suite is the one that installs
 * both. It needs no class from either — only the file one of them wrote and the
 * routes the other registered.
 *
 * **The application's answer wins.** Only Fortify's own default is replaced. A
 * `home` somebody already changed is theirs, and this step is then done.
 *
 * The panel's root answers for itself: `wire-panels` routes an entry there
 * (`wire.home`) that sends the person to the first page of the admin they may
 * open, or a landing page claims the path outright. Either way the path is the
 * right thing to write — not a page under it, which would depend on which
 * modules happen to be installed.
 */
final readonly class SendSignInToPanel implements SetupStep
{
    /**
     * The `'home' => '/home'` line in the published config, whatever its spacing
     * or quotes — what `fortify:install` publishes, and the only value this
     * replaces.
     */
    private const HOME_LINE = '/([\'"])home\1\s*=>\s*([\'"])\/home\2/';

    /**
     * The group `RegisterResourceRoutes` appends to routes/web.php. Read from the
     * file because the step that wrote it ran in this same process, after the
     * routes were loaded — the router has not heard of it yet.
     */
    private const INSTALLER_GROUP = '/Route::middleware\(\[[^\]]*\]\)(?:->prefix\(([\'"])([^\'"]*)\1\))?->group\(function\s*\(\)\s*\{\s*Route::wireResources\(\);/';

    public function __construct(private Router $router) {}

    public function label(): string
    {
        return 'Sign-in destination';
    }

    public function state(): SetupState
    {
        if (! is_file($this->configPath()) || $this->panelRoot() === null) {
            return SetupState::Blocked;
        }

        return $this->stillDefault() ? SetupState::Pending : SetupState::Done;
    }

    public function summary(): string
    {
        if (! is_file($this->configPath())) {
            return 'needs Fortify set up first — there is no config/fortify.php';
        }

        $root = $this->panelRoot();

        if ($root === null) {
            return 'needs the admin routed first';
        }

        return $this->stillDefault()
            ? "send a signed-in person to {$root} instead of Fortify's /home, which nothing routes"
            : 'fortify.home already points somewhere of this application\'s choosing';
    }

    public function apply(SetupConsole $console): SetupOutcome
    {
        $root = $this->panelRoot();
        $path = $this->configPath();

        if ($root === null || ! is_file($path)) {
            return SetupOutcome::Skipped;
        }

        $contents = (string) file_get_contents($path);
        $replaced = preg_replace(self::HOME_LINE, "'home' => ".var_export($root, true), $contents, 1, $count);

        if ($count !== 1 || ! is_string($replaced)) {
            $console->note('config/fortify.php sets its own home — left as it is.');

            return SetupOutcome::Skipped;
        }

        if (! is_writable($path) || @file_put_contents($path, $replaced) === false) {
            $console->warn("Could not write config/fortify.php — set 'home' => '{$root}' there yourself.");

            return SetupOutcome::Failed;
        }

        // The running process read the old value at boot; a later step in this
        // same run that asks Fortify where home is should get the new answer.
        config()->set('fortify.home', $root);

        $console->note("Signing in now lands on {$root}.");

        return SetupOutcome::Applied;
    }

    public function package(): string
    {
        return 'nyoncode/wire-suite';
    }

    public function sort(): int
    {
        // After Fortify publishes its config (40) and the admin is routed (200):
        // this reads what both of them wrote.
        return 210;
    }

    /**
     * The path the admin answers at, or null while it is not routed.
     *
     * The live router first — an application that routed the panel its own way,
     * or a second run of the installer. Then the group the installer itself
     * appended in this run, which the router cannot know about yet.
     */
    private function panelRoot(): ?string
    {
        foreach ($this->router->getRoutes()->getRoutesByName() as $name => $route) {
            if ($name === 'wire.home' || str_ends_with($name, '.wire.home')) {
                return '/'.ltrim($route->uri(), '/');
            }
        }

        if (config('wire-panels.routes.enabled') === true) {
            return '/'.trim((string) config('wire-panels.routes.prefix', ''), '/');
        }

        $routes = base_path('routes/web.php');

        if (is_file($routes) && preg_match(self::INSTALLER_GROUP, (string) file_get_contents($routes), $group) === 1) {
            return '/'.trim($group[2] ?? '', '/');
        }

        return null;
    }

    private function stillDefault(): bool
    {
        return preg_match(self::HOME_LINE, (string) file_get_contents($this->configPath())) === 1;
    }

    private function configPath(): string
    {
        return config_path('fortify.php');
    }
}
