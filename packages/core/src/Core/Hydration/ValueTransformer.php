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

        return match ($cast) {
            'string' => (string) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'array' => is_string($value) ? json_decode($value, true) : (array) $value,
            'json' => is_string($value) ? json_decode($value, true) : $value,
            'datetime', 'date' => $value instanceof DateTimeInterface ? Carbon::instance($value)->toISOString() : (string) $value,
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

        return match ($cast) {
            'string' => (string) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'array' => is_array($value) ? json_encode($value) : $value,
            'json' => is_array($value) ? json_encode($value) : $value,
            'datetime', 'date' => Carbon::parse((string) $value),
            'timestamp' => Carbon::createFromTimestamp((int) $value),
            'collection' => Collection::make(is_array($value) ? $value : []),
            default => $value,
        };
    }
}
