<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;
use ReflectionFunction;

/**
 * Shown or hidden by a bool, or by a Closure asked about a context — the row an
 * action sits on, or nothing at all for a table-level control like a filter.
 *
 * The one owner of the rules both surfaces had copied: `visible()` stores its
 * Closure and inverts on read (a `! $closure` would coerce it to a bool and lose
 * the condition), a literal replaces any Closure so the last call wins, and a
 * Closure that needs an argument nobody has (a record-aware `fn ($record)` asked
 * bare) falls back to the static answer instead of failing.
 *
 * Distinct from {@see HasVisibility}, whose Closures are `$get`-aware and read a
 * form's live state.
 */
trait HasContextualVisibility
{
    protected bool $hidden = false;

    protected ?Closure $hiddenCallback = null;

    /**
     * True when {@see $hiddenCallback} came from {@see visible()} and its result
     * must be inverted. Tracked separately instead of wrapping the Closure, so
     * its arity survives for {@see isHidden()} to read.
     */
    protected bool $hiddenCallbackInverts = false;

    /** Show only when the condition is true (a bool, or a Closure given the context). */
    public function visible(bool|Closure $visible = true): static
    {
        if ($visible instanceof Closure) {
            $this->hiddenCallback = $visible;
            $this->hiddenCallbackInverts = true;

            return $this;
        }

        return $this->hidden(! $visible);
    }

    /** Hide when the condition is true — the inverse of `visible()`. */
    public function hidden(bool|Closure $hidden = true): static
    {
        if ($hidden instanceof Closure) {
            $this->hiddenCallback = $hidden;
            $this->hiddenCallbackInverts = false;
        } else {
            // A literal state supersedes any previously registered Closure so the
            // last call wins predictably (e.g. `->visible($cb)->hidden(false)`).
            $this->hidden = $hidden;
            $this->hiddenCallback = null;
            $this->hiddenCallbackInverts = false;
        }

        return $this;
    }

    public function isHidden(mixed $context = null): bool
    {
        $callback = $this->hiddenCallback;

        if ($callback !== null && ($context !== null || (new ReflectionFunction($callback))->getNumberOfRequiredParameters() === 0)) {
            $result = (bool) $callback($context);

            return $this->hiddenCallbackInverts ? ! $result : $result;
        }

        return $this->hidden;
    }
}
