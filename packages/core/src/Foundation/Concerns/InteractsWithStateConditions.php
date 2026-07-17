<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Support\StateMatcher;

/**
 * Conditioning a component on a *sibling field's* live state.
 *
 * Split out of {@see HasVisibility} and {@see CanBeDisabled}, which is where
 * these three used to live. They read sibling state through the `$get` accessor
 * that {@see EvaluatesClosures} injects, so they only mean anything on a
 * state-aware surface: fields and layouts inside a form. A table column has no
 * siblings and never receives `$get`, so `visibleWhen()` there would answer
 * "visible" whatever it was handed — API that cannot work.
 *
 * Compose this only where a form state context exists.
 */
trait InteractsWithStateConditions
{
    abstract public function visible(bool|Closure $condition = true): static;

    abstract public function hidden(bool|Closure $condition = true): static;

    abstract public function disabled(bool|Closure $condition = true): static;

    /**
     * Show this component only when another field equals the given value (or is
     * one of the given values).
     */
    public function visibleWhen(string $field, mixed $value = true): static
    {
        return $this->visible(StateMatcher::condition($field, $value, whenMissing: true));
    }

    /**
     * Hide this component when another field equals the given value (or is one
     * of the given values).
     */
    public function hiddenWhen(string $field, mixed $value = true): static
    {
        return $this->hidden(StateMatcher::condition($field, $value, whenMissing: false));
    }

    /**
     * Disable this component when another field equals the given value (or is
     * one of the given values).
     */
    public function disabledWhen(string $field, mixed $value = true): static
    {
        return $this->disabled(StateMatcher::condition($field, $value, whenMissing: false));
    }
}
