<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Support;

/**
 * Utility for triggering runtime deprecation warnings.
 *
 * Emits E_USER_DEPRECATED notices that can be captured by
 * error handlers, logged, or displayed in debug mode.
 */
final class Deprecation
{
    private static bool $enabled = true;

    /** @var array<string, true> Already warned keys to avoid spam */
    private static array $warned = [];

    /**
     * Trigger a deprecation warning for a renamed method.
     *
     * @param  string  $old  Old method name (e.g. 'polling')
     * @param  string  $new  New method name (e.g. 'poll')
     * @param  string  $version  Version when it will be removed (e.g. '2.0')
     */
    public static function method(string $old, string $new, string $version = '2.0'): void
    {
        self::warn(
            "Method {$old}() is deprecated, use {$new}() instead. It will be removed in v{$version}.",
            $old,
        );
    }

    /**
     * Trigger a deprecation warning for a renamed class.
     *
     * @param  string  $old  Old class name
     * @param  string  $new  New class name
     * @param  string  $version  Version when it will be removed
     */
    public static function classRenamed(string $old, string $new, string $version = '2.0'): void
    {
        self::warn(
            "Class {$old} is deprecated, use {$new} instead. It will be removed in v{$version}.",
            $old,
        );
    }

    /**
     * Trigger a deprecation warning for a removed property.
     *
     * @param  string  $class  Class name
     * @param  string  $property  Property name
     * @param  string  $alternative  What to use instead
     * @param  string  $version  Version when it will be removed
     */
    public static function property(string $class, string $property, string $alternative, string $version = '2.0'): void
    {
        self::warn(
            "Property {$class}::\${$property} is deprecated, use {$alternative} instead. It will be removed in v{$version}.",
            "{$class}::{$property}",
        );
    }

    /**
     * Trigger a generic deprecation warning.
     */
    public static function warn(string $message, ?string $deduplicationKey = null): void
    {
        if (! self::$enabled) {
            return;
        }

        $key = $deduplicationKey ?? $message;
        if (isset(self::$warned[$key])) {
            return;
        }

        self::$warned[$key] = true;

        @trigger_error($message, E_USER_DEPRECATED);
    }

    /**
     * Disable deprecation warnings (useful for tests).
     */
    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * Enable deprecation warnings.
     */
    public static function enable(): void
    {
        self::$enabled = true;
    }

    /**
     * Reset the warned keys (useful for tests).
     */
    public static function reset(): void
    {
        self::$warned = [];
    }
}
