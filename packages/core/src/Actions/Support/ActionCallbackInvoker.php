<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Support;

use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;
use ReflectionFunction;

/**
 * Calls an action callback with the arguments it actually asked for.
 *
 * The rule is the whole class: a callback declares the parameters it wants by
 * name, gets the ones the payload has, and gets its own defaults for the rest.
 * A parameter that is neither in the payload nor defaulted is simply not passed
 * — which is what lets `fn (Model $record) => …` and `fn () => …` be the same
 * kind of thing.
 *
 * **Not `app()->call()`**, which is what {@see EvaluatesClosures}
 * uses and a reasonable person would reach for here too. The container resolves
 * an unmatched parameter from its own bindings and throws when it cannot, so a
 * callback naming something the payload does not carry would fail instead of
 * being handed nothing. Action callbacks are written by users against a payload
 * that varies by surface; skipping is the published behaviour.
 *
 * Extracted from `InteractsWithActions::invokeActionCallback()` when a second
 * caller appeared ({@see ComponentActionRunner}, which runs actions for surfaces
 * that cannot reach the host trait). The trait delegates here rather than
 * keeping a copy: two implementations of one calling convention diverge, and the
 * copy is the one nobody has exercised.
 */
final class ActionCallbackInvoker
{
    /**
     * @param  array<string, mixed>  $payload  named arguments, matched by parameter name
     */
    public function invoke(callable $callback, array $payload): mixed
    {
        $reflection = new ReflectionFunction($callback);
        $arguments = [];

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $payload)) {
                $arguments[] = $payload[$name];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            }
        }

        return $reflection->invokeArgs($arguments);
    }
}
