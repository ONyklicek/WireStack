<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Preferences\Drivers;

use Illuminate\Contracts\Auth\Authenticatable;
use NyonCode\WireCore\Foundation\Preferences\Contracts\PreferenceDriver;
use NyonCode\WireCore\Foundation\Preferences\Models\Preference;

/**
 * Database-backed preferences: one `wire_preferences` row per (user, surface, view).
 *
 * The reference persistent driver — publish and run the
 * `create_wire_preferences_table` migration, point the surface's configured
 * driver at `database`, and every user's layout is remembered across sessions
 * and devices. Scales to any number of surfaces (`surface_key`) and users
 * (`user_id`) via the composite unique index.
 *
 * Guests (no identifier) collapse onto a shared `null` user_id row, so for
 * per-guest memory keep the guest driver on `session` (the default).
 */
class DatabasePreferenceDriver implements PreferenceDriver
{
    /** The stored spelling of "no name": see {@see viewName()}. */
    private const CURRENT_LAYOUT = '';

    public function load(string $surfaceKey, ?Authenticatable $user, ?string $view = null): array
    {
        $record = Preference::query()
            ->where('surface_key', $surfaceKey)
            ->where('user_id', $this->userId($user))
            ->where('view', $this->viewName($view))
            ->first();

        $preferences = $record?->preferences;

        return is_array($preferences) ? $preferences : [];
    }

    public function save(string $surfaceKey, ?Authenticatable $user, array $preferences, ?string $view = null): void
    {
        Preference::query()->updateOrCreate(
            ['surface_key' => $surfaceKey, 'user_id' => $this->userId($user), 'view' => $this->viewName($view)],
            ['preferences' => $preferences],
        );
    }

    public function forget(string $surfaceKey, ?Authenticatable $user, ?string $view = null): void
    {
        Preference::query()
            ->where('surface_key', $surfaceKey)
            ->where('user_id', $this->userId($user))
            ->where('view', $this->viewName($view))
            ->delete();
    }

    public function views(string $surfaceKey, ?Authenticatable $user): array
    {
        return Preference::query()
            ->where('surface_key', $surfaceKey)
            ->where('user_id', $this->userId($user))
            ->where('view', '!=', self::CURRENT_LAYOUT)
            ->pluck('view')
            ->all();
    }

    /**
     * The unnamed current layout, spelled for the database.
     *
     * An empty string rather than NULL, because the unique index has to reject a
     * second row for the same triple — and in MySQL and SQLite two NULLs are not
     * equal, so a nullable column in a unique index rejects nothing.
     */
    protected function viewName(?string $view): string
    {
        return $view ?? self::CURRENT_LAYOUT;
    }

    protected function userId(?Authenticatable $user): int|string|null
    {
        return $user?->getAuthIdentifier();
    }
}
