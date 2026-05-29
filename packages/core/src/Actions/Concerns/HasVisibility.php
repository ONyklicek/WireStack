<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Concerns\HasAuthorization;

/**
 * Trait HasVisibility
 *
 * Shared visibility, permission, and disabled state logic for all Action types.
 * Authorization is delegated to HasAuthorization trait.
 */
trait HasVisibility
{
    use HasAuthorization;

    protected bool $hidden = false;

    protected ?Closure $hiddenCallback = null;

    protected bool $disabled = false;

    protected ?Closure $disabledCallback = null;

    public function visible(bool|Closure $visible = true): static
    {
        return $this->hidden(! $visible);
    }

    public function hidden(bool|Closure $hidden = true): static
    {
        if ($hidden instanceof Closure) {
            $this->hiddenCallback = $hidden;
        } else {
            $this->hidden = $hidden;
        }

        return $this;
    }

    public function disabled(bool|Closure $disabled = true): static
    {
        if ($disabled instanceof Closure) {
            $this->disabledCallback = $disabled;
        } else {
            $this->disabled = $disabled;
        }

        return $this;
    }

    public function isHidden(mixed $context = null): bool
    {
        if ($this->hiddenCallback && $context) {
            return ($this->hiddenCallback)($context);
        }
        if ($this->hiddenCallback) {
            return ($this->hiddenCallback)();
        }

        return $this->hidden;
    }

    public function isDisabled(mixed $context = null): bool
    {
        if ($this->disabledCallback && $context) {
            return ($this->disabledCallback)($context);
        }
        if ($this->disabledCallback) {
            return ($this->disabledCallback)();
        }

        return $this->disabled;
    }

    public function canExecute(mixed $context = null): bool
    {
        if ($this->isHidden($context)) {
            return false;
        }

        return $this->isAuthorized($context);
    }
}
