<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

/**
 * OTP / PIN input — N individual character boxes with automatic focus advance.
 */
class OtpInput extends Field
{
    protected int $length = 6;

    protected bool $numericOnly = false;

    protected bool $masked = false;

    protected ?int $separator = null;

    /** Number of individual input boxes. */
    public function length(int $length): static
    {
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

    /** Show a visual separator (e.g. dash) after every N characters. */
    public function separator(int $after): static
    {
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
