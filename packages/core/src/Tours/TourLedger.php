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
 * Beside it, under `reached`, the step somebody got to in a tour they have not
 * finished, stamped with the version it was reached in:
 *
 *     ['reached' => ['getting-started' => ['since' => '2.2', 'step' => 3]]]
 *
 * so that leaving a walkthrough halfway — clicking into the page it is showing
 * off, which is what a good tour invites — does not start it over next time.
 * Finishing, skipping and forgetting all clear it.
 *
 * And under `postponed`, a tour somebody answered "later" to, stamped with the
 * session it was put off in and how many times it has been:
 *
 *     ['postponed' => ['getting-started' => ['since' => '2.2', 'session' => 'w1Xz…', 'count' => 2]]]
 *
 * The session is what makes "later" mean "not in this sitting" on a driver that
 * remembers for ever, and the count is what stops it meaning "not ever, one
 * sitting at a time" — {@see Tour::postpone()} owns the number it is compared
 * against.
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

    /** The key the unfinished tours' progress lives under, inside the same bag. */
    public const REACHED = 'reached';

    /** The key a put-off tour's session and count live under, inside the same bag. */
    public const POSTPONED = 'postponed';

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
        ], forgetProgressOf: $tour);
    }

    /**
     * The step this user reached in this version of a tour they have not
     * finished, or null.
     *
     * Null for a step reached in an older version: the author changed the tour,
     * and a step number means nothing across that — the step that was fourth may
     * be gone, or be about something else now.
     */
    public function reached(Tour $tour, ?Authenticatable $user): ?int
    {
        $reached = $this->reachedIn($this->driver($user)->load(self::SURFACE, $user))[$tour->getId()] ?? null;

        return $reached !== null && $reached['since'] === $tour->getVersion() ? $reached['step'] : null;
    }

    /** Record the step this user has got to in a tour they are still walking. */
    public function reach(Tour $tour, ?Authenticatable $user, int $step): void
    {
        $driver = $this->driver($user);

        $bag = $driver->load(self::SURFACE, $user);

        $driver->save(self::SURFACE, $user, [
            ...$bag,
            self::REACHED => [
                ...$this->reachedIn($bag),
                $tour->getId() => ['since' => $tour->getVersion(), 'step' => $step],
            ],
        ]);
    }

    /**
     * Whether this user has put this tour off for the session they are in.
     *
     * Stamped with the session rather than with a clock, which is what makes
     * "not now" mean the same thing on every driver. On `session` the entry
     * would have gone with the session anyway; on `database` it outlives it,
     * and without this comparison a postponement there would be indistinguishable
     * from a skip — the one outcome "Later" exists in order not to be.
     *
     * A session that cannot be identified — no session bound at all, which is a
     * console render rather than a page — answers false. The recoverable
     * direction: a tour that greets somebody once more, rather than one that
     * silently never greets anybody again.
     */
    public function isPostponed(Tour $tour, ?Authenticatable $user): bool
    {
        $session = $this->session();

        if ($session === null) {
            return false;
        }

        $entry = $this->postponedIn($this->driver($user)->load(self::SURFACE, $user))[$tour->getId()] ?? null;

        return $entry !== null && $entry['since'] === $tour->getVersion() && $entry['session'] === $session;
    }

    /**
     * How many times this user has put off this version of this tour.
     *
     * Counted across sessions, unlike {@see isPostponed()}: the whole point of
     * the count is that saying "later" in three different sessions is an answer.
     * A count recorded against an older `since()` is not this tour's, for the
     * reason {@see reached()} gives about step numbers.
     */
    public function postponements(Tour $tour, ?Authenticatable $user): int
    {
        $entry = $this->postponedIn($this->driver($user)->load(self::SURFACE, $user))[$tour->getId()] ?? null;

        return $entry !== null && $entry['since'] === $tour->getVersion() ? $entry['count'] : 0;
    }

    /** Record that this user has put this tour off once more, for this session. */
    public function postpone(Tour $tour, ?Authenticatable $user): void
    {
        $driver = $this->driver($user);

        $bag = $driver->load(self::SURFACE, $user);

        $driver->save(self::SURFACE, $user, [
            ...$bag,
            self::POSTPONED => [
                ...$this->postponedIn($bag),
                $tour->getId() => [
                    'since' => $tour->getVersion(),
                    'session' => $this->session() ?? '',
                    'count' => $this->postponements($tour, $user) + 1,
                ],
            ],
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
        }, forgetProgressOf: $tour);
    }

    /**
     * Read, change and store the acknowledgement map in one pass.
     *
     * Through `load()` rather than over a cached copy because the bag holds more
     * than this surface writes, and a save built from a stale read would put
     * back whatever another surface stored in between.
     *
     * Both callers are done with the tour's progress too — somebody who
     * finished has nowhere left to resume, and a replay starts from the top — so
     * it is dropped in the same save rather than a second one. The postponement
     * goes with it, and for a sharper reason: a replayed tour that kept its
     * count would be one "later" away from acknowledging itself, and a
     * *finished* tour that kept it would carry the count into the next version,
     * where the author's third greeting would be somebody else's first.
     *
     * @param  callable(array<string, string>): array<string, string>  $change
     */
    private function write(?Authenticatable $user, callable $change, Tour $forgetProgressOf): void
    {
        $driver = $this->driver($user);

        $bag = $driver->load(self::SURFACE, $user);

        $reached = $this->reachedIn($bag);
        unset($reached[$forgetProgressOf->getId()]);

        $postponed = $this->postponedIn($bag);
        unset($postponed[$forgetProgressOf->getId()]);

        $driver->save(self::SURFACE, $user, [
            ...$bag,
            self::SURFACE => $change($this->versionsIn($bag)),
            self::REACHED => $reached,
            self::POSTPONED => $postponed,
        ]);
    }

    /**
     * The postponement map inside a loaded bag, with anything unusable dropped —
     * for the reason {@see versionsIn()} gives.
     *
     * @param  array<string, mixed>  $bag
     * @return array<string, array{since: string, session: string, count: int}>
     */
    private function postponedIn(array $bag): array
    {
        $postponed = $bag[self::POSTPONED] ?? [];

        if (! is_array($postponed)) {
            return [];
        }

        $valid = [];

        foreach ($postponed as $id => $entry) {
            if (is_string($id) && is_array($entry) && is_scalar($entry['since'] ?? null) && is_scalar($entry['session'] ?? null) && is_int($entry['count'] ?? null) && $entry['count'] >= 0) {
                $valid[$id] = [
                    'since' => (string) $entry['since'],
                    'session' => (string) $entry['session'],
                    'count' => $entry['count'],
                ];
            }
        }

        return $valid;
    }

    /**
     * The id of the session this request belongs to, or null when there is none.
     *
     * Asked of the container rather than of the `session()` helper so that a
     * context without a session — an artisan command rendering a view — answers
     * null instead of throwing. Unlike the route, a session id is the same on a
     * page render and on the Livewire updates after it, which is why this is
     * read where it is needed rather than passed along.
     */
    private function session(): ?string
    {
        if (! app()->bound('session.store')) {
            return null;
        }

        $id = app('session.store')->getId();

        return $id === '' ? null : $id;
    }

    /**
     * The progress map inside a loaded bag, with anything unusable dropped —
     * for the reason {@see versionsIn()} gives.
     *
     * @param  array<string, mixed>  $bag
     * @return array<string, array{since: string, step: int}>
     */
    private function reachedIn(array $bag): array
    {
        $reached = $bag[self::REACHED] ?? [];

        if (! is_array($reached)) {
            return [];
        }

        $valid = [];

        foreach ($reached as $id => $entry) {
            if (is_string($id) && is_array($entry) && is_scalar($entry['since'] ?? null) && is_int($entry['step'] ?? null) && $entry['step'] >= 0) {
                $valid[$id] = ['since' => (string) $entry['since'], 'step' => $entry['step']];
            }
        }

        return $valid;
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
