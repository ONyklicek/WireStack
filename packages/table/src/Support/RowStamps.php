<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

/**
 * Which rows on a page moved since the last poll.
 *
 * A stamp per row rather than a checksum over the set, because the answer the
 * poll wants is *which* rows changed, not whether any did. That is what lets a
 * tick answer with two rows instead of a page — and, in the common case of a
 * table nobody is editing, with nothing at all.
 *
 * The rule worth stating is the one about the key set. If the rows on the page
 * are not the same rows, no per-row partial can describe what happened: a row
 * arrived, left, or moved under the sort, and the page's *shape* changed rather
 * than a row's contents. {@see changed()} says so by returning null, and the
 * caller falls back to a full render.
 *
 * Pure on purpose. This is the part of the partial path that decides whether
 * anything renders at all, and it used to be reachable only through a Livewire
 * poll in a browser.
 */
final class RowStamps
{
    /**
     * Stamp each record by its attributes, keyed by primary key.
     *
     * The attributes only — not the model object — so two loads of an unchanged
     * row stamp identically.
     *
     * Keys are `array-key` rather than `string` because PHP casts a numeric
     * string array key to an int, and primary keys usually are numeric. The
     * caller compares them strictly against the keys of the same array, so the
     * two always agree — but a caller comparing against a key from elsewhere
     * must not assume a string.
     *
     * @param  iterable<mixed>  $records
     * @return array<array-key, int>
     */
    public static function of(iterable $records, string $key): array
    {
        $stamps = [];

        foreach ($records as $record) {
            $stamps[(string) $record->{$key}] = crc32((string) json_encode($record->getAttributes()));
        }

        return $stamps;
    }

    /**
     * The keys whose stamp moved, or null when the page holds different rows.
     *
     * Null and an empty array mean opposite things and the caller treats them
     * as such: null is "render everything", `[]` is "render nothing".
     *
     * @param  array<array-key, int>|mixed  $previous  Whatever the poll state held.
     * @param  array<array-key, int>  $current
     * @return array<int, array-key>|null
     */
    public static function changed(mixed $previous, array $current): ?array
    {
        // The first poll of a page has nothing to compare against, which is the
        // same situation as the page having changed.
        if (! is_array($previous) || array_keys($previous) !== array_keys($current)) {
            return null;
        }

        return array_keys(array_filter(
            $current,
            fn (int $stamp, int|string $recordKey): bool => ($previous[$recordKey] ?? null) !== $stamp,
            ARRAY_FILTER_USE_BOTH,
        ));
    }
}
