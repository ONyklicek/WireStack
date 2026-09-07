<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Support;

use NyonCode\WireModuleMedia\Models\Media;

/**
 * Which files a piece of written content points at.
 *
 * The rich text editor writes `data-media-id` on every picture inserted from the
 * library, so reading a body back is a regex rather than a guess. What it cannot
 * see is a URL somebody pasted in by hand, and nothing here pretends otherwise —
 * see {@see MediaUsage} for the sentence every screen says about that.
 *
 * Separate from the trait that calls it because a trait is the wrong place for
 * logic worth testing on its own, and this is the half with the edge cases.
 */
final class ContentMedia
{
    /**
     * The ids the editor wrote into this content.
     *
     * @return array<int, int>
     */
    public static function idsIn(?string $html): array
    {
        if ($html === null || $html === '') {
            return [];
        }

        preg_match_all('/data-media-id=["\'](\d+)["\']/', $html, $matches);

        // Unique, because one article using one picture three times is one use
        // of it: the question a warning answers is which records break, not how
        // many tags do.
        return array_values(array_unique(array_map(intval(...), $matches[1])));
    }

    /**
     * The ids a piece of content points at by URL, matched against the library.
     *
     * What the backfill command uses on articles written before the id was kept.
     * It is a best effort by design — a URL that no longer matches any stored
     * path finds nothing, and that is the honest answer rather than a wrong one.
     *
     * @param  array<string, int>  $byPath  stored path => media id, and basename => media id
     * @return array<int, int>
     */
    public static function idsByUrl(?string $html, array $byPath): array
    {
        if ($html === null || $html === '') {
            return [];
        }

        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches);

        $found = [];

        foreach ($matches[1] as $src) {
            $src = urldecode(strtok($src, '?') ?: $src);

            foreach ([$src, basename($src)] as $candidate) {
                if (isset($byPath[$candidate])) {
                    $found[] = $byPath[$candidate];

                    continue 2;
                }
            }

            // A URL carrying the stored path somewhere inside it — `/storage/`
            // in front of it, a bucket host, a CDN prefix. Checked last, because
            // it is the expensive one and the two lookups above catch most.
            foreach ($byPath as $path => $id) {
                if (str_contains($src, $path)) {
                    $found[] = $id;

                    break;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * A lookup of everything in the library, keyed by both its path and its
     * basename.
     *
     * Built once and held in memory on purpose: the alternative is a query per
     * `<img>` in every row of every table being backfilled, and a one-off
     * command may spend the memory to avoid that.
     *
     * @return array<string, int>
     */
    public static function lookup(): array
    {
        $map = [];

        Media::query()->select(['id', 'path'])->chunkById(500, function ($files) use (&$map): void {
            foreach ($files as $file) {
                $path = (string) $file->path;

                $map[$path] = (int) $file->getKey();
                // Second key, and deliberately the weaker one: two files with
                // the same basename in different folders resolve to whichever
                // was loaded last. A backfill that occasionally attributes a use
                // to the wrong copy of an identical name is still better than
                // one that finds nothing, and the exact path is tried first.
                $map[basename($path)] ??= (int) $file->getKey();
            }
        });

        return $map;
    }
}
