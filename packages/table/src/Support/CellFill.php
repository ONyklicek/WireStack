<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireTable\Exceptions\FillRequestException;

/**
 * One value written to many records of one column — the parsed form of a fill
 * request entry.
 *
 * The wire payload is a *list* of these rather than a single entry so that
 * horizontal and rectangular fill, when they arrive, need no new endpoint: they
 * send several entries where the vertical fill sends one.
 *
 * `$records` maps record key to the optimistic-lock version the client held for
 * that row, because each row carries its own `updated_at` — one shared version
 * for the whole drag would defeat the lock. The map form is required rather than
 * a bare list of keys: PHP casts a numeric string array key to an int, so
 * `{"15": "1718"}` and `["1718"]` are indistinguishable once decoded, and
 * guessing between them would silently read a version as a record key. A client
 * with nothing to send sends null versions, not a list.
 */
final readonly class CellFill
{
    /**
     * @param  array<string, string|null>  $records  record key => client-held version
     */
    public function __construct(
        public string $column,
        public mixed $value,
        public array $records,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $payload
     * @return array<int, self>
     *
     * @throws FillRequestException
     */
    public static function listFromPayload(array $payload): array
    {
        if ($payload === [] || ! array_is_list($payload)) {
            throw FillRequestException::malformed();
        }

        return array_map(self::fromEntry(...), $payload);
    }

    /**
     * @param  array<int, self>  $fills
     * @return int total records across a parsed list
     */
    public static function countRecords(array $fills): int
    {
        return array_sum(array_map(fn (self $fill) => count($fill->records), $fills));
    }

    /**
     * @throws FillRequestException
     */
    private static function fromEntry(mixed $entry): self
    {
        if (! is_array($entry) || ! array_key_exists('value', $entry)) {
            throw FillRequestException::malformed();
        }

        $column = $entry['column'] ?? null;

        if (! is_string($column) || trim($column) === '') {
            throw FillRequestException::emptyColumn();
        }

        $records = $entry['records'] ?? null;

        if (! is_array($records) || $records === []) {
            throw FillRequestException::noRecords($column);
        }

        $parsed = [];

        foreach ($records as $key => $version) {
            if (is_array($version)) {
                throw FillRequestException::malformed();
            }

            // '' is not a version any more than null is; letting it through would
            // never match a real stamp and would reject every row as a conflict.
            $parsed[(string) $key] = ($version === null || $version === '')
                ? null
                : (string) $version;
        }

        return new self($column, $entry['value'], $parsed);
    }
}
