<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Routing;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route as RouteFacade;
use NyonCode\WireCore\Foundation\Routing\Contracts\RegistersPageRoutes;

/**
 * The route group an application declared in config instead of in a route file.
 *
 * ADR 0026 §5. It reads exactly what a `Route::group()` takes — prefix,
 * middleware, domain — plus the `only`/`except` the macro already had, and hands
 * them to the same {@see ResourceRoutes::all()} the macro calls. There is no
 * second registration path here, only a second place to say the same three
 * things.
 *
 * "Routing is opt-in" (ADR 0020 §5) is about who decides, not which file the
 * decision is typed in. The default is `enabled => false`, so an application
 * that says nothing gets nothing.
 */
final readonly class ConfiguredRoutes implements RegistersPageRoutes
{
    /**
     * Marks that config already registered the pages.
     *
     * A container binding rather than a static: a static would survive between
     * tests in one process and start refusing a macro call that was the first in
     * its own application.
     */
    public const MARKER = 'wire-panels.routes.registered-from-config';

    public function __construct(private Application $app) {}

    public function register(): void
    {
        /** @var array<string, mixed> $config */
        $config = $this->app->make('config')->get('wire-panels.routes', []);

        if (($config['enabled'] ?? false) !== true) {
            return;
        }

        $registrar = RouteFacade::middleware($config['middleware'] ?? []);

        if (($config['prefix'] ?? null) !== null) {
            $registrar = $registrar->prefix($config['prefix']);
        }

        if (($config['domain'] ?? null) !== null) {
            $registrar = $registrar->domain($config['domain']);
        }

        // Marked before the group runs, not after: `ResourceRoutes::all()` is
        // what the macro calls too, and the refusal has to be able to tell the
        // two apart from inside either one.
        $this->app->instance(self::MARKER, true);

        $registrar->group(function () use ($config): void {
            ResourceRoutes::all($config['only'] ?? [], $config['except'] ?? []);
        });
    }
}
