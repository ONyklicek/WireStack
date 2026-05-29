<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use Illuminate\Support\Arr;

/**
 * Helper for working with dot-notation array paths.
 * Wraps Laravel's Arr helpers with Wire-specific conventions.
 */
final class ArrayDotHelper
{
    /**
     * Get a value from a nested array using dot notation.
     *
     * @param  array<string, mixed>  $data
     */
    public static function get(array $data, string $path, mixed $default = null): mixed
    {
        return Arr::get($data, $path, $default);
    }

    /**
     * Set a value in a nested array using dot notation.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function set(array &$data, string $path, mixed $value): array
    {
        $keys = explode('.', $path);
        $ref = &$data;
        foreach ($keys as $key) {
            if (! is_array($ref[$key] ?? null)) {
                $ref[$key] = [];
            }
            $ref = &$ref[$key];
        }
        $ref = $value;

        return $data;
    }

    /**
     * Check if a key exists in a nested array using dot notation.
     *
     * @param  array<string, mixed>  $data
     */
    public static function has(array $data, string $path): bool
    {
        return Arr::has($data, $path);
    }

    /**
     * Remove a key from a nested array using dot notation.
     *
     * @param  array<string, mixed>  $data
     */
    public static function forget(array &$data, string $path): void
    {
        $keys = explode('.', $path);
        $ref = &$data;
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
     * Prepend a prefix to a dot-notation path.
     */
    public static function prefix(string $prefix, string $path): string
    {
        if ($prefix === '') {
            return $path;
        }

        return "{$prefix}.{$path}";
    }
}
