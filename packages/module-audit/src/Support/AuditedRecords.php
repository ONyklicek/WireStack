<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAudit\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NyonCode\WireCore\Foundation\Contracts\ResolvesRecordUrls;
use NyonCode\WireCore\Foundation\Support\MorphedModels;

/**
 * The thing an entry is *about* — named, and linked where the application
 * already has a screen for it.
 *
 * Two jobs, and the second is the one that changes what the log is worth. An
 * entry says `App\Models\Invoice` and `7`; the question a person actually has
 * is "show me that invoice". That question has a canonical owner
 * ({@see ResolvesRecordUrls}) shared with anything else that links to a record
 * by type and id, so what is left here is reading the stored type.
 *
 * **The stored type may be a morph alias.** `Relation::morphMap()` is how an
 * application keeps class names out of its database, and `auditable_type` is
 * written from `getMorphClass()` — so it holds `invoice`, not the class, for
 * every application that did that. Reading the alias back through the map is
 * what stops those installations seeing a log labelled `Invoice` in one column
 * and nothing linkable in the next.
 */
final class AuditedRecords
{
    /**
     * The model class behind a stored type.
     *
     * Delegates to the canonical owner ({@see MorphedModels}) — the audit trail
     * was the first reader of a morph-typed column, not the only one.
     *
     * @return class-string<Model>|null
     */
    public static function modelClass(?string $type): ?string
    {
        return MorphedModels::classFor($type);
    }

    /**
     * A readable name for a stored type — `Invoice` for every shape of it.
     *
     * The namespace is dropped before the type is read as words, and that is not
     * only for tidiness: a log outlives the code it describes, so it holds class
     * names nothing autoloads any more. Headlining one of those whole gives
     * `App\ Models\ Order` in the middle of a table.
     */
    public static function typeLabel(?string $type): string
    {
        if ($type === null || $type === '') {
            return '';
        }

        return Str::headline(class_basename(self::modelClass($type) ?? $type));
    }

    /**
     * The record, as a person refers to it: `Invoice #7`.
     *
     * A bulk action records the type it ran over and no id, so that entry reads
     * `Invoice` — the type alone, rather than an invented `#`.
     */
    public static function label(?string $type, int|string|null $id = null): string
    {
        $label = self::typeLabel($type);

        if ($label === '') {
            return '';
        }

        return $id === null || $id === '' ? $label : $label.' #'.$id;
    }

    /**
     * Where that record can be read, in the zone the asking page was opened in.
     *
     * Null is the ordinary answer, not a failure: the model may have no
     * resource, the resource no view page, the application no routes at all
     * (ADR 0027). Every caller renders without a link when it comes back null.
     *
     * @param  string|null  $zone  The mount point the calling page read in `mount()`.
     */
    public static function urlFor(?string $type, int|string|null $id, ?string $zone = null): ?string
    {
        $model = self::modelClass($type);

        if ($model === null || $id === null || $id === '') {
            return null;
        }

        // Delegated to the canonical owner ({@see ResolvesRecordUrls}): "where can
        // this record be read" is the same question a mention in a body of text
        // asks, and it had two answers until it had one. The entry carries a type
        // and an id rather than a record, so the model is instantiated with its
        // key set — enough for a resource lookup, and no query.
        $record = (new $model)->forceFill([(new $model)->getKeyName() => $id]);

        return app(ResolvesRecordUrls::class)->urlForRecord($record, $zone);
    }
}
