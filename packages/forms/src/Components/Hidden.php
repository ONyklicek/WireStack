<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use NyonCode\WireCore\Foundation\Concerns\HasExtraInputAttributes;
use NyonCode\WireForms\Concerns\CanSubmitNatively;
use NyonCode\WireForms\Contracts\SupportsNativeSubmit;

/**
 * A value the form carries without asking about it.
 *
 * **In a Livewire form it emits no markup at all**, not even the input its view
 * describes: it marks itself hidden in the constructor, every render loop skips
 * a component whose `isVisible()` is false, and the value it carries lives in
 * form state — filled in PHP, carried in the snapshot, written on save. There is
 * nothing for the DOM to hold.
 *
 * **In a native-submit form there is no state to live in**, and that is the one
 * place the input is not decoration: the browser posts what is in the document
 * and nothing else, so a hidden field with no element carries nothing at all.
 * So `submitsNatively()` is the one condition under which this renders — the
 * same promise, through the only mechanism a browser offers (ADR 0036 §7).
 */
class Hidden extends Field implements SupportsNativeSubmit
{
    use CanSubmitNatively;
    use HasExtraInputAttributes;

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->hidden();
    }

    /**
     * Invisible to every render loop, except when the browser is doing the
     * posting.
     *
     * Not a weakening of `hidden()`: what the constructor declares is "the user
     * has no business choosing this", which is as true here. What changes is
     * where the value can live — a native form has no snapshot, so an unrendered
     * hidden field is a value silently dropped from the request.
     */
    public function isVisible(): bool
    {
        return $this->submitsNatively() || parent::isVisible();
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.hidden';
    }
}
