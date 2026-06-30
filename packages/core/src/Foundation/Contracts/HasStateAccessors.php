<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts;

use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * Marks a component that can expose reactive state accessors to evaluated
 * Closures.
 *
 * When a component implements this contract, {@see EvaluatesClosures::evaluate()}
 * merges the returned accessors into the Closure's named arguments. This is how
 * fields receive Filament-style `$get` / `$set` / `$state` helpers inside
 * `visible()`, `disabled()`, and similar dynamic callbacks, resolving against
 * the live Livewire state rather than relying on the host component being
 * reachable through a global facade.
 */
interface HasStateAccessors
{
    /**
     * Named accessors injected into evaluated Closures.
     *
     * Conventionally returns the keys `get` (Closure(string $path, mixed
     * $default = null): mixed), `set` (Closure(string $path, mixed $value):
     * mixed), and `state` (the component's own current value).
     *
     * @return array<string, mixed>
     */
    public function getStateAccessors(): array;
}
