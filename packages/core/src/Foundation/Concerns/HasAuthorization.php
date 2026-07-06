<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Trait HasAuthorization
 *
 * Shared authorization logic for components (actions, columns, filters, fields, widgets).
 * Uses only Laravel Gate — compatible with spatie/laravel-permission and
 * nyoncode/laravel-permission-extended (both register into Gate automatically).
 */
trait HasAuthorization
{
    protected ?string $permission = null;

    protected ?string $gateAbility = null;

    protected ?Closure $authorizeCallback = null;

    /**
     * Set a permission string for authorization.
     *
     * Checked via Gate::allows() — works with Laravel Gate,
     * spatie/laravel-permission, and nyoncode/laravel-permission-extended
     * (including wildcard permissions like 'admin.*').
     */
    public function permission(?string $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    /**
     * Authorize using a Laravel Gate ability.
     *
     * Example: ->authorize('viewSalary')
     */
    public function authorize(?string $ability): static
    {
        $this->gateAbility = $ability;

        return $this;
    }

    /**
     * Authorize using a custom callback.
     *
     * The callback receives the authenticated user and, where the surface has
     * one, the row's record — so authorization can be scoped per record:
     *
     *   ->authorizeUsing(fn (User $user) => $user->hasRole('admin'))
     *   ->authorizeUsing(fn (User $user, $record) => $user->id === $record?->owner_id)
     *
     * The record is present for row actions; it is null for record-less
     * surfaces (structural column/filter visibility, fields, widgets).
     */
    public function authorizeUsing(?Closure $callback): static
    {
        $this->authorizeCallback = $callback;

        return $this;
    }

    /**
     * Check if the current user is authorized.
     *
     * Priority:
     * 1. If nothing configured — allowed
     * 2. No authenticated user — denied
     * 3. Custom callback (highest priority)
     * 4. Gate ability check
     * 5. Permission string via Gate (fallback)
     */
    public function isAuthorized(mixed $context = null): bool
    {
        if (! $this->permission && ! $this->gateAbility && ! $this->authorizeCallback) {
            return true;
        }

        try {
            /** @var Authenticatable|null $user */
            $user = auth()->guard()->user();
        } catch (Throwable) {
            return false;
        }

        if (! $user) {
            return false;
        }

        // Custom authorize callback (highest priority). The context — the row's
        // record where the caller has one (actions), null for record-less
        // surfaces (structural column/filter visibility, fields, widgets) — is
        // forwarded so callbacks can gate per record: fn ($user, $record) => …
        if ($this->authorizeCallback) {
            return (bool) ($this->authorizeCallback)($user, $context);
        }

        // Gate ability check
        if ($this->gateAbility) {
            return Gate::forUser($user)->allows($this->gateAbility, $context);
        }

        // Permission string via Gate (works with Laravel, Spatie, permission-extended)
        if ($this->permission) {
            return Gate::forUser($user)->allows($this->permission);
        }

        return true;
    }
}
