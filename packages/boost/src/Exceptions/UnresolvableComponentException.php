<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Thrown when a tool cannot identify what the caller asked about.
 *
 * The caller is an AI agent, so every message ends with the move that would
 * work: naming the tool that lists the valid values beats saying "invalid".
 */
final class UnresolvableComponentException extends InvalidArgumentException implements WireException
{
    public static function classMissing(string $class): self
    {
        return new self(
            "Class [{$class}] does not exist. Use list-wire-components to see the components in this application."
        );
    }

    public static function notAWireComponent(string $class): self
    {
        return new self(
            "[{$class}] defines no table(), form() or infolist() method returning a wire builder, "
            .'so there is nothing to inspect.'
        );
    }

    public static function packageMissing(string $builderClass): self
    {
        return new self("The package providing [{$builderClass}] is not installed.");
    }

    public static function unknownType(string $input): self
    {
        return new self(
            "Could not resolve component type [{$input}]. Pass a fully-qualified class name, or a short "
            .'name from list-component-types (e.g. "badge-column").'
        );
    }

    /**
     * @param  array<int, string>  $valid
     */
    public static function unknownCategory(string $category, array $valid): self
    {
        return new self(
            "Unknown category [{$category}]. Valid categories: ".implode(', ', $valid).'.'
        );
    }
}
