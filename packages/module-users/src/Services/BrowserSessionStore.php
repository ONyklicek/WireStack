<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use NyonCode\WireModuleUsers\ValueObjects\BrowserSession;

/**
 * The signed-in person's sessions, read from and pruned in Laravel's own table.
 *
 * **Only the `database` driver can answer.** A file, cookie, Redis or cache
 * session is keyed by its id alone, so nothing can ask it "which of you belong
 * to this user" — the database handler is the one that writes a `user_id`
 * beside each row. Anywhere else the list is empty and {@see listable()} says
 * why; signing the other devices out still works, because that half is the
 * password rehash `AuthenticateSession` checks, not these rows.
 *
 * The table and the connection are Laravel's `session.table` and
 * `session.connection`, read rather than restated, so an application that
 * moved its sessions is followed there.
 */
final class BrowserSessionStore
{
    /** Whether this installation keeps sessions where they can be listed. */
    public function listable(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * Every session of this person, the most recently used first.
     *
     * @return Collection<int, BrowserSession>
     */
    public function forUser(Authenticatable $user, string $currentId): Collection
    {
        if (! $this->listable()) {
            return collect();
        }

        return $this->query($user)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $row): BrowserSession => BrowserSession::fromRow($row, $currentId))
            ->values();
    }

    /**
     * Delete every session of this person but the one making the request.
     *
     * @return int How many were removed.
     */
    public function forgetOthers(Authenticatable $user, string $currentId): int
    {
        if (! $this->listable()) {
            return 0;
        }

        return $this->query($user)->where('id', '!=', $currentId)->delete();
    }

    private function query(Authenticatable $user): Builder
    {
        return DB::connection(config('session.connection'))
            ->table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier());
    }
}
