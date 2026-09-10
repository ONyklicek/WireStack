<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use NyonCode\WireForms\Concerns\CanSubmitNatively;
use NyonCode\WireForms\Contracts\SupportsNativeSubmit;
use NyonCode\WireForms\Exceptions\FormConfigurationException;
use NyonCode\WireForms\Support\FieldBounds;

/**
 * OTP / PIN input — N individual character boxes with automatic focus advance.
 *
 * **The boxes are the enhancement, not the field.** They carry no `name` of
 * their own — six inputs would post six values — so the field itself is one
 * control holding the joined string: `wire:model` state in a Livewire form, and
 * in a native-submit one (ADR 0036) a real text input that the boxes write into
 * and Alpine hides. Which is what keeps the two-factor challenge answerable with
 * JavaScript off: no Alpine, no boxes, and the input they replace is still on
 * the page with the code's own name on it.
 */
class OtpInput extends Field implements SupportsNativeSubmit
{
    use CanSubmitNatively;

    protected int $length = 6;

    protected bool $numericOnly = false;

    protected bool $masked = false;

    protected ?int $separator = null;

    /**
     * Number of individual input boxes.
     *
     * @throws FormConfigurationException When not at least 1 — a zero-length OTP
     *                                    renders no boxes at all.
     */
    public function length(int $length): static
    {
        FieldBounds::assertPositive(static::class, 'length', $length);

        $this->length = $length;

        return $this;
    }

    /** Accept digits only (inputmode="numeric", pattern="[0-9]"). */
    public function numericOnly(bool $condition = true): static
    {
        $this->numericOnly = $condition;

        return $this;
    }

    /** Mask the characters like a password field. */
    public function masked(bool $condition = true): static
    {
        $this->masked = $condition;

        return $this;
    }

    /**
     * Show a visual separator (e.g. dash) after every N characters.
     *
     * @throws FormConfigurationException When not at least 1 — "after every 0
     *                                    characters" has no rendering.
     */
    public function separator(int $after): static
    {
        FieldBounds::assertPositive(static::class, 'separator', $after);

        $this->separator = $after;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    public function getLength(): int
    {
        return $this->length;
    }

    public function isNumericOnly(): bool
    {
        return $this->numericOnly;
    }

    public function isMasked(): bool
    {
        return $this->masked;
    }

    public function getSeparator(): ?int
    {
        return $this->separator;
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.otp-input';
    }
}
