<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;

/**
 * Extra HTML attributes for the component's outer element — the one every
 * component has.
 *
 * The input-element counterpart lives in {@see HasExtraInputAttributes}, mixed
 * in only where a single element carries the value: it was here until
 * 2026-08-01, which handed it to 49 types (widgets, entries, Placeholder) that
 * have no input at all, and no view ever implemented it.
 */
trait HasExtraAttributes
{
    /** @var array<string, mixed>|Closure */
    protected array|Closure $extraAttributes = [];

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
     * @return array<string, mixed>
     */
    public function getExtraAttributes(): array
    {
        return $this->evaluate($this->extraAttributes) ?? [];
    }
}
