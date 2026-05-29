<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

/**
 * Textarea field with autosize and row/col configuration.
 */
class Textarea extends Field
{
    protected int $rows = 3;

    protected ?int $cols = null;

    protected ?int $minLength = null;

    protected ?int $maxLength = null;

    protected bool $autosize = false;

    protected ?bool $spellcheck = null;

    public function rows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    public function cols(?int $cols): static
    {
        $this->cols = $cols;

        return $this;
    }

    public function minLength(?int $length): static
    {
        $this->minLength = $length;

        return $this;
    }

    public function maxLength(?int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    public function autosize(bool $condition = true): static
    {
        $this->autosize = $condition;

        return $this;
    }

    public function spellcheck(?bool $condition = true): static
    {
        $this->spellcheck = $condition;

        return $this;
    }

    public function getRows(): int
    {
        return $this->rows;
    }

    public function getCols(): ?int
    {
        return $this->cols;
    }

    public function getMinLength(): ?int
    {
        return $this->minLength;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function isAutosize(): bool
    {
        return $this->autosize;
    }

    public function getSpellcheck(): ?bool
    {
        return $this->spellcheck;
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.textarea';
    }
}
