<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Concerns\HasExtraAttributes;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithColor;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;
use NyonCode\WireCore\Foundation\View\Sparkline;

/**
 * One row of a {@see ProgressWidget}: a figure, the figure it is heading for,
 * and how far along that is.
 *
 * ## The arithmetic, and where it lives
 *
 * In PHP, like {@see Sparkline}'s and for the
 * same reason — arithmetic in a template is arithmetic nothing can test. The
 * fraction is clamped to 0–100 on both ends, so an over-delivered target draws a
 * full bar rather than a bar wider than its track, and a negative reading draws
 * an empty one rather than one growing to the left.
 *
 * A target of zero or less has no fraction to compute at all. It reads as 0 %
 * rather than as complete: "0 of 0" is an unconfigured row far more often than
 * it is a finished one, and drawing it full would announce success nobody
 * achieved.
 *
 * ## What the row prints
 *
 * The percentage, unless {@see formattedValue()} says otherwise. Deliberately
 * not `number_format($value)`: a thousands separator and a decimal mark are a
 * locale decision, and a widget is the wrong place to make one on the caller's
 * behalf. The percentage is the one reading that needs no such choice, and a
 * caller who wants `1.2M / 2M` writes exactly that.
 *
 * @phpstan-consistent-constructor
 */
class ProgressItem
{
    use EvaluatesClosures;
    use HasExtraAttributes;

    // $color + color() + getColor() come from the canonical colour-state owner;
    // HasColor stays for the class-map resolvers (getSolidBgClass, …).
    use InteractsWithColor;

    protected float $value = 0.0;

    protected float $target = 100.0;

    protected ?string $description = null;

    protected ?string $formattedValue = null;

    protected ?string $icon = null;

    public function __construct(protected string $label) {}

    public static function make(string $label): static
    {
        return new static($label);
    }

    /** Set the current reading. */
    public function value(int|float $value): static
    {
        $this->value = (float) $value;

        return $this;
    }

    /** Set the figure the reading is heading for — default 100. */
    public function target(int|float $target): static
    {
        $this->target = (float) $target;

        return $this;
    }

    /** Set the secondary line under the label. */
    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /** Print this instead of the percentage (e.g. '1.2M / 2M'). */
    public function formattedValue(?string $value): static
    {
        $this->formattedValue = $value;

        return $this;
    }

    /** Set the icon shown beside the label. */
    public function icon(string|Icon|null $icon): static
    {
        $this->icon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function getTarget(): float
    {
        return $this->target;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /** How far along the row is, as a number between 0 and 100. */
    public function getPercentage(): float
    {
        if ($this->target <= 0.0) {
            return 0.0;
        }

        return max(0.0, min(100.0, $this->value / $this->target * 100));
    }

    /** Whether the reading has reached its target. */
    public function isComplete(): bool
    {
        return $this->getPercentage() >= 100.0;
    }

    public function getFormattedValue(): string
    {
        return $this->formattedValue ?? round($this->getPercentage()).'%';
    }

    /**
     * Fill classes for the bar, routed through the canonical palette so no
     * caller-supplied colour name reaches Tailwind verbatim.
     */
    public function getBarColorClass(): string
    {
        return HasColor::getSolidBgClass($this->color ?? 'primary');
    }

    /** Accent text class for the printed value, matching the bar's hue. */
    public function getValueColorClass(): string
    {
        return $this->color !== null
            ? HasColor::getTextColorClasses($this->color)
            : 'text-gray-900 dark:text-white';
    }
}
