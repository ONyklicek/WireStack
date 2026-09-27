<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Routing;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use NyonCode\WirePanels\Contracts\HasTenants;
use NyonCode\WirePanels\Exceptions\TenancyConfigurationException;

/**
 * A tenant zone's own address without a tenant — `/app` — and where it leads.
 *
 * Every page of the zone lives under a tenant, so the zone's bare address used
 * to be a 404, and it is the one address a person types and the one signing in
 * lands on. It answers the way {@see ZoneEntry} answers one level up: straight
 * to the person's {@see HasTenants::getDefaultTenant()}, and from there
 * {@see PanelEntry} takes them to the first page they may open (ADR 0040 §7).
 *
 * Someone with no tenant at all gets `routes.tenant_entry.view` when the
 * application names one — the place to offer registering a company — and a
 * 403 that says why when it does not.
 *
 * Where a tenant's address is comes from the route, as a template (`app/{tenant}`,
 * or `//{tenant}.example.com` for a domain zone), so one controller serves a
 * path zone and a domain zone alike.
 */
final readonly class TenantEntry
{
    /** The route default the address template travels in. */
    public const TARGET = '_wire_tenant_target';

    public function __construct(
        private Repository $config,
        private Factory $views,
    ) {}

    public function __invoke(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        if (! $user instanceof HasTenants) {
            throw TenancyConfigurationException::userCannotHaveTenants($user::class);
        }

        $tenant = $user->getDefaultTenant();

        if ($tenant === null) {
            $view = $this->config->get('wire-panels.routes.tenant_entry.view');

            abort_unless(is_string($view) && $view !== '', 403, __('wire-panels::messages.no_tenant'));

            return $this->views->make($view);
        }

        $target = (string) $request->route(self::TARGET);

        return redirect()->to(str_replace('{tenant}', (string) $tenant->getRouteKey(), $target));
    }
}
