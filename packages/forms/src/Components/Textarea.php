<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use NyonCode\WireCore\Foundation\Concerns\HasExtraInputAttributes;
use NyonCode\WireForms\Concerns\HasCharacterLimits;
use NyonCode\WireForms\Exceptions\FormConfigurationException;
use NyonCode\WireForms\Support\FieldBounds;

class Textarea extends Field
{
    use HasCharacterLimits;
    use HasExtraInputAttributes;

    protected int $rows = 3;

    protected ?int $cols = null;

    protected bool $autosize = false;

    protected ?bool $spellcheck = null;

    /**
     * Set the visible number of text rows.
     *
     * @throws FormConfigurationException When not at least 1.
     */
    public function rows(int $rows): static
    {
        FieldBounds::assertPositive(static::class, 'rows', $rows);

        $this->rows = $rows;

        return $this;
    }

    /**
     * Set the visible width of the textarea in character columns.
     *
     * @throws FormConfigurationException When not at least 1.
     */
    public function cols(?int $cols): static
    {
        FieldBounds::assertPositive(static::class, 'cols', $cols);

        $this->cols = $cols;

        return $this;
    }

    /** Grow the textarea height automatically to fit its content. */
    public function autosize(bool $condition = true): static
    {
        $this->autosize = $condition;

        return $this;
    }

    /** Toggle native browser spellchecking (null leaves it unset). */
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
