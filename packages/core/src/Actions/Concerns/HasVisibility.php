<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

use Closure;
use Illuminate\Contracts\Auth\Guard;

/**
 * Trait HasVisibility
 *
 * Shared visibility, permission, and disabled state logic for all Action types.
 */
trait HasVisibility
{
    protected bool $hidden = false;

    protected ?Closure $hiddenCallback = null;

    protected bool $disabled = false;

    protected ?Closure $disabledCallback = null;

    protected ?string $permission = null;

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

    public function permission(?string $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    public function isHidden(mixed $context = null): bool
    {
        if ($this->hiddenCallback && $context) {
            return call_user_func($this->hiddenCallback, $context);
        }
        if ($this->hiddenCallback && ! $context) {
            return call_user_func($this->hiddenCallback);
        }

        return $this->hidden;
    }

    public function isDisabled(mixed $context = null): bool
    {
        if ($this->disabledCallback && $context) {
            return call_user_func($this->disabledCallback, $context);
        }

        return $this->disabled;
    }

    public function canExecute(mixed $context = null): bool
    {
        if ($this->isHidden($context)) {
            return false;
        }
        if (! $this->permission) {
            return true;
        }

        /** @var Guard $guard */
        $guard = auth()->guard();
        $user = $guard->user();
        if (! $user) {
            return false;
        }

        /** @phpstan-ignore-next-line */
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        if (method_exists($user, 'hasPermissionTo')) {
            return $user->hasPermissionTo($this->permission);
        }

        return true;
    }
}
