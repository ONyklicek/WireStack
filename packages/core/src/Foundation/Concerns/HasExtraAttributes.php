<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;

/**
 * Extra HTML attributes for components.
 */
trait HasExtraAttributes
{
    /** @var array<string, mixed>|Closure */
    protected array|Closure $extraAttributes = [];

    /** @var array<string, mixed>|Closure */
    protected array|Closure $extraInputAttributes = [];

    /**
     * Set extra HTML attributes merged onto the component's outer element.
     *
     * @param  array<string, mixed>|Closure  $attributes
     */
    public function extraAttributes(array|Closure $attributes): static
    {
        $this->extraAttributes = $attributes;

        return $this;
    }

    /**
     * Set extra HTML attributes merged onto the component's inner input element.
     *
     * @param  array<string, mixed>|Closure  $attributes
     */
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
