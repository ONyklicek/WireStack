<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use NyonCode\WireCore\Foundation\Contracts\ResolvesRecordUrls;
use NyonCode\WireModuleMedia\Models\Media;

/**
 * Where a file is used — as far as anything can know.
 *
 * Three screens are built on this answer: the confirmation before a delete, the
 * warning before a replacement, and the plain question a media library is
 * expected to answer about a file. See ADR 0034.
 *
 * **Every number here is a floor, and every surface has to say so.** A link row
 * exists for a file attached through a field and for a picture the rich text
 * editor inserted, because the editor keeps the id. A URL somebody pasted into a
 * Blade template, a seeder or an e-mail layout by hand is invisible to this and
 * always will be. An honest floor is usable — "definitely in use, possibly
 * more" — where a number presented as complete would be trusted and would
 * eventually be wrong at the moment it mattered.
 */
final class MediaUsage
{
    /** The reserved collection a body of written content's links are filed in. */
    public const CONTENT = '__content';

    /**
     * Every known use of one file, newest first.
     *
     * @return array<int, array{type: string, label: string, collection: string, url: string|null}>
     */
    public static function for(Media $media): array
    {
        $rows = DB::table('wire_mediables')
            ->where('media_id', $media->getKey())
            ->orderByDesc('id')
            ->get(['mediable_type', 'mediable_id', 'collection']);

        return $rows->map(static function (object $row): array {
            $class = Relation::getMorphedModel((string) $row->mediable_type) ?? (string) $row->mediable_type;
            $record = self::record($class, $row->mediable_id);

            return [
                'type' => class_basename($class),
                'label' => class_basename($class).' #'.$row->mediable_id,
                'collection' => (string) $row->collection,
                'url' => $record === null ? null : self::urls()?->urlForRecord($record),
            ];
        })->all();
    }

    /**
     * How many known uses each of these files has.
     *
     * One grouped query for a whole page rather than one per tile: this is asked
     * where a grid is drawn, and a count per file is how a library of forty
     * files becomes forty queries.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    public static function countsFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('wire_mediables')
            ->whereIn('media_id', $ids)
            ->groupBy('media_id')
            ->selectRaw('media_id, count(*) as total')
            ->pluck('total', 'media_id')
            ->map(static fn ($total): int => (int) $total)
            ->all();
    }

    public static function countFor(Media $media): int
    {
        return DB::table('wire_mediables')->where('media_id', $media->getKey())->count();
    }

    /**
     * The record behind a link row, or null.
     *
     * Null is ordinary rather than exceptional: a model class renamed, a package
     * uninstalled, a row deleted without its links. The panel shows the type and
     * the key it has either way — knowing *something* still uses this file is
     * the point, and a missing link is not a reason to hide it.
     */
    private static function record(string $class, mixed $key): ?Model
    {
        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        return $class::query()->find($key);
    }

    private static function urls(): ?ResolvesRecordUrls
    {
        return app()->bound(ResolvesRecordUrls::class) ? app(ResolvesRecordUrls::class) : null;
    }
}
