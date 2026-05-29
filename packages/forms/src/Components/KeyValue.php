<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;

/**
 * Key-value pair editor for dictionary / map-like data.
 *
 * State is stored as array<int, array{key: string, value: string}>.
 * Use mutateFormDataBeforeSave() to reshape to an associative array if needed.
 */
class KeyValue extends Field
{
    protected string|Closure|null $keyLabel = null;

    protected string|Closure|null $valueLabel = null;

    protected ?string $keyPlaceholder = null;

    protected ?string $valuePlaceholder = null;

    protected bool $addable = true;

    protected bool $deletable = true;

    protected bool $reorderable = false;

    protected bool $keyEditable = true;

    public function keyLabel(string|Closure|null $label): static
    {
        $this->keyLabel = $label;

        return $this;
    }

    public function valueLabel(string|Closure|null $label): static
    {
        $this->valueLabel = $label;

        return $this;
    }

    public function keyPlaceholder(?string $placeholder): static
    {
        $this->keyPlaceholder = $placeholder;

        return $this;
    }

    public function valuePlaceholder(?string $placeholder): static
    {
        $this->valuePlaceholder = $placeholder;

        return $this;
    }

    public function addable(bool $condition = true): static
    {
        $this->addable = $condition;

        return $this;
    }

    public function deletable(bool $condition = true): static
    {
        $this->deletable = $condition;

        return $this;
    }

    public function reorderable(bool $condition = true): static
    {
        $this->reorderable = $condition;

        return $this;
    }

    /** Prevent the user from editing the key column (value-only mode). */
    public function keyEditable(bool $condition = true): static
    {
        $this->keyEditable = $condition;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    public function getKeyLabel(): string
    {
        return $this->evaluate($this->keyLabel) ?? __('Key');
    }

    public function getValueLabel(): string
    {
        return $this->evaluate($this->valueLabel) ?? __('Value');
    }

    public function getKeyPlaceholder(): ?string
    {
        return $this->keyPlaceholder;
    }

    public function getValuePlaceholder(): ?string
    {
        return $this->valuePlaceholder;
    }

    public function isAddable(): bool
    {
        return $this->addable && ! $this->isDisabled();
    }

    public function isDeletable(): bool
    {
        return $this->deletable && ! $this->isDisabled();
    }

    public function isReorderable(): bool
    {
        return $this->reorderable && ! $this->isDisabled();
    }

    public function isKeyEditable(): bool
    {
        return $this->keyEditable && ! $this->isDisabled();
    }

    public function getStateType(): string
    {
        return 'array';
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.key-value';
    }
}
