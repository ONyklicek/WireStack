<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasFontWeight;
use NyonCode\WireCore\Foundation\Enums\FontWeight;
use NyonCode\WireTable\Columns\Column;

/**
 * How the cell's *text* is drawn — size, weight and colour — and the one class
 * string a cell partial needs to draw it.
 *
 * Deliberately separate from the canonical `HasSize` / `HasColor` /
 * `HasFontWeight` the column also composes, and this is the distinction that
 * caused a breaking change in v2: `size()` is the column's **structural** size,
 * `color()` its icon/link colour. The `text*` setters here are the typographic
 * pair, which is why a column can be `size('lg')` and `textSize('sm')` at once.
 *
 * Resolution still delegates: the weight class comes from
 * {@see HasFontWeight::getFontWeightClasses()} and every real colour from the
 * canonical palette. Only the text *scale* is spelled out here, because Tailwind
 * must be able to scan the literal utilities.
 *
 * @phpstan-require-extends Column
 */
trait HasTextStyling
{
    /** @var string|null Text size class or value (e.g., 'sm', 'lg', '1.2rem') */
    protected ?string $textSize = null;

    /** @var string|null Font weight (e.g., 'bold', '500') */
    protected ?string $textWeight = null;

    /** @var string|null Text color (e.g., 'red-500', '#FF0000') */
    protected ?string $textColor = null;

    /** Set the cell text size on the Tailwind text scale (distinct from `size()`, which is the structural size). */
    public function textSize(string $size): static
    {
        $this->textSize = $size;

        return $this;
    }

    public function getTextSize(): ?string
    {
        return $this->textSize;
    }

    /** Set the cell font weight (a `FontWeight` enum or keyword like `semibold`). */
    public function weight(string|FontWeight $weight): static
    {
        $this->textWeight = $weight instanceof FontWeight ? $weight->value : $weight;

        return $this;
    }

    public function getTextWeight(): ?string
    {
        return $this->textWeight;
    }

    /** Set the cell text color (a palette name or `Color` enum). */
    public function textColor(string|Color $color): static
    {
        $this->textColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function getTextColor(): ?string
    {
        return $this->textColor;
    }

    /**
     * Get CSS classes for text styling
     */
    public function getTextClasses(): string
    {
        $classes = [];

        if ($this->textSize) {
            $classes[] = match ($this->textSize) {
                'xs' => 'text-xs',
                'sm' => 'text-sm',
                'base', 'md' => 'text-base',
                'lg' => 'text-lg',
                'xl' => 'text-xl',
                '2xl' => 'text-2xl',
                default => "text-$this->textSize",
            };
        }

        if ($this->textWeight) {
            $classes[] = HasFontWeight::getFontWeightClasses($this->textWeight);
        }

        if ($this->textColor) {
            // 'muted' is a text treatment (lighter gray), not a palette color, so
            // it keeps its dedicated shade; every real color goes through the
            // canonical Foundation palette so hues stay consistent everywhere.
            $classes[] = $this->textColor === 'muted'
                ? 'text-gray-400 dark:text-gray-500'
                : self::getTextColorClasses($this->textColor);
        }

        return implode(' ', $classes);
    }
}
