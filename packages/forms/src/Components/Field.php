<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Concerns\CanBeLive;
use NyonCode\WireCore\Foundation\Concerns\CanBeReadOnly;
use NyonCode\WireCore\Foundation\Concerns\HasDebounce;
use NyonCode\WireCore\Foundation\Concerns\HasPlaceholder;
use NyonCode\WireCore\Foundation\Concerns\HasPrefixAndSuffix;
use NyonCode\WireCore\Foundation\Concerns\HasTooltip;
use NyonCode\WireForms\Concerns\CanBeAutofocused;
use NyonCode\WireForms\Concerns\HasFormValidation;
use NyonCode\WireForms\Contracts\HasValidation;

/**
 * Base class for all wire-forms input field components.
 *
 * Extends core Component with validation, live bindings,
 * prefix/suffix, placeholder, read-only, and autofocus.
 */
abstract class Field extends Component implements HasValidation
{
    use CanBeAutofocused;
    use CanBeLive;
    use CanBeReadOnly;
    use HasDebounce;
    use HasFormValidation;
    use HasPlaceholder;
    use HasPrefixAndSuffix;
    use HasTooltip;

    /**
     * Default debounce for live text fields (ms) — prevents DOM morph from
     * overwriting a partially-typed value when a Livewire response arrives
     * mid-keystroke.
     */
    protected int $defaultLiveDebounce = 250;

    public function getDebounceModifier(): string
    {
        if ($this->debounce !== null) {
            return ".debounce.{$this->debounce}ms";
        }

        if ($this->isLive) {
            return ".debounce.{$this->defaultLiveDebounce}ms";
        }

        return '';
    }

    /**
     * Return the StateHydrator type hint for this field.
     *
     * Used when hydrating incoming state so raw request values are cast
     * to the correct PHP type before being stored in the StateContainer.
     */
    public function getStateType(): string
    {
        return 'string';
    }
}
