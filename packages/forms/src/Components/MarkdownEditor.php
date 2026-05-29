<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

/**
 * Markdown editor with optional live preview tab.
 */
class MarkdownEditor extends Field
{
    protected bool $withPreview = true;

    protected bool $livePreview = false;

    protected int $minHeight = 200;

    protected ?int $maxLength = null;

    /** Show a Preview tab that renders the markdown output. */
    public function withPreview(bool $condition = true): static
    {
        $this->withPreview = $condition;

        return $this;
    }

    /** Show editor and preview side-by-side instead of as tabs. */
    public function livePreview(bool $condition = true): static
    {
        $this->livePreview = $condition;

        return $this;
    }

    public function minHeight(int $pixels): static
    {
        $this->minHeight = $pixels;

        return $this;
    }

    public function maxLength(?int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    public function hasPreview(): bool
    {
        return $this->withPreview || $this->livePreview;
    }

    public function isLivePreview(): bool
    {
        return $this->livePreview;
    }

    public function getMinHeight(): int
    {
        return $this->minHeight;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.markdown-editor';
    }
}
