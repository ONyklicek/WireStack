<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Which team the person is working in, when this application has teams at all.
 *
 * **The permission layer owns teams**, the same way Fortify owns two-factor.
 * Roles scoped to a team, the extra column on the pivot tables, the cache key
 * that has to include the team — all of that is a feature of
 * `nyoncode/laravel-permission-extended` and the `spatie/laravel-permission` it
 * is built on, switched on with `permission.teams`. A second teams
 * implementation next to it would be two answers to "may this person do this
 * here", which is the one question that must have one.
 *
 * The package this asks for is the extended one, never bare Spatie — the same
 * rule {@see Roles} follows and for the same reason. `PermissionRegistrar`
 * below is the *mechanism* it inherits, not an alternative to it: the extended
 * package adds behaviour on the user model and leaves the registrar where it
 * is, so telling the registrar which team this request is in is still how the
 * scoping is done.
 *
 * What Spatie deliberately has no opinion about is the part a panel needs:
 * *which* team is current on this request. That is a session, a switcher and a
 * middleware, and it is all this class is:
 *
 *   - {@see enabled()}    — are there teams to switch between
 *   - {@see optionsFor()} — the ones this person belongs to
 *   - {@see currentId()}  — the one they are looking at
 *   - {@see switchTo()}   — change it, if they are a member
 *
 * Nothing here ships a teams table. An application that has teams already has
 * both the model and the relation, and one that does not is not using this.
 */
final class Teams
{
    /**
     * The registrar the scoping actually goes through.
     *
     * Spatie's, and named here rather than wrapped: the extended package
     * inherits it untouched, so this is the one object that knows which team a
     * permission read is scoped to. Which package an application installs is
     * {@see available()}'s question; this is the mechanism underneath it.
     */
    public const REGISTRAR = 'Spatie\\Permission\\PermissionRegistrar';

    /** Whether team switching should be part of this installation. */
    public static function enabled(): bool
    {
        $setting = config('wire-module-users.teams.enabled', 'auto');

        if (is_bool($setting)) {
            return $setting;
        }

        return $setting === 'auto' && self::available();
    }

    /**
     * Whether the permission layer has teams on, and there is a model to reach.
     *
     * Three conditions, and each is a different question:
     *
     *  - **the permission layer is this stack's**, which is
     *    `nyoncode/laravel-permission-extended` and not bare Spatie ({@see Roles});
     *  - **`permission.teams` is on**, which is the switch that makes the *whole*
     *    feature real — it is what puts the team column on the pivot tables and
     *    into the permission cache key. Following it rather than a switch of our
     *    own is what keeps the panel and the authorization it draws from
     *    agreeing about whether teams exist;
     *  - **there is a team model to list**, which is the application's.
     */
    public static function available(): bool
    {
        $model = config('wire-module-users.teams.model');

        return Roles::hasExtendedPermissions()
            && (bool) config('permission.teams', false)
            && class_exists(self::REGISTRAR)
            && is_string($model)
            && class_exists($model);
    }

    /** The relation on the user model that reaches their teams. */
    public static function relation(): string
    {
        $configured = config('wire-module-users.teams.relation');

        return is_string($configured) && $configured !== '' ? $configured : 'teams';
    }

    /** The attribute a team is named by in a switcher. */
    public static function labelAttribute(): string
    {
        $configured = config('wire-module-users.teams.label_attribute');

        return is_string($configured) && $configured !== '' ? $configured : 'name';
    }

    /** The session key the current team is remembered under. */
    public static function sessionKey(): string
    {
        $configured = config('wire-module-users.teams.session_key');

        return is_string($configured) && $configured !== '' ? $configured : 'wire.team';
    }

    /**
     * The teams a person belongs to, keyed by primary key.
     *
     * Their own teams, never every team: a switcher that listed the whole table
     * would be a way to look at somebody else's data, and the switch itself
     * checks membership again because a select is not a permission.
     *
     * @return array<int|string, string>
     */
    public static function optionsFor(mixed $user = null): array
    {
        $teams = self::teamsOf($user);

        $label = self::labelAttribute();

        return $teams
            ->mapWithKeys(static fn (Model $team): array => [
                $team->getKey() => (string) ($team->getAttribute($label) ?? $team->getKey()),
            ])
            ->all();
    }

    /**
     * The team this request is in.
     *
     * The session, when it names one this person is still a member of;
     * otherwise their first, which is what makes a first visit — and a visit
     * after being removed from a team — land somewhere real rather than nowhere.
     */
    public static function currentId(mixed $user = null): int|string|null
    {
        if (! self::enabled()) {
            return null;
        }

        $options = self::optionsFor($user);

        if ($options === []) {
            return null;
        }

        $stored = session(self::sessionKey());

        if ($stored !== null && array_key_exists($stored, $options)) {
            return is_int($stored) || is_string($stored) ? $stored : null;
        }

        return array_key_first($options);
    }

    /**
     * Switch, if they are a member. Answers whether it happened.
     *
     * Membership is re-checked here rather than trusted from the control that
     * asked: a select is markup, and markup is whatever reached the browser.
     */
    public static function switchTo(int|string $team, mixed $user = null): bool
    {
        if (! self::enabled() || ! array_key_exists($team, self::optionsFor($user))) {
            return false;
        }

        session()->put(self::sessionKey(), $team);

        self::apply($team);

        return true;
    }

    /**
     * Tell the permission package which team this request is in.
     *
     * Every role and permission read after this is scoped by it, which is why it
     * has to happen before anything reads one — see the middleware.
     */
    public static function apply(int|string|null $team): void
    {
        // The permission package is a dependency of this suite, so neither the
        // missing-class guard nor the catch can be reached from a test here.
        // Both are for the installation this module is built to survive: one
        // that never installed Spatie, or installed a version too old to know
        // about teams.
        // @codeCoverageIgnoreStart
        if (! class_exists(self::REGISTRAR)) {
            return;
        }
        // @codeCoverageIgnoreEnd

        try {
            app(self::REGISTRAR)->setPermissionsTeamId($team);
            // @codeCoverageIgnoreStart
        } catch (Throwable) {
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * The teams a person belongs to.
     *
     * @return Collection<int, Model>
     */
    private static function teamsOf(mixed $user = null): Collection
    {
        /** @var Collection<int, Model> $empty */
        $empty = new Collection;

        $user ??= Auth::user();

        if (! $user instanceof Model || ! self::available()) {
            return $empty;
        }

        $relation = self::relation();

        if (! method_exists($user, $relation)) {
            return $empty;
        }

        try {
            $related = $user->{$relation}();

            return $related instanceof Relation ? $related->get() : $empty;
        } catch (Throwable) {
            // No table yet, or a relation that is not one: both mean this
            // installation has no teams to offer, not that a page should fail.
            return $empty;
        }
    }
}
