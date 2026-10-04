<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Support;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use NyonCode\WireModuleTenants\Routing\TenantRoutes;

/**
 * Who may register a company, and what a company may be called.
 *
 * `wire-module-tenants.registration`: 'anyone' signed in, 'ability' through
 * the Gate, or false for nobody through the screen.
 */
final class Registration
{
    public static function allows(mixed $user): bool
    {
        if ($user === null) {
            return false;
        }

        return match (self::mode()) {
            'anyone' => true,
            'ability' => Gate::forUser($user)->allows((string) config('wire-module-tenants.registration_ability', 'tenants.create')),
            default => false,
        };
    }

    /**
     * The setting as one of its three answers: `'anyone'`, `'ability'` or false.
     *
     * Read the way the environment writes it. `WIRE_TENANTS_REGISTRATION=true`
     * arrives as the boolean `true`, which is none of the three words and used to
     * mean nobody; on/off words and `1`/`0` read as "anyone" and "nobody".
     */
    public static function mode(): string|false
    {
        $setting = config('wire-module-tenants.registration', 'anyone');

        if (is_string($setting) && in_array(strtolower(trim($setting)), ['anyone', 'ability'], true)) {
            return strtolower(trim($setting));
        }

        $flag = is_bool($setting) ? $setting : filter_var($setting, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $flag === true ? 'anyone' : false;
    }

    /**
     * The registration screen's address for this person, or null when it is
     * not theirs to open — or not routed, because the application has not
     * placed the `tenants` route group.
     */
    public static function urlFor(mixed $user): ?string
    {
        return self::allows($user) && Route::has(TenantRoutes::REGISTER) ? route(TenantRoutes::REGISTER) : null;
    }

    /** A slug from a name: what the registration form proposes. */
    public static function slugFor(string $name): string
    {
        return Str::slug($name);
    }

    /** @return array<int, string> */
    public static function reserved(): array
    {
        return array_values(array_map('strval', (array) config('wire-module-tenants.reserved_slugs', [])));
    }

    /** Where one company's pages are — the tenant zone's own address for it. */
    public static function homeOf(string $slug): string
    {
        return url(str_replace('{tenant}', $slug, (string) config('wire-module-tenants.home', 'app/{tenant}')));
    }
}
