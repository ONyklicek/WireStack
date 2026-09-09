<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Preferences\Drivers;

use Illuminate\Contracts\Auth\Authenticatable;
use NyonCode\WireTable\Preferences\Contracts\TablePreferenceDriver;
use NyonCode\WireTable\Preferences\Models\TablePreference;

/**
 * Database-backed preferences: one `table_preferences` row per (user, table).
 *
 * The reference persistent driver — publish and run the
 * `create_table_preferences_table` migration, set
 * `config('wire-table.preferences.default')` to `database`, and every user's
 * column layout is remembered across sessions and devices. Scales to any number
 * of tables (`table_key`) and users (`user_id`) via the composite unique index.
 *
 * Guests (no identifier) collapse onto a shared `null` user_id row, so for
 * per-guest memory keep the guest driver on `session` (the default).
 */
class DatabasePreferenceDriver implements TablePreferenceDriver
{
    /** The stored spelling of "no name": see {@see viewName()}. */
    private const CURRENT_LAYOUT = '';

    public function load(string $tableKey, ?Authenticatable $user, ?string $view = null): array
    {
        $record = TablePreference::query()
            ->where('table_key', $tableKey)
            ->where('user_id', $this->userId($user))
            ->where('view', $this->viewName($view))
            ->first();

        $preferences = $record?->preferences;

        return is_array($preferences) ? $preferences : [];
    }

    public function save(string $tableKey, ?Authenticatable $user, array $preferences, ?string $view = null): void
    {
        TablePreference::query()->updateOrCreate(
            ['table_key' => $tableKey, 'user_id' => $this->userId($user), 'view' => $this->viewName($view)],
            ['preferences' => $preferences],
        );
    }

    public function forget(string $tableKey, ?Authenticatable $user, ?string $view = null): void
    {
        TablePreference::query()
            ->where('table_key', $tableKey)
            ->where('user_id', $this->userId($user))
            ->where('view', $this->viewName($view))
            ->delete();
    }

    public function views(string $tableKey, ?Authenticatable $user): array
    {
        return TablePreference::query()
            ->where('table_key', $tableKey)
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
