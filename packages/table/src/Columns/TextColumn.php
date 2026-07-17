<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Concerns\FormatsState;

class TextColumn extends Column
{
    use FormatsState;

    protected ?string $fontFamily = null;

    /**
     * Render the cell in a font family: `sans`, `serif` or `mono`.
     *
     * Tailwind ships exactly these three families, so an unknown value is passed
     * through as `font-<value>` for a project that configured its own.
     */
    public function fontFamily(?string $family): static
    {
        $this->fontFamily = $family;

        return $this;
    }

    public function getFontFamily(): ?string
    {
        return $this->fontFamily;
    }

    /**
     * Append the font family to the canonical text classes, next to size/weight.
     */
    public function getTextClasses(): string
    {
        $classes = parent::getTextClasses();

        if ($this->fontFamily === null || $this->fontFamily === '') {
            return $classes;
        }

        return trim($classes.' '.match ($this->fontFamily) {
            'sans' => 'font-sans',
            'serif' => 'font-serif',
            'mono' => 'font-mono',
            default => "font-$this->fontFamily",
        });
    }

    public function formatValue(mixed $value, Model $record): string
    {
        if ($value === null || $value === '') {
            return $this->getEmptyCellText();
        }

        $value = $this->applyNumericAndDateFormatting($value);

        return parent::formatValue($value, $record);
    }
}
