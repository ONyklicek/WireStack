<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Routing;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The address above the zones — `/` in an application that has several — and
 * the one Fortify's `home` points at, so it is where signing in ends.
 *
 * It sends the person straight into the zone they land in
 * ({@see ZoneDirectory::landingFor()}): their own preference, the primary zone,
 * or the only one they can reach. Only when that leaves a real choice does it
 * show the picker — `wire-panels.routes.zone_entry.view`, handed `$zones` (key
 * => URL) — and without one it takes the first zone they can reach, which is
 * never worse than the 404 the address used to be.
 *
 * **Choosing again stays possible.** `?choose` skips the landing and shows the
 * picker even to somebody who has a primary zone, which is what a zone
 * switcher's "all zones" link points at: `route('wire.zones', ['choose' => 1])`.
 */
final readonly class ZoneEntry
{
    public const CHOOSE = 'choose';

    public function __construct(
        private ZoneDirectory $zones,
        private Repository $config,
        private Factory $views,
    ) {}

    public function __invoke(Request $request): RedirectResponse|View
    {
        $user = $request->user();
        $reachable = $this->zones->reachableBy($user);

        if ($reachable === []) {
            // Nothing routed is a missing page; zones that all refuse this
            // person are a refusal. The same split PanelEntry makes.
            throw new HttpException($this->zones->all() === [] ? 404 : 403);
        }

        $landing = $request->has(self::CHOOSE) ? null : $this->zones->landingFor($user);

        if ($landing !== null) {
            return redirect()->to($reachable[$landing]);
        }

        $view = $this->config->get('wire-panels.routes.zone_entry.view');

        if (! is_string($view) || $view === '') {
            return redirect()->to((string) reset($reachable));
        }

        return $this->views->make($view, [
            'zones' => $reachable,
            'primary' => $this->zones->landingFor($user),
        ]);
    }
}
