<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use NyonCode\WireCore\Foundation\Colors\SemanticPalette;
use NyonCode\WireForms\Exceptions\FormConfigurationException;
use NyonCode\WireForms\Support\FieldBounds;

/**
 * Star rating field with optional half-star precision.
 */
class Rating extends Field
{
    protected int $max = 5;

    protected bool $allowHalf = false;

    protected string $color = 'warning';

    protected bool $clearable = true;

    /**
     * Total number of stars/icons.
     *
     * @throws FormConfigurationException When not at least 1 — a rating with no
     *                                    stars renders an empty row.
     */
    public function max(int $max): static
    {
        FieldBounds::assertPositive(static::class, 'max', $max);

        $this->max = $max;

        return $this;
    }

    /** Allow 0.5 precision (half stars). */
    public function allowHalf(bool $condition = true): static
    {
        $this->allowHalf = $condition;

        return $this;
    }

    /** Colour token for the filled stars: warning, primary, danger, success. */
    public function color(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    /** Allow clicking the active star to clear the rating. */
    public function clearable(bool $condition = true): static
    {
        $this->clearable = $condition;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    public function getMax(): int
    {
        return $this->max;
    }

    public function isAllowHalf(): bool
    {
        return $this->allowHalf;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    /**
     * Filled-star color class (bright -500/-400 scale).
     *
     * Owns the rating's star hue map in PHP so the view only consumes the result.
     * Hues are kept in sync with the canonical Foundation palette (success =
     * emerald, not green); the default is the classic amber star.
     */
    public function getColorClasses(): string
    {
        // The role is resolved by the canonical owner rather than encoded again
        // here; only the -500 star vocabulary is this component's own. A role
        // pointed at a hue with no arm below falls to the amber default.
        return match (SemanticPalette::hue($this->color)) {
            'primary' => 'text-primary-500',
            'emerald' => 'text-emerald-500',
            'red' => 'text-red-500',
            default => 'text-amber-400',
        };
    }

    public function isClearable(): bool
    {
        return $this->clearable;
    }

    public function getStateType(): string
    {
        return $this->allowHalf ? 'float' : 'int';
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.rating';
    }
}
