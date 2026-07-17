<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * Thrown when a plugin cannot be admitted to the registry.
 *
 * Both cases are about the state of the registry rather than a bad argument:
 * the plugin itself is fine, it is the order or the company it keeps that is
 * wrong.
 */
final class PluginRegistrationException extends RuntimeException implements WireException
{
    public static function alreadyRegistered(string $id): self
    {
        return new self("Plugin '{$id}' is already registered.");
    }

    public static function missingDependency(string $id, string $dependency): self
    {
        return new self("Plugin '{$id}' requires '{$dependency}' which is not registered.");
    }
}
