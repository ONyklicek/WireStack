<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Data;

use Illuminate\Support\Arr;

/**
 * A plain array row seen through {@see RecordContract}.
 *
 * The counterpart to {@see EloquentRecord}, and what a source backed by a
 * collection, a DTO list or an API response hands back.
 *
 * `unwrap()` returns the array rather than null: the contract's escape hatch is
 * "the native thing behind this row", and for this source that is the array.
 * Callers written against `Model` will not accept it, which is the point —
 * they should be refused rather than handed something shaped almost right.
 */
final readonly class ArrayRecord implements RecordContract
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function __construct(private array $row, private string $keyName = 'id') {}

    public function getKey(): int|string
    {
        $key = $this->row[$this->keyName] ?? null;

        return is_int($key) ? $key : (string) $key;
    }

    /**
     * Dot-paths work, because nested arrays are how these rows carry what an
     * Eloquent row would carry as a relation.
     */
    public function get(string $path): mixed
    {
        return Arr::get($this->row, $path);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->row;
    }

    /**
     * @return array<string, mixed>
     */
    public function unwrap(): array
    {
        return $this->row;
    }
}
