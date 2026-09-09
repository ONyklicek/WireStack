<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Preferences\Drivers;

use Illuminate\Contracts\Auth\Authenticatable;
use NyonCode\WireCore\Foundation\Preferences\Contracts\PreferenceDriver;

/**
 * No-op preference driver: nothing is remembered.
 *
 * The safe default, and the reason a surface can offer to remember something
 * without an application having to configure a store: a table opts into
 * `rememberColumns()`, a dashboard into `customisable()`, and with no driver
 * configured the choices live only for the component's lifetime. Load always
 * returns an empty bag, so the surface keeps its declared defaults.
 */
class NullPreferenceDriver implements PreferenceDriver
{
    public function load(string $surfaceKey, ?Authenticatable $user, ?string $view = null): array
    {
        return [];
    }

    public function save(string $surfaceKey, ?Authenticatable $user, array $preferences, ?string $view = null): void
    {
        // Intentionally does nothing.
    }

    public function forget(string $surfaceKey, ?Authenticatable $user, ?string $view = null): void
    {
        // Intentionally does nothing.
    }

    public function views(string $surfaceKey, ?Authenticatable $user): array
    {
        return [];
    }
}
