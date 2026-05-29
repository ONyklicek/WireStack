<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\State;

use Illuminate\Support\Arr;

/**
 * Resolves dot-notation paths to nested array access.
 *
 * Provides static utilities for reading, writing, and removing
 * values from nested arrays using dot-separated path strings.
 */
final class StatePathResolver
{
    /**
     * Get a value at the given dot-notation path.
     *
     * @param  array<string, mixed>  $state
     */
    public static function resolve(array $state, string $path): mixed
    {
        return Arr::get($state, $path);
    }

    /**
     * Set a value at the given dot-notation path.
     *
     * @param  array<string, mixed>  $state
     */
    public static function set(array &$state, string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $ref = &$state;
        foreach ($keys as $key) {
            if (! is_array($ref[$key] ?? null)) {
                $ref[$key] = [];
            }
            $ref = &$ref[$key];
        }
        $ref = $value;
    }

    /**
     * Check if a value exists at the given dot-notation path.
     *
     * @param  array<string, mixed>  $state
     */
    public static function has(array $state, string $path): bool
    {
        return Arr::has($state, $path);
    }

    /**
     * Remove a value at the given dot-notation path.
     *
     * @param  array<string, mixed>  $state
     */
    public static function forget(array &$state, string $path): void
    {
        $keys = explode('.', $path);
        $ref = &$state;
        $last = array_pop($keys);
        foreach ($keys as $key) {
            if (! isset($ref[$key]) || ! is_array($ref[$key])) {
                return;
            }
            $ref = &$ref[$key];
        }
        unset($ref[$last]);
    }

    /**
     * Split a dot-notation path into its individual segments.
     *
     * @return array<int, string>
     */
    public static function segments(string $path): array
    {
        return explode('.', $path);
    }
}
