<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;

/**
 * Extra HTML attributes for components.
 */
trait HasExtraAttributes
{
    protected array|Closure $extraAttributes = [];

    protected array|Closure $extraInputAttributes = [];

    public function extraAttributes(array|Closure $attributes): static
    {
        $this->extraAttributes = $attributes;

        return $this;
    }

    public function extraInputAttributes(array|Closure $attributes): static
    {
        $this->extraInputAttributes = $attributes;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtraAttributes(): array
    {
        return $this->evaluate($this->extraAttributes) ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtraInputAttributes(): array
    {
        return $this->evaluate($this->extraInputAttributes) ?? [];
    }
}
