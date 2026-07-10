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
    public function load(string $tableKey, ?Authenticatable $user): array
    {
        $record = TablePreference::query()
            ->where('table_key', $tableKey)
            ->where('user_id', $this->userId($user))
            ->first();

        $preferences = $record?->preferences;

        return is_array($preferences) ? $preferences : [];
    }

    public function save(string $tableKey, ?Authenticatable $user, array $preferences): void
    {
        TablePreference::query()->updateOrCreate(
            ['table_key' => $tableKey, 'user_id' => $this->userId($user)],
            ['preferences' => $preferences],
        );
    }

    public function forget(string $tableKey, ?Authenticatable $user): void
    {
        TablePreference::query()
            ->where('table_key', $tableKey)
            ->where('user_id', $this->userId($user))
            ->delete();
    }

    protected function userId(?Authenticatable $user): int|string|null
    {
        return $user?->getAuthIdentifier();
    }
}
