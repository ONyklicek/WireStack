<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAudit\Support;

use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Audit\AuditEntry;
use NyonCode\WireModuleAudit\Exceptions\AuditModuleException;
use Throwable;

/**
 * The log itself, asked about itself.
 *
 * Two facts every screen here needs before it can draw anything: which model
 * holds the trail, and whether the table behind it exists at all. The second is
 * not a formality — `wire-core` ships the `audit_logs` migration
 * publish-on-demand, so an application can install this module, reach the
 * screen and have no table. That used to be a `QueryException` raised while the
 * *filters* were being built, which reads as a broken package rather than as a
 * migration nobody ran.
 *
 * The facets (which record types, which actors) are read from the log rather
 * than from a list somebody maintains: a filter over types nobody audited is a
 * filter that only ever returns nothing.
 *
 * **Nothing here is memoised, deliberately.** A static memo outlives the request
 * under Octane, and both answers are things that change between requests — a
 * migration runs, a new model gets audited. One `hasTable()` and one `DISTINCT`
 * per render is a price this screen can pay; a filter that lists yesterday's
 * models for as long as the worker lives is not.
 */
final class AuditLog
{
    /**
     * The configured model, or null when an application deliberately points at
     * nothing.
     *
     * @return class-string<AuditEntry>|null
     *
     * @throws AuditModuleException When the setting names something that is not
     *                              an audit entry — see the exception for why that is not tolerated.
     */
    public static function model(): ?string
    {
        $model = config('wire-module-audit.model');

        if (! is_string($model) || $model === '') {
            return null;
        }

        if (! is_a($model, AuditEntry::class, true)) {
            throw AuditModuleException::modelIsNotAnAuditEntry($model);
        }

        return $model;
    }

    /** Whether there is a model *and* a table under it to read. */
    public static function available(): bool
    {
        $model = self::model();

        if ($model === null) {
            return false;
        }

        try {
            $instance = new $model;

            return $instance->getConnection()
                ->getSchemaBuilder()
                ->hasTable($instance->getTable());
        } catch (Throwable) {
            // No connection, no database, a table this user may not describe:
            // all of them mean "there is nothing to read here", and none is
            // worth a stack trace on a screen that was only drawing a filter.
            return false;
        }
    }

    /**
     * A query over the log, or null when there is nothing to query.
     *
     * @return Builder<AuditEntry>|null
     */
    public static function query(): ?Builder
    {
        $model = self::model();

        return $model !== null && self::available() ? $model::query() : null;
    }

    /**
     * The record types the log actually holds, as `stored value => label`.
     *
     * The key stays exactly what is stored — a class name, or a morph alias
     * where the application registered one — because that is what the filter
     * compares against.
     *
     * @return array<string, string>
     */
    public static function recordTypes(): array
    {
        $query = self::query();

        if ($query === null) {
            return [];
        }

        /** @var array<string, string> $types */
        $types = $query
            ->select('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type', 'auditable_type')
            ->map(static fn (string $type): string => AuditedRecords::typeLabel($type))
            ->all();

        return $types;
    }

    /**
     * The actors the log actually holds, newest activity first.
     *
     * Capped, because this feeds a `<select>`: an installation with ten thousand
     * distinct actors has a filter nobody can use, and the cap is the honest
     * version of that rather than a browser that stops responding.
     *
     * @return array<int, int|string>
     */
    public static function actorIds(int $limit = 100): array
    {
        $query = self::query();

        if ($query === null) {
            return [];
        }

        /** @var array<int, int|string> $ids */
        $ids = $query
            ->select('user_id')
            ->whereNotNull('user_id')
            ->distinct()
            ->orderBy('user_id')
            ->limit($limit)
            ->pluck('user_id')
            ->all();

        return $ids;
    }
}
