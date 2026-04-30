<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;

/**
 * Provides an id property for components.
 */
trait HasId
{
    protected string|Closure|null $id = null;

    public function id(string|Closure $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): string
    {
        $id = $this->evaluate($this->id);

        return $id ?? $this->getStatePath();
    }
}
