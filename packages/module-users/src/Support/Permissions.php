<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Support;

use NyonCode\WireCore\Foundation\Routing\RoutePage;

/**
 * Which ability each user and role screen requires.
 *
 * One reader for `wire-module-users.permissions`, so the route middleware, the
 * buttons that lead to a page and the installer's report cannot disagree about
 * what guards what. {@see RoutePage} turns the answer into `can:` middleware and
 * `BelongsToResource::pagePermission()` reads the same declaration back out to
 * hide a link that would land on a 403.
 *
 * **Authorization does not happen here.** This resolves a *string*; Gate answers
 * it. That is what lets a wildcard (`users.*`), a policy, or the super-admin
 * bypass in `nyoncode/laravel-permission-extended` all work without this class
 * knowing they exist.
 *
 * The defaults are real abilities rather than null, unlike the rest of this
 * stack — see the config file for why that one screen is different.
 */
final class Permissions
{
    /**
     * The ability a page of a resource needs, or null when it needs none.
     *
     * @param  'users'|'roles'  $resource
     * @param  'viewAny'|'view'|'create'|'update'  $page
     */
    public static function for(string $resource, string $page): ?string
    {
        $ability = config("wire-module-users.permissions.{$resource}.{$page}");

        // An empty string is the shape a `.env` produces for "unset", and it is
        // not an ability — `can:` on it would deny everything with no way to
        // grant it. Treated as null, which is the thing the person meant.
        return is_string($ability) && $ability !== '' ? $ability : null;
    }

    /**
     * A page declaration carrying its ability, or the bare component without one.
     *
     * Bare rather than `RoutePage::make($c)->permission(null)` so an unguarded
     * page stays exactly the declaration it was before this existed — the router
     * and the button-hiding both special-case a plain class string, and there is
     * no reason to make them handle a second shape that means the same thing.
     *
     * @param  class-string  $component
     * @param  'users'|'roles'  $resource
     * @param  'viewAny'|'view'|'create'|'update'  $page
     * @return class-string|RoutePage
     */
    public static function page(string $component, string $resource, string $page): string|RoutePage
    {
        $ability = self::for($resource, $page);

        return $ability === null
            ? $component
            : RoutePage::make($component)->permission($ability);
    }
}
