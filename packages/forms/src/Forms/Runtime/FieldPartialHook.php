<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Runtime;

use Closure;
use Livewire\ComponentHook;
use NyonCode\WireForms\Concerns\InteractsWithFieldPartials;

/**
 * Runs the field diff after a property update, before anything renders.
 *
 * A `ComponentHook` rather than an `updated()` method on the trait, for one
 * reason: a class method wins over a trait method silently, so any host that
 * defines its own `updated()` would switch the feature off without a word. The
 * hook cannot be shadowed.
 *
 * The timing is what makes it work. Livewire sets every property in the request,
 * then runs the `update` finish closures, then the calls, then the render — so
 * queueing here is early enough for `PartialRenderHook::call()` to see the
 * regions and skip the view, and late enough that the diff reads the state the
 * request actually produced rather than a half-applied one.
 */
final class FieldPartialHook extends ComponentHook
{
    /**
     * @param  mixed  $propertyName
     * @param  mixed  $fullPath
     * @param  mixed  $newValue
     * @return Closure(): void
     */
    public function update($propertyName, $fullPath, $newValue)
    {
        return function (): void {
            $component = $this->component;

            if (! in_array(InteractsWithFieldPartials::class, class_uses_recursive($component), true)) {
                return;
            }

            // Once per request however many properties the commit carried: the
            // diff already looks at every field, so a second pass would render
            // them all again to reach the same answer.
            if ($this->ran) {
                return;
            }

            $this->ran = true;

            (fn () => $this->queueChangedFieldPartials())->call($component);
        };
    }

    private bool $ran = false;
}
