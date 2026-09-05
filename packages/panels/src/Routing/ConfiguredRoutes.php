<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Routing;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route as RouteFacade;
use NyonCode\WireCore\Foundation\Routing\Contracts\RegistersPageRoutes;

/**
 * The route groups an application declared in config instead of in a route file.
 *
 * ADR 0026 §5, extended by ADR 0027 §5. It reads exactly what a `Route::group()`
 * takes — prefix, middleware, domain — plus the `only`/`except` the macro already
 * had, and hands them to the same {@see ResourceRoutes::all()} the macro calls.
 * There is no second registration path here, only a second place to say the same
 * few things.
 *
 * ## Zones
 *
 * `routes.zones` makes it several such groups instead of one, and the array key
 * is the zone: it becomes `Route::name("{$zone}.")`, so the same resource mounted
 * in `admin` and `business` gets `admin.wire.invoices.index` and
 * `business.wire.invoices.index` rather than two routes fighting over one name.
 *
 * That is the reason to prefer this over hand-written groups rather than merely
 * an alternative to them: in a route file `->name('business.')` is a line
 * someone omits, and omitting it makes the later zone win every lookup silently.
 * An array key cannot be omitted and cannot repeat.
 *
 * With no `zones` key it is one unnamed group — what ADR 0026 shipped, unchanged
 * in meaning.
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

        // Marked before any group runs, not after: `ResourceRoutes::all()` is
        // what the macro calls too, and the refusal has to be able to tell the
        // two apart from inside either one.
        $this->app->instance(self::MARKER, true);

        $zones = $config['zones'] ?? [];

        if (! is_array($zones) || $zones === []) {
            // One unnamed group — a single-zone application, and every
            // application that predates zones.
            $this->mount(null, $config);

            return;
        }

        foreach ($zones as $zone => $overrides) {
            // Each zone inherits the top-level values and overrides what it
            // names, so `middleware => ['web']` is written once for all of them
            // and a zone that needs `auth` says only that.
            $this->mount((string) $zone, [...$config, ...(is_array($overrides) ? $overrides : [])]);
        }
    }

    /**
     * One mount point: a route group named after its zone.
     *
     * @param  array<string, mixed>  $config
     */
    private function mount(?string $zone, array $config): void
    {
        $registrar = RouteFacade::middleware($config['middleware'] ?? []);

        if ($zone !== null && $zone !== '') {
            // The route-name prefix ADR 0027 makes a zone's whole identity. Not
            // optional here, which is the point: a hand-written group can forget
            // it, and this cannot.
            $registrar = $registrar->name($zone.'.');
        }

        if (($config['prefix'] ?? null) !== null) {
            $registrar = $registrar->prefix($config['prefix']);
        }

        if (($config['domain'] ?? null) !== null) {
            $registrar = $registrar->domain($config['domain']);
        }

        $registrar->group(function () use ($config): void {
            ResourceRoutes::all($config['only'] ?? [], $config['except'] ?? []);
        });
    }
}
