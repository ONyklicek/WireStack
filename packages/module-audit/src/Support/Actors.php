<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAudit\Support;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Audit\AuditEntry;
use Throwable;

/**
 * Who did it.
 *
 * The column used to print `user_id`, which is a number a reader has to go and
 * look up somewhere else — in the one table whose whole purpose is telling you
 * who was responsible. Core stores the key and defines the relation
 * ({@see AuditEntry::user()}); this turns the pair into a name.
 *
 * Three answers, and each is a different fact:
 *
 *  - **no actor** — `wire-core::audit.system`. Seeders, queued jobs and console
 *    commands audit changes with no auth context, and `AuditLogger` records
 *    those on purpose rather than dropping the entry.
 *  - **an actor who is gone** — `wire-core::audit.unknown_user`, with the key
 *    kept beside it. A deleted user is exactly the case where the id is the
 *    only thing left, so it stays visible.
 *  - **an actor** — the first of the configured attributes they have.
 *
 * The wording is core's translations rather than this module's, because core's
 * trail slide-over already says those two things and one screen contradicting
 * the other about who "System" is would be the sort of small lie an audit log
 * cannot afford.
 */
final class Actors
{
    /**
     * The application's user model, as core's audit configuration names it —
     * null when it is not a class that exists.
     *
     * Testbench and a fresh package installation both have
     * `App\Models\User` configured and absent, and the relation cannot be
     * touched without it: a `belongsTo` over a missing class fatals rather than
     * returning nothing.
     *
     * @return class-string<Model>|null
     */
    public static function userModel(): ?string
    {
        $model = config('wire-core.audit.user_model', 'App\\Models\\User');

        return is_string($model) && is_a($model, Model::class, true) ? $model : null;
    }

    /** Whether actors can be named at all — which decides eager loading and the actor filter. */
    public static function available(): bool
    {
        return self::userModel() !== null;
    }

    /** The name to show for one entry's actor. */
    public static function label(Model $entry): string
    {
        /** @var int|string|null $id */
        $id = $entry->getAttribute('user_id');

        if ($id === null || $id === '') {
            return (string) __('wire-core::audit.system');
        }

        $user = self::available() ? $entry->getAttribute('user') : null;

        if (! $user instanceof Model) {
            return (string) __('wire-core::audit.unknown_user').' #'.$id;
        }

        return self::nameOf($user);
    }

    /**
     * One user's name: the first configured attribute they actually have.
     *
     * Configured rather than assumed, because `name` is a convention and not a
     * contract — an application whose users have `first_name` and `last_name`
     * would otherwise see a column of keys and no way to change it.
     */
    public static function nameOf(Model $user): string
    {
        /** @var array<int, string> $attributes */
        $attributes = (array) config('wire-module-audit.actor.attributes', ['name', 'email']);

        foreach ($attributes as $attribute) {
            $value = $user->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '#'.$user->getKey();
    }

    /**
     * Actor filter options — the people who appear in the log, by their key.
     *
     * Built from the log and resolved in one query, not one per row. An id with
     * no user behind it keeps its place: filtering by "the account that was
     * deleted" is a question this screen should still be able to answer.
     *
     * @return array<array-key, string>
     */
    public static function options(): array
    {
        $ids = AuditLog::actorIds();
        $model = self::userModel();

        if ($ids === [] || $model === null) {
            return [];
        }

        try {
            $users = $model::query()->findMany($ids)->keyBy(
                static fn (Model $user): string => (string) $user->getKey(),
            );
        } catch (Throwable) {
            // The user model may be configured and its table not migrated yet —
            // the same state the log itself can be in. A filter listing keys is
            // still a working filter.
            $users = collect();
        }

        $options = [];

        foreach ($ids as $id) {
            $user = $users->get((string) $id);

            $options[$id] = $user instanceof Model
                ? self::nameOf($user)
                : (string) __('wire-core::audit.unknown_user').' #'.$id;
        }

        return $options;
    }
}
