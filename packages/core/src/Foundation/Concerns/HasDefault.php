<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * Default value for form components.
 */
trait HasDefault
{
    protected mixed $default = null;

    protected bool $defaultOnNull = false;

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function getDefault(): mixed
    {
        return $this->evaluate($this->default);
    }

    /**
     * Let this field's {@see default()} also fill an existing null (or empty-string)
     * value, not just a genuinely-absent key.
     *
     * By default a `->default()` is a create-mode intent: it seeds only keys the
     * caller never provided, so a record's persisted value — even an intentional
     * null the user cleared — is never overwritten. Opting in flips that for this
     * one field: an edit-mode null is treated as "unset" and receives the default.
     * Use only where null is not a value the user can deliberately choose.
     */
    public function defaultOnNull(bool $condition = true): static
    {
        $this->defaultOnNull = $condition;

        return $this;
    }

    public function isDefaultOnNull(): bool
    {
        return $this->defaultOnNull;
    }
}
