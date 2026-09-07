<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Support;

use Illuminate\Support\Facades\Gate;
use NyonCode\WireModuleSettings\Contracts\DescribesSettingsGroup;
use NyonCode\WireModuleSettings\Contracts\GuardsSettingsGroup;
use NyonCode\WireModuleSettings\Contracts\ProvidesSettingsDefaults;
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;
use Throwable;

/**
 * What the application declared, read once and answered for.
 *
 * The list in `wire-module-settings.groups` is a config array of class strings,
 * and everything that wanted to use it had to do the same four things first:
 * skip what is not a group, key it by its storage name, put it in order, and ask
 * whether this user may see it. The page did the first two and none of the rest,
 * the Blade view did the labels by reaching for `$class::label()` itself, and
 * {@see Settings} could not do any of it — which is why a declared default had
 * nowhere to be read from.
 *
 * One owner, so all three ask the same question and get the same answer. It is
 * also the only place that knows the optional contracts exist: a caller says
 * `SettingsGroups::icon($group)` and never writes an `is_a()` against
 * {@see DescribesSettingsGroup}.
 *
 * Nothing is cached across requests. The list is a config array — resolving it
 * is a loop over a handful of strings — and a cache here would be a second thing
 * to invalidate when an application changes what it declares.
 */
final class SettingsGroups
{
    /**
     * Every declared group, keyed by its storage name, in switcher order.
     *
     * Two sources, one list: `wire-module-settings.groups`, which is the
     * application's, and {@see SettingsRegistry}, which is what packages
     * contribute through. The application wins a collision and sorts first among
     * ties; `wire-module-settings.except` drops a contributed tab outright.
     *
     * @return array<string, class-string<SettingsGroup>>
     */
    public static function all(): array
    {
        $groups = self::declared();

        // Stable since PHP 8.0, and that is what makes `sort()` optional: a group
        // that declares a number moves, and the ones that declare none keep the
        // order the config listed them in rather than being shuffled around it.
        uasort($groups, static fn (string $a, string $b): int => self::sort($a) <=> self::sort($b));

        return $groups;
    }

    /**
     * The groups this user may actually open.
     *
     * What the switcher is built from, so a link never leads to a 403 the page
     * would then have to explain.
     *
     * @return array<string, class-string<SettingsGroup>>
     */
    public static function visible(): array
    {
        return array_filter(self::all(), static fn (string $class): bool => self::authorized($class));
    }

    /**
     * One declared group by its storage name, or null when nothing declares it.
     *
     * @return class-string<SettingsGroup>|null
     */
    public static function find(string $group): ?string
    {
        return self::declared()[$group] ?? null;
    }

    /**
     * Whether the current user may see and save this group.
     *
     * `Gate::allows()` and nothing else — the same check every other surface in
     * this framework makes, so the permission package an application installed
     * answers this one too.
     *
     * @param  class-string<SettingsGroup>  $class
     */
    public static function authorized(string $class): bool
    {
        $permission = self::permission($class);

        if ($permission === null) {
            return true;
        }

        try {
            return Gate::allows($permission);
        } catch (Throwable) {
            // Fail closed, the same way HasAuthorization does: a context with no
            // resolvable guard — console, a queued job — can authorize nothing,
            // and denying is the only safe answer there.
            return false;
        }
    }

    /** @param  class-string<SettingsGroup>  $class */
    public static function permission(string $class): ?string
    {
        if (! is_a($class, GuardsSettingsGroup::class, true)) {
            return null;
        }

        $permission = $class::permission();

        return $permission !== null && $permission !== '' ? $permission : null;
    }

    /**
     * The values a group has before anything is stored.
     *
     * @param  class-string<SettingsGroup>  $class
     * @return array<string, mixed>
     */
    public static function defaults(string $class): array
    {
        return is_a($class, ProvidesSettingsDefaults::class, true) ? $class::defaults() : [];
    }

    /**
     * The declared defaults for a storage group name, or none for a group this
     * application does not declare.
     *
     * The name rather than the class, because this is what {@see Settings} has
     * in hand: a settings read names a group as a string, and may well run in an
     * application where nothing declares that group at all.
     *
     * @return array<string, mixed>
     */
    public static function defaultsFor(string $group): array
    {
        $class = self::find($group);

        return $class === null ? [] : self::defaults($class);
    }

    /**
     * The declared list, unordered.
     *
     * The lookup half, split from {@see all()} because it is on a hot path and
     * the sort is not: {@see Settings::all()} resolves a group's defaults on
     * every read, cache hit included, and a `uasort` there would have been the
     * price of a feature most groups do not use.
     *
     * @return array<string, class-string<SettingsGroup>>
     */
    private static function declared(): array
    {
        // The application's own list first, so it also wins the tie when two
        // groups declare no `sort()` — this is their panel.
        $groups = self::keyed((array) config('wire-module-settings.groups', []));

        // Then what packages contributed, filling gaps only. `??=` is the whole
        // precedence rule: an application that declares `mail` itself has
        // answered the question, and a package must not answer it again over the
        // top — which is what a plain assignment here would do, silently, on the
        // machine where composer happened to order the providers that way.
        foreach (self::keyed(SettingsRegistry::instance()->all()) as $name => $class) {
            $groups[$name] ??= $class;
        }

        // The way out, and the reason a package may ship a tab at all: an
        // application that does not want one names it here and it is gone. A
        // package-shipped screen an application cannot remove is the thing that
        // makes people stop installing package-shipped screens.
        foreach ((array) config('wire-module-settings.except', []) as $name) {
            if (is_string($name)) {
                unset($groups[$name]);
            }
        }

        return $groups;
    }

    /**
     * A list of class strings as a map of storage group => class.
     *
     * A listed class that is not a settings group is skipped rather than fatal:
     * this list is read while a menu renders, and a typo in config should cost a
     * missing tab, not every screen in the panel.
     *
     * @param  array<int|string, mixed>  $classes
     * @return array<string, class-string<SettingsGroup>>
     */
    private static function keyed(array $classes): array
    {
        $groups = [];

        foreach ($classes as $class) {
            if (is_string($class) && is_subclass_of($class, SettingsGroup::class)) {
                $groups[$class::group()] = $class;
            }
        }

        return $groups;
    }

    /** @param  class-string<SettingsGroup>  $class */
    public static function icon(string $class): ?string
    {
        return is_a($class, DescribesSettingsGroup::class, true) ? $class::icon() : null;
    }

    /** @param  class-string<SettingsGroup>  $class */
    public static function description(string $class): ?string
    {
        return is_a($class, DescribesSettingsGroup::class, true) ? $class::description() : null;
    }

    /** @param  class-string<SettingsGroup>  $class */
    public static function sort(string $class): int
    {
        return is_a($class, DescribesSettingsGroup::class, true) ? $class::sort() : 0;
    }
}
