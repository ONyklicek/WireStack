<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Console\Support;

use NyonCode\WireCore\Foundation\Console\Actions\ScaffoldComponent;

/**
 * What {@see ScaffoldComponent} did — which files it wrote, and which it found
 * already there and left alone.
 */
final class ScaffoldedComponent
{
    public function __construct(
        public readonly string $class,
        public readonly string $classPath,
        public readonly bool $classWritten,
        public readonly ?string $viewPath = null,
        public readonly bool $viewWritten = false,
    ) {}
}
