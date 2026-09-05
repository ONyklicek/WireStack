<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use NyonCode\WireCore\Foundation\Concerns\HasExtraInputAttributes;
use NyonCode\WireForms\Exceptions\FormConfigurationException;
use NyonCode\WireForms\Support\FieldBounds;

/**
 * Range slider field for numeric values.
 */
class Slider extends Field
{
    use HasExtraInputAttributes;

    protected int|float $min = 0;

    protected int|float|Closure $max = 100;

    protected int|float $step = 1;

    protected bool $showValue = true;

    protected ?string $color = null;

    /**
     * Set the minimum selectable value.
     *
     * @throws FormConfigurationException When it exceeds a max() already set.
     */
    public function min(int|float $min): static
    {
        // A Closure max cannot be compared without a record to evaluate it
        // against, so the pair is only checked when both ends are literal. The
        // dynamic case is the caller's to keep consistent.
        FieldBounds::assertOrdered(
            static::class,
            'min',
            $min,
            'max',
            $this->max instanceof Closure ? null : $this->max,
        );

        $this->min = $min;

        return $this;
    }

    /**
     * Set the maximum selectable value.
     *
     * @throws FormConfigurationException When it falls below a min() already set.
     */
    public function max(int|float|Closure $max): static
    {
        FieldBounds::assertOrdered(
            static::class,
            'min',
            $max instanceof Closure ? null : $this->min,
            'max',
            $max instanceof Closure ? null : $max,
        );

        $this->max = $max;

        return $this;
    }

    /**
     * Set the increment between selectable values (default 1).
     *
     * @throws FormConfigurationException When not greater than 0 — the browser
     *                                    rejects `step="0"` and the thumb stops
     *                                    moving, which reads as a broken slider.
     */
    public function step(int|float $step): static
    {
        FieldBounds::assertPositive(static::class, 'step', $step);

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
