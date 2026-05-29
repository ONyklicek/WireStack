<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;

/**
 * Range slider field for numeric values.
 */
class Slider extends Field
{
    protected int|float $min = 0;

    protected int|float|Closure $max = 100;

    protected int|float $step = 1;

    protected bool $showValue = true;

    protected ?string $color = null;

    public function min(int|float $min): static
    {
        $this->min = $min;

        return $this;
    }

    public function max(int|float|Closure $max): static
    {
        $this->max = $max;

        return $this;
    }

    public function step(int|float $step): static
    {
        $this->step = $step;

        return $this;
    }

    /** Show a badge with the current value below the slider. */
    public function showValue(bool $condition = true): static
    {
        $this->showValue = $condition;

        return $this;
    }

    /**
     * Set the fill/thumb color (any CSS color, e.g. '#f59e0b' or 'rgb(16 185 129)').
     * Defaults to the theme primary when not set.
     */
    public function color(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    public function getMin(): int|float
    {
        return $this->min;
    }

    public function getMax(): int|float
    {
        return $this->evaluate($this->max);
    }

    public function getStep(): int|float
    {
        return $this->step;
    }

    public function isShowValue(): bool
    {
        return $this->showValue;
    }

    /** CSS color used for the filled track and thumb. */
    public function getColor(): string
    {
        return $this->color ?? 'var(--color-primary-600, #2563eb)';
    }

    public function getStateType(): string
    {
        return is_float($this->step) ? 'float' : 'int';
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.slider';
    }
}
