<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * Default value for form components.
 */
trait HasDefault
{
    protected mixed $default = null;

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function getDefault(): mixed
    {
        return $this->evaluate($this->default);
    }
}
