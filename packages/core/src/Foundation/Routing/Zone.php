<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Routing;

use Illuminate\Support\Facades\Route;
use NyonCode\WirePanels\Resources\Concerns\ResolvesOneRecord;

/**
 * Which mount point the current page is in — a route-name prefix, or none.
 *
 * A zone is not a registered thing (ADR 0027 §1). It is the `name()` of the route
 * group an application put its pages in, so `business.wire.invoices.index` is the
 * invoices index *in the business zone*, and the whole identity of that zone is
 * the string `business.`. What multiplies across zones is the mount point, never
 * the registration: the catalogue still holds one key per class.
 *
 * ## Call this during a full page render, and nowhere else
 *
 * `Route::currentRouteName()` answers `livewire.update` during a Livewire round
 * trip — measured, not assumed. So this is right the first time a page renders
 * and wrong on every request after it, which is the worst shape a helper can
 * have: the palette searches on every keystroke and a sidebar re-renders on
 * every navigation, and both would keep rendering perfectly while linking out of
 * the zone.
 *
 * The rule is therefore the one {@see ResolvesOneRecord}
 * already follows: **the identifier travels, the thing is resolved per request.**
 * Read the zone once in `mount()`, keep it in a public property so Livewire
 * snapshots it, and pass that property afterwards.
 *
 *   public ?string $zone = null;
 *
 *   public function mount(): void
 *   {
 *       $this->zone = Zone::current();
 *   }
 */
final class Zone
{
    /**
     * The zone of the route being rendered, or null when there is none.
     *
     * Matched rather than searched for, because the obvious `str_contains($name,
     * 'wire.')` is a trap with a name: **`livewire.update` contains `wire.`**, so
     * a substring search finds a zone called `li` on exactly the request where
     * there is no zone to find. The shape has to be anchored — an optional
     * prefix, then `wire.`, then a key and a page and nothing else.
     */
    public static function current(): ?string
    {
        return self::of(Route::currentRouteName());
    }

    /**
     * The zone a route name belongs to, or null for an unzoned or non-wire name.
     *
     * Separate from {@see current()} so the rule is testable without a request,
     * and so a caller holding a route name can ask directly.
     */
    public static function of(?string $routeName): ?string
    {
        if ($routeName === null) {
            return null;
        }

        if (preg_match('/^(?<zone>.+\.)?wire\.[^.]+\.[^.]+$/', $routeName, $m) !== 1) {
            return null;
        }

        return ($m['zone'] ?? '') === '' ? null : $m['zone'];
    }

    /**
     * A zone as a route-name prefix: `business` and `business.` both give
     * `business.`, and null or empty gives the empty string.
     *
     * Written once here because every caller that builds a route name needs the
     * same normalisation, and a trailing dot is exactly the kind of thing two
     * call sites disagree about.
     */
    public static function prefix(?string $zone): string
    {
        $zone = trim((string) $zone, '.');

        return $zone === '' ? '' : $zone.'.';
    }
}
