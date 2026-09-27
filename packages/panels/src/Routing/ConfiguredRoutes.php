<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Routing;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route as RouteFacade;
use NyonCode\WireCore\Foundation\Routing\Contracts\RegistersPageRoutes;
use NyonCode\WirePanels\Exceptions\TenancyConfigurationException;
use NyonCode\WirePanels\Http\Middleware\IdentifyTenant;

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

        // The address above the zones, behind the top-level middleware — it
        // is in no zone, so no zone's `can:` applies to it.
        $entry = $config['zone_entry']['uri'] ?? null;

        if (is_string($entry) && $entry !== '') {
            $registrar = RouteFacade::middleware($config['middleware'] ?? []);

            if (($config['domain'] ?? null) !== null) {
                $registrar = $registrar->domain($config['domain']);
            }

            $registrar->group(fn () => ResourceRoutes::zoneEntry($entry));
        }
    }

    /**
     * One mount point: a route group named after its zone.
     *
     * @param  array<string, mixed>  $config
     */
    private function mount(?string $zone, array $config): void
    {
        // Validated first: the entry below reads the same keys.
        $tenanted = $this->withTenant((string) $zone, $config);
        $this->mountTenantEntry($zone, $config);
        $config = $tenanted;
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

    /**
     * A tenant zone: `{tenant}` in the prefix or in front of the domain, and the
     * middleware that finds it (ADR 0040 §3). The parameter has one name
     * wherever it sits, so one middleware reads both.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function withTenant(string $zone, array $config): array
    {
        $mode = $config['tenant'] ?? null;

        if ($mode === null || $mode === false) {
            return $config;
        }

        $parameter = '{'.IdentifyTenant::PARAMETER.'}';

        if ($mode === 'path') {
            $config['prefix'] = trim(($config['prefix'] ?? '').'/'.$parameter, '/');
        } elseif ($mode === 'domain') {
            if (! is_string($config['domain'] ?? null) || $config['domain'] === '') {
                throw TenancyConfigurationException::domainModeWithoutDomain($zone);
            }

            $config['domain'] = $parameter.'.'.$config['domain'];
        } else {
            throw TenancyConfigurationException::unknownMode($zone, is_string($mode) ? $mode : get_debug_type($mode));
        }

        $config['middleware'] = [...(array) ($config['middleware'] ?? []), IdentifyTenant::ALIAS];

        return $config;
    }

    /**
     * The tenant zone's bare address — `app`, or the domain's root — outside the
     * tenant, and so outside `wire.tenant`: there is no tenant in it to find.
     *
     * @param  array<string, mixed>  $config
     */
    private function mountTenantEntry(?string $zone, array $config): void
    {
        $mode = $config['tenant'] ?? null;

        if ($mode !== 'path' && $mode !== 'domain') {
            return;
        }

        $registrar = RouteFacade::middleware($config['middleware'] ?? []);

        if ($zone !== null && $zone !== '') {
            $registrar = $registrar->name($zone.'.');
        }

        $prefix = trim((string) ($config['prefix'] ?? ''), '/');
        $domain = $config['domain'] ?? null;

        if (is_string($domain) && $domain !== '') {
            $registrar = $registrar->domain($domain);
        }

        $to = $mode === 'path'
            ? trim($prefix.'/{'.IdentifyTenant::PARAMETER.'}', '/')
            : '//{'.IdentifyTenant::PARAMETER.'}.'.$domain.($prefix === '' ? '' : '/'.$prefix);

        $registrar->group(fn () => ResourceRoutes::tenantEntry($prefix === '' ? '/' : $prefix, $to));
    }
}
