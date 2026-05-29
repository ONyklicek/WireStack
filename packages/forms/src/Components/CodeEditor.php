<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

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

    public function minHeight(int $pixels): static
    {
        $this->minHeight = $pixels;

        return $this;
    }

    public function withLineNumbers(bool $condition = true): static
    {
        $this->withLineNumbers = $condition;

        return $this;
    }

    public function maxLength(?int $length): static
    {
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
