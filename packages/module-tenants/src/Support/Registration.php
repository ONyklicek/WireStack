<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Support;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

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

        return match (config('wire-module-tenants.registration', 'anyone')) {
            'anyone' => true,
            'ability' => Gate::forUser($user)->allows((string) config('wire-module-tenants.registration_ability', 'tenants.create')),
            default => false,
        };
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
