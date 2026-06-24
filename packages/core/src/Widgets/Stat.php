<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Concerns\HasExtraAttributes;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * @phpstan-consistent-constructor
 */
class Stat
{
    use EvaluatesClosures;
    use HasExtraAttributes;

    protected string $label;

    protected string $value;

    protected ?string $description = null;

    protected ?string $descriptionIcon = null;

    protected ?string $color = null;

    /** @var array<int, int|float>|null Sparkline data points */
    protected ?array $chart = null;

    protected ?string $icon = null;

    public function __construct(string $label, string $value)
    {
        $this->label = $label;
        $this->value = $value;
    }

    public static function make(string $label, string $value): static
    {
        return new static($label, $value);
    }

    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function descriptionIcon(string|Icon|null $icon): static
    {
        $this->descriptionIcon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    public function getDescriptionIcon(): ?string
    {
        return $this->descriptionIcon;
    }

    public function color(string|Color|null $color): static
    {
        $this->color = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    /**
     * @param  array<int, int|float>  $data
     */
    public function chart(array $data): static
    {
        $this->chart = $data;

        return $this;
    }

    /**
     * @return array<int, int|float>|null
     */
    public function getChart(): ?array
    {
        return $this->chart;
    }

    public function hasChart(): bool
    {
        return $this->chart !== null && count($this->chart) > 0;
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

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Color class for the stat value, routed through the canonical palette.
     *
     * Replaces the unsafe `text-{$color}-600` interpolation that lived in the
     * widget view (which let arbitrary color names through to Tailwind). An unset
     * color falls back to the default heading text color.
     */
    public function getValueColorClass(): string
    {
        return $this->color !== null
            ? HasColor::getTextColorClasses($this->color)
            : 'text-gray-900 dark:text-white';
    }

    /**
     * Color class for the description line, routed through the canonical palette.
     *
     * Same safe allow-list as {@see getValueColorClass()}, but an unset color
     * falls back to the muted secondary text color.
     */
    public function getDescriptionColorClass(): string
    {
        return $this->color !== null
            ? HasColor::getTextColorClasses($this->color)
            : 'text-gray-500 dark:text-gray-400';
    }

    /**
     * Accent stroke color class for the sparkline, routed through the canonical
     * chart palette so the line hue matches the rest of the design system and no
     * owner-supplied color name reaches Tailwind verbatim.
     */
    public function getChartColorClass(): string
    {
        return HasColor::getFillTextClasses($this->color ?? 'primary');
    }
}
