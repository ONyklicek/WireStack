<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;
use Throwable;

/**
 * Thrown when a component exists but blows up while building its own schema.
 *
 * The original failure is kept as `previous` rather than flattened into a
 * string: it is the application's own exception, and whoever handles this needs
 * its type and trace, not just its message.
 *
 * `validate-wire-component` catches this and reports it as a finding — a
 * component that cannot be built is exactly what that tool is for. Every other
 * tool lets it reach the boundary and become a tool error.
 */
final class ComponentBuildFailedException extends RuntimeException implements WireException
{
    private function __construct(
        public readonly string $component,
        public readonly string $builderMethod,
        Throwable $previous,
    ) {
        parent::__construct(
            "[{$component}::{$builderMethod}()] threw while building its schema: ".$previous->getMessage(),
            0,
            $previous,
        );
    }

    public static function make(string $component, string $builderMethod, Throwable $previous): self
    {
        return new self($component, $builderMethod, $previous);
    }
}
