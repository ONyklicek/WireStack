<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

/**
 * Minimum / maximum character-length constraints for text fields.
 *
 * Shared by single-line and multi-line text inputs (TextInput, Textarea).
 * Distinct from `minValue()` / `maxValue()`, which wrap closures and drive
 * numeric bounds — these are plain length values.
 */
trait HasCharacterLimits
{
    protected ?int $minLength = null;

    protected ?int $maxLength = null;

    /** Set the minimum character length (adds the `min` validation rule). */
    public function minLength(?int $length): static
    {
        $this->minLength = $length;

        return $this;
    }

    /** Set the maximum character length (adds the `max` validation rule). */
    public function maxLength(?int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    public function getMinLength(): ?int
    {
        return $this->minLength;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }
}
