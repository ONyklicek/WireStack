<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * Provides a name property for components.
 */
trait HasName
{
    protected string $name;

    public function getName(): string
    {
        return $this->name;
    }
}
