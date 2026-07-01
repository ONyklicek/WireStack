<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Concerns\CanBeLive;
use NyonCode\WireCore\Foundation\Concerns\CanBeReadOnly;
use NyonCode\WireCore\Foundation\Concerns\HasAfterStateUpdated;
use NyonCode\WireCore\Foundation\Concerns\HasDebounce;
use NyonCode\WireCore\Foundation\Concerns\HasPlaceholder;
use NyonCode\WireCore\Foundation\Concerns\HasPrefixAndSuffix;
use NyonCode\WireCore\Foundation\Concerns\HasTooltip;
use NyonCode\WireCore\Foundation\Contracts\HasFieldActions;
use NyonCode\WireCore\Foundation\Contracts\HasStateAccessors;
use NyonCode\WireCore\Foundation\Contracts\HasStateUpdatedCallback;
use NyonCode\WireForms\Concerns\CanBeAutofocused;
use NyonCode\WireForms\Concerns\DispatchesStateUpdates;
use NyonCode\WireForms\Concerns\HasFormValidation;
use NyonCode\WireForms\Concerns\InteractsWithFormState;
use NyonCode\WireForms\Contracts\HasValidation;

/**
 * Base class for all wire-forms input field components.
 *
 * Extends core Component with validation, live bindings,
 * prefix/suffix, placeholder, read-only, and autofocus.
 */
abstract class Field extends Component implements HasFieldActions, HasStateAccessors, HasStateUpdatedCallback, HasValidation
{
    use CanBeAutofocused;
    use CanBeLive;
    use CanBeReadOnly;
    use HasAfterStateUpdated;
    use HasDebounce;
    use HasFormValidation;
    use HasPlaceholder;
    use HasPrefixAndSuffix;
    use HasTooltip;
    use InteractsWithFormState;

    /**
     * Default debounce for live text fields (ms) — prevents DOM morph from
     * overwriting a partially-typed value when a Livewire response arrives
     * mid-keystroke.
     */
    protected int $defaultLiveDebounce = 250;

    /**
     * Register a reactive callback that runs after this field's value changes.
     *
     * Overrides the canonical {@see HasAfterStateUpdated::afterStateUpdated()} to
     * auto-enable live() — without a server roundtrip the hook could never fire.
     */
    public function afterStateUpdated(?\Closure $callback): static
    {
        if ($callback !== null) {
            $this->live();
        }

        $this->afterStateUpdated = $callback;

        return $this;
    }

    /**
     * Validate this field on every change during the reactive roundtrip.
     *
     * Enables live() so the server sees each change, and marks the field so the
     * host validates just this field and refreshes its error bag entry (see
     * {@see DispatchesStateUpdates}).
     */
    public function validateLive(bool $condition = true): static
    {
        $this->validatesLive = $condition;

        if ($condition) {
            $this->live();
        }

        return $this;
    }

    /**
     * Validate this field when it loses focus, rather than on every keystroke.
     */
    public function validateOnBlur(bool $condition = true): static
    {
        $this->validatesLive = $condition;

        if ($condition) {
            $this->liveOnBlur();
        }

        return $this;
    }

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
