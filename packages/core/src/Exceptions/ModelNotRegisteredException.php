<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Thrown when metadata is requested for a model the registry has never seen.
 */
final class ModelNotRegisteredException extends InvalidArgumentException implements WireException
{
    public static function make(string $modelClass): self
    {
        return new self("Model [{$modelClass}] not registered in MetadataRegistry.");
    }
}
