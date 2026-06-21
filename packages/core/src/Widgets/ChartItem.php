<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasExtraAttributes;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * A single bar/series entry rendered by {@see BarChartWidget}.
 *
 * Holds the raw numeric value plus presentation hints (label, formatted value,
 * color, optional icon, optional explicit fill percentage). The owning widget
 * is responsible for turning these into a 0–100 fill percentage; this class only
 * validates and stores its own inputs.
 *
 * @phpstan-consistent-constructor
 */
class ChartItem
{
    use EvaluatesClosures;
    use HasExtraAttributes;

    protected float $value = 0.0;

    protected ?string $formattedValue = null;

    protected ?string $color = null;

    protected ?float $percentage = null;

    protected ?string $icon = null;

    public function __construct(protected string $label) {}

    public static function make(string $label): static
    {
        return new static($label);
    }

    public function value(int|float $value): static
    {
        $this->value = (float) $value;

        return $this;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    /**
     * Pre-formatted, human-readable value (e.g. "125 000 Kč" or "72 %").
     *
     * When omitted the widget falls back to the raw {@see getValue()}.
     */
    public function formattedValue(?string $formattedValue): static
    {
        $this->formattedValue = $formattedValue;

        return $this;
    }

    public function getFormattedValue(): string
    {
        return $this->formattedValue ?? rtrim(rtrim(number_format($this->value, 2, '.', ' '), '0'), '.');
    }

    public function color(string|Color|null $color): static
    {
        $this->color = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    /**
     * Resolved color key. Always a non-empty string so views can hand it
     * straight to the safe color resolver without extra null handling.
     */
    public function getColor(): string
    {
        return $this->color ?? 'primary';
    }

    /**
     * Explicit fill percentage (0–100). When set it wins over any value-based
     * scaling the widget would otherwise compute.
     */
    public function percentage(int|float $percentage): static
    {
        $percentage = (float) $percentage;

        if ($percentage < 0 || $percentage > 100) {
            throw new InvalidArgumentException(
                "Chart item percentage must be between 0 and 100, [{$percentage}] given."
            );
        }

        $this->percentage = $percentage;

        return $this;
    }

    public function getPercentage(): ?float
    {
        return $this->percentage;
    }

    public function hasPercentage(): bool
    {
        return $this->percentage !== null;
    }

    public function icon(string|Icon|null $icon): static
    {
        $this->icon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
