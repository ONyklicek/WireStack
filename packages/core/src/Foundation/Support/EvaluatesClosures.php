<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use Closure;
use NyonCode\WireCore\Foundation\Contracts\HasStateAccessors;

/**
 * Trait EvaluatesClosures
 *
 * Provides a consistent way to evaluate values that may be Closures.
 * Used across all components (fields, actions, layouts) to support
 * dynamic values resolved at runtime.
 */
trait EvaluatesClosures
{
    /**
     * Evaluate a value, resolving Closures with optional named parameters.
     *
     * Components implementing {@see HasStateAccessors} additionally expose their
     * reactive state accessors (`$get` / `$set` / `$state`) to the Closure, so
     * dynamic callbacks can read sibling state without reaching for a global
     * Livewire facade. Explicit named arguments always win over the defaults.
     *
     * @param  array<string, mixed>  $namedArgs
     */
    protected function evaluate(mixed $value, array $namedArgs = []): mixed
    {
        if (! $value instanceof Closure) {
            return $value;
        }

        $defaults = ['component' => $this];

        if ($this instanceof HasStateAccessors) {
            $defaults = array_merge($defaults, $this->getStateAccessors());
        }

        return app()->call($value, array_merge($defaults, $namedArgs));
    }
}
