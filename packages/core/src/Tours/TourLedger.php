<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Tours;

use Illuminate\Contracts\Auth\Authenticatable;
use NyonCode\WireCore\Foundation\Preferences\Contracts\PreferenceDriver;
use NyonCode\WireCore\Foundation\Preferences\PreferenceManager;

/**
 * What each user has acknowledged, and at which version.
 *
 * One surface in the {@see PreferenceDriver} store, holding a flat map of tour
 * id to the `since()` value that was current when the user finished or skipped
 * it:
 *
 *     ['getting-started' => '2.2', 'sales-orders' => '1']
 *
 * ## Why this is a preference and not a table of its own
 *
 * Because `Foundation\Preferences` is already "a per-user JSON bag keyed by a
 * surface and a user", and its contract states the bag is open on purpose —
 * a driver must keep keys it does not recognise, so that a surface can grow
 * state without a contract change. A `wire_tours` table would have been a second
 * implementation of a store that exists, which the adapter rule forbids.
 *
 * ## Its own config prefix, and why that matters more here than elsewhere
 *
 * `wire-core.tours.preferences` rather than `wire-core.preferences`, because the
 * shipped default of the latter is the **null driver, which stores nothing**. A
 * table that forgets its hidden columns is an annoyance; a tour on a store that
 * forgets is a walkthrough that interrupts the same person on every page load,
 * for ever.
 *
 * The tour prefix therefore defaults to `session` — the worst untouched-config
 * behaviour becomes "once per session" instead — and the docs tell an
 * application that wants "once, ever" to set `database` and run the migration.
 *
 * ## Scope is not in the key
 *
 * An administrator's tour and a sales tour are two ids in one bag, so somebody
 * whose role changes has acknowledged one and not the other, and sees the other.
 * That is the wanted behaviour and it needs no rule: it falls out of each tour
 * having its own id.
 */
final class TourLedger
{
    /** The surface these live under, alongside a table's or a dashboard's. */
    public const SURFACE = 'tours';

    public const CONFIG_PREFIX = 'wire-core.tours.preferences';

    public function __construct(private readonly ?PreferenceDriver $driver = null) {}

    /**
     * The versions this user has acknowledged, keyed by tour id.
     *
     * @return array<string, string>
     */
    public function acknowledged(?Authenticatable $user): array
    {
        return $this->versionsIn($this->driver($user)->load(self::SURFACE, $user));
    }

    /**
     * Whether this user has already acknowledged this exact version of a tour.
     *
     * Inequality, not ordering: an acknowledged version that differs from the
     * tour's current one — because the author bumped `since()` — is as good as
     * none, and nothing here has to know which of two version strings is newer.
     */
    public function hasSeen(Tour $tour, ?Authenticatable $user): bool
    {
        return ($this->acknowledged($user)[$tour->getId()] ?? null) === $tour->getVersion();
    }

    /**
     * Record that this user has finished or skipped a tour.
     *
     * The same call for both, because somebody who skipped has decided about
     * this tour just as firmly as somebody who finished it, and a tour that
     * comes back after a skip is the worst version of this feature.
     */
    public function acknowledge(Tour $tour, ?Authenticatable $user): void
    {
        $this->write($user, fn (array $seen): array => [
            ...$seen,
            $tour->getId() => $tour->getVersion(),
        ]);
    }

    /**
     * Forget one tour for this user, so it runs again on the next matching page.
     *
     * What a "replay" entry calls. Scoped to one id rather than clearing the
     * surface: replaying the tour you are looking at should not un-acknowledge
     * every other one.
     */
    public function forget(Tour $tour, ?Authenticatable $user): void
    {
        $this->write($user, function (array $seen) use ($tour): array {
            unset($seen[$tour->getId()]);

            return $seen;
        });
    }

    /**
     * Read, change and store the acknowledgement map in one pass.
     *
     * Through `load()` rather than over a cached copy because the bag holds more
     * than this surface writes, and a save built from a stale read would put
     * back whatever another surface stored in between.
     *
     * @param  callable(array<string, string>): array<string, string>  $change
     */
    private function write(?Authenticatable $user, callable $change): void
    {
        $driver = $this->driver($user);

        $bag = $driver->load(self::SURFACE, $user);

        $driver->save(self::SURFACE, $user, [
            ...$bag,
            self::SURFACE => $change($this->versionsIn($bag)),
        ]);
    }

    /**
     * The acknowledgement map inside a loaded bag, with anything unusable dropped.
     *
     * The bag is shared and open, so this key can come back as something this
     * class did not write — an application storing its own state under the same
     * surface, or a hand-edited row. Treating that as "nothing acknowledged"
     * shows a tour again, which is recoverable; trusting it would mean indexing
     * a string a line later.
     *
     * @param  array<string, mixed>  $bag
     * @return array<string, string>
     */
    private function versionsIn(array $bag): array
    {
        $seen = $bag[self::SURFACE] ?? [];

        if (! is_array($seen)) {
            return [];
        }

        $versions = [];

        foreach ($seen as $id => $version) {
            if (is_string($id) && is_scalar($version)) {
                $versions[$id] = (string) $version;
            }
        }

        return $versions;
    }

    /**
     * The store this ledger reads and writes.
     *
     * The constructor's driver is handed to {@see PreferenceManager::resolve()}
     * as its override rather than short-circuited here, so the precedence rules
     * — per-surface override, then a global test swap, then config — stay in the
     * one class that owns them.
     */
    private function driver(?Authenticatable $user): PreferenceDriver
    {
        return PreferenceManager::resolve(
            $this->driver,
            authenticated: $user !== null,
            configPrefix: self::CONFIG_PREFIX,
        );
    }
}
