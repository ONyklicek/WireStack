<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use NyonCode\WireForms\Exceptions\FormConfigurationException;
use NyonCode\WireForms\Support\FieldBounds;

/**
 * Code editor — monospace textarea with line numbers and Tab-key support.
 */
class CodeEditor extends Field
{
    protected string $language = 'plaintext';

    protected int $minHeight = 200;

    protected bool $withLineNumbers = true;

    protected ?int $maxLength = null;

    /** Syntax language hint shown in the header (display only, no highlighting). */
    public function language(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    /** Set the minimum editor height in pixels. */
    public function minHeight(int $pixels): static
    {
        $this->minHeight = $pixels;

        return $this;
    }

    /** Show a line-number gutter. */
    public function withLineNumbers(bool $condition = true): static
    {
        $this->withLineNumbers = $condition;

        return $this;
    }

    /** Allow at most this many characters. */
    /**
     * Cap the character count (adds the `max` validation rule).
     *
     * @throws FormConfigurationException When negative.
     */
    public function maxLength(?int $length): static
    {
        FieldBounds::assertNotNegative(static::class, 'maxLength', $length);

        $this->maxLength = $length;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getMinHeight(): int
    {
        return $this->minHeight;
    }

    public function hasLineNumbers(): bool
    {
        return $this->withLineNumbers;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.code-editor';
    }
}
