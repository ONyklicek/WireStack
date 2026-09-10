<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Contracts;

use NyonCode\WireCore\Foundation\Concerns\HasExtraInputAttributes;

/**
 * A field that can render for a browser's own form submit instead of Livewire.
 *
 * Implemented only where it is true. A field bound with `wire:model` and nothing
 * else carries no `name`, so a browser posts nothing for it — dropped into a
 * native `<form>` it is an input that looks right and submits an empty value.
 * The form refuses to render such a field rather than letting that reach a
 * login page, which is why this is a contract to be implemented and not a flag
 * every field is assumed to honour.
 *
 * See ADR 0036. What cannot implement it is anything needing a round-trip
 * mid-form — `live()` and reactive fields, a `Select` searching on the server,
 * `FileUpload`'s temporary uploads, `Repeater`, `Builder`.
 */
interface SupportsNativeSubmit
{
    /**
     * Render this field for a native submit rather than for Livewire.
     *
     * Called by the form for every field in its schema, so a field is never
     * asked to decide the mode it is in.
     */
    public function nativeSubmit(bool $native = true): static;

    /** Whether this field is currently rendering for a native submit. */
    public function submitsNatively(): bool;

    /**
     * The attributes binding this field's element to a native form submission.
     *
     * Only this branch. The Livewire binding stays where each view already
     * builds it, because the views do not build it identically — a text input
     * appends its debounce modifier and a checkbox does not — and folding two
     * spellings into one owner here would change what the second one renders.
     *
     * One escaped fragment, the same shape
     * {@see HasExtraInputAttributes::getExtraInputAttributesHtml()}
     * already has, so a view echoes a string rather than repeating the escaping.
     */
    public function getNativeBindingHtml(): string;
}
