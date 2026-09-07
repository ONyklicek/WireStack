<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Concerns\FormatsState;
use NyonCode\WireCore\Foundation\Mentions\MentionRenderer;

class TextColumn extends Column
{
    use FormatsState;

    protected ?string $fontFamily = null;

    protected bool $richContent = false;

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

    /**
     * Print stored editor content with its mentions read back from the database.
     *
     * Implies {@see Column::html()} — resolved mentions are markup — so this is
     * not a second raw-HTML switch, it is what to do with the identities such
     * markup holds. Without it a mention renders as the name it was written
     * with, which in a table is quietly wrong rather than visibly broken.
     *
     * **It costs queries per row.** A cell is rendered on its own, so mentions
     * batch within one cell and not across the page: twenty-five rows naming
     * articles are twenty-five lookups. Worth it on a narrow table of documents;
     * not worth it on a listing that only shows the first eighty characters,
     * where `limit()` on plain text says the same thing for free.
     */
    public function richContent(bool $condition = true): static
    {
        $this->richContent = $condition;

        if ($condition) {
            $this->html();
        }

        return $this;
    }

    public function isRichContent(): bool
    {
        return $this->richContent;
    }

    public function formatValue(mixed $value, Model $record): string
    {
        if ($value === null || $value === '') {
            return $this->getEmptyCellText();
        }

        $value = $this->applyNumericAndDateFormatting($value);

        $formatted = parent::formatValue($value, $record);

        return $this->richContent
            ? app(MentionRenderer::class)->render($formatted)
            : $formatted;
    }
}
