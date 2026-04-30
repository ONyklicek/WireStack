<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use Closure;

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
     * @param  array<string, mixed>  $namedArgs
     */
    protected function evaluate(mixed $value, array $namedArgs = []): mixed
    {
        if ($value instanceof Closure) {
            return app()->call($value, array_merge(
                ['component' => $this],
                $namedArgs,
            ));
        }

        return $value;
    }
}
