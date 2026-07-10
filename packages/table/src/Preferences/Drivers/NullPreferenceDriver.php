<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Preferences\Drivers;

use Illuminate\Contracts\Auth\Authenticatable;
use NyonCode\WireTable\Preferences\Contracts\TablePreferenceDriver;

/**
 * No-op preference driver: nothing is remembered.
 *
 * The safe default — a table opts into `rememberColumns()` but no store is
 * configured, so column toggles live only for the component's lifetime. Load
 * always returns an empty bag (the table keeps its configured defaults).
 */
class NullPreferenceDriver implements TablePreferenceDriver
{
    public function load(string $tableKey, ?Authenticatable $user): array
    {
        return [];
    }

    public function save(string $tableKey, ?Authenticatable $user, array $preferences): void
    {
        // Intentionally does nothing.
    }

    public function forget(string $tableKey, ?Authenticatable $user): void
    {
        // Intentionally does nothing.
    }
}
