<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Hydration;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * Transforms values between different representations based on cast types.
 */
final class ValueTransformer
{
    /**
     * Transform a value using the specified cast type.
     */
    public function transform(mixed $value, string $cast): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->normalizeCast($cast)) {
            'string' => (string) $value,
            'int', 'integer' => (int) $value,
            'float', 'double', 'real' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'array' => is_string($value) ? json_decode($value, true) : (array) $value,
            'json' => is_string($value) ? json_decode($value, true) : $value,
            'datetime', 'date', 'immutable_datetime', 'immutable_date' => $value instanceof DateTimeInterface ? Carbon::instance($value)->toISOString() : (string) $value,
            'timestamp' => $value instanceof DateTimeInterface ? Carbon::instance($value)->getTimestamp() : (int) $value,
            'collection' => $value instanceof Collection ? $value->toArray() : (array) $value,
            default => $value,
        };
    }

    /**
     * Reverse transform a value back from its transformed representation.
     */
    public function reverseTransform(mixed $value, string $cast): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->normalizeCast($cast)) {
            'string' => (string) $value,
            'int', 'integer' => (int) $value,
            'float', 'double', 'real' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            // Leave the array intact: the dehydrated value is written back through
            // Model::setAttribute(), whose 'array'/'json' cast JSON-encodes it
            // exactly once. Pre-encoding here made the cast encode a second time,
            // persisting a double-encoded column that read back as a string. This
            // mirrors the datetime arm, which returns a Carbon and lets the cast
            // format it rather than pre-formatting a DB string.
            'array', 'json' => $value,
            'datetime', 'date', 'immutable_datetime', 'immutable_date' => Carbon::parse((string) $value),
            'timestamp' => Carbon::createFromTimestamp((int) $value),
            'collection' => Collection::make(is_array($value) ? $value : []),
            default => $value,
        };
    }

    /**
     * Strip Eloquent cast arguments, e.g. decimal:2 or datetime:Y-m-d.
     */
    private function normalizeCast(string $cast): string
    {
        return strtolower(strtok($cast, ':') ?: $cast);
    }
}
