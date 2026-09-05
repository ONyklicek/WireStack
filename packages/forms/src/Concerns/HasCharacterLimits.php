<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use NyonCode\WireForms\Exceptions\FormConfigurationException;
use NyonCode\WireForms\Support\FieldBounds;

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

    /**
     * Set the minimum character length (adds the `min` validation rule).
     *
     * @throws FormConfigurationException When negative, or above maxLength().
     */
    public function minLength(?int $length): static
    {
        FieldBounds::assertNotNegative(static::class, 'minLength', $length);
        FieldBounds::assertOrdered(static::class, 'minLength', $length, 'maxLength', $this->maxLength);

        $this->minLength = $length;

        return $this;
    }

    /**
     * Set the maximum character length (adds the `max` validation rule).
     *
     * @throws FormConfigurationException When negative, or below minLength().
     */
    public function maxLength(?int $length): static
    {
        FieldBounds::assertNotNegative(static::class, 'maxLength', $length);
        FieldBounds::assertOrdered(static::class, 'minLength', $this->minLength, 'maxLength', $length);

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
