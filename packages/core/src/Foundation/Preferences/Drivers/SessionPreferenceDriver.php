<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Preferences\Drivers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Session;
use NyonCode\WireCore\Foundation\Preferences\Contracts\PreferenceDriver;

/**
 * Session-backed preferences: no database, no migration.
 *
 * Preferences live in the user's session, keyed by the surface key and the user
 * identifier (or `guest` when unauthenticated). Ideal for guests and apps that
 * do not want a `wire_preferences` table; the layout survives reloads for as
 * long as the session lives.
 *
 * The session key prefix is `wire.`, not `wire-table.`, since a dashboard's
 * layout lives here too now. Sessions written under the old prefix are simply
 * not found — which for session storage means a user's unsaved layout resets
 * once, and nothing else.
 */
class SessionPreferenceDriver implements PreferenceDriver
{
    public function load(string $surfaceKey, ?Authenticatable $user, ?string $view = null): array
    {
        $stored = $view === null
            ? Session::get($this->key($surfaceKey, $user), [])
            : ($this->savedViews($surfaceKey, $user)[$view] ?? []);

        return is_array($stored) ? $stored : [];
    }

    public function save(string $surfaceKey, ?Authenticatable $user, array $preferences, ?string $view = null): void
    {
        if ($view === null) {
            Session::put($this->key($surfaceKey, $user), $preferences);

            return;
        }

        $views = $this->savedViews($surfaceKey, $user);
        $views[$view] = $preferences;

        Session::put($this->viewsKey($surfaceKey, $user), $views);
    }

    public function forget(string $surfaceKey, ?Authenticatable $user, ?string $view = null): void
    {
        if ($view === null) {
            Session::forget($this->key($surfaceKey, $user));

            return;
        }

        $views = $this->savedViews($surfaceKey, $user);
        unset($views[$view]);

        Session::put($this->viewsKey($surfaceKey, $user), $views);
    }

    public function views(string $surfaceKey, ?Authenticatable $user): array
    {
        return array_keys($this->savedViews($surfaceKey, $user));
    }

    /**
     * Every named view for a surface + user, as `name => bag`.
     *
     * The names are array keys, never part of the session key, and the named
     * views sit under a root of their own. Both halves of that are load-bearing,
     * because Session::put() reads dots as nesting: hanging a view off the
     * current layout's key wrote it INSIDE that layout's bag, and a view named
     * "Q1.2026" would have done the same thing one level further down.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function savedViews(string $surfaceKey, ?Authenticatable $user): array
    {
        $stored = Session::get($this->viewsKey($surfaceKey, $user), []);

        return is_array($stored) ? $stored : [];
    }

    protected function viewsKey(string $surfaceKey, ?Authenticatable $user): string
    {
        $userId = $user?->getAuthIdentifier() ?? 'guest';

        return "wire.savedViews.{$userId}.{$surfaceKey}";
    }

    /**
     * Namespaced session key, scoped to the user so two accounts sharing a
     * browser session never see each other's layout.
     */
    protected function key(string $surfaceKey, ?Authenticatable $user): string
    {
        $userId = $user?->getAuthIdentifier() ?? 'guest';

        return "wire.preferences.{$userId}.{$surfaceKey}";
    }
}
