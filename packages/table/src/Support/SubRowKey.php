<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

/**
 * The key a sub-row action travels under: the parent row's key and the child's.
 *
 * A sub-row action runs through the same entry points as a row action
 * (`openActionModal`, `executeTableAction`, the modal frame, the halt), and those
 * carry one record key. A child's own key would be looked up among the PARENT
 * rows — another model, so another record with the same id, or none — so a
 * sub-row button sends both, and the host finds the child through the parent
 * the table's own query returns. That is also what keeps a forged key from
 * reaching a child of a row the viewer cannot see.
 *
 * The separator never occurs in an integer, UUID or ULID key and survives the
 * single-quoted Livewire expression it is written into.
 */
final class SubRowKey
{
    private const SEPARATOR = '~sub~';

    public static function encode(int|string $parentKey, int|string $childKey): string
    {
        return $parentKey.self::SEPARATOR.$childKey;
    }

    /**
     * @return array{0: string, 1: string}|null [parent key, child key], or null for a row key
     */
    public static function decode(int|string|null $key): ?array
    {
        if (! is_string($key) || ! str_contains($key, self::SEPARATOR)) {
            return null;
        }

        [$parent, $child] = explode(self::SEPARATOR, $key, 2);

        return $parent === '' || $child === '' ? null : [$parent, $child];
    }
}
