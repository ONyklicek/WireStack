<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;

/**
 * Free-form tag input with optional suggestions, limits, and relationship support.
 */
class Tags extends Field
{
    /** @var array<int, string>|Closure */
    protected array|Closure $suggestions = [];

    /** @var array<int, string> */
    protected array $splitKeys = ['Enter', ','];

    protected ?int $minItems = null;

    protected ?int $maxItems = null;

    protected bool $allowNew = true;

    protected bool $allowDuplicates = false;

    protected ?string $relationship = null;

    protected ?string $titleAttribute = null;

    /**
     * @param  array<int, string>|Closure  $suggestions  Predefined values shown as autocomplete.
     */
    public function suggestions(array|Closure $suggestions): static
    {
        $this->suggestions = $suggestions;

        return $this;
    }

    /**
     * @param  array<int, string>  $keys  Keys that commit the current input as a tag (e.g. ['Enter', ',']).
     */
    public function splitKeys(array $keys): static
    {
        $this->splitKeys = $keys;

        return $this;
    }

    public function minItems(?int $count): static
    {
        $this->minItems = $count;

        return $this;
    }

    public function maxItems(?int $count): static
    {
        $this->maxItems = $count;

        return $this;
    }

    /** Allow the user to create tags not in the suggestions list. */
    public function allowNew(bool $condition = true): static
    {
        $this->allowNew = $condition;

        return $this;
    }

    public function allowDuplicates(bool $condition = true): static
    {
        $this->allowDuplicates = $condition;

        return $this;
    }

    public function relationship(?string $name, ?string $titleAttribute = null): static
    {
        $this->relationship = $name;
        $this->titleAttribute = $titleAttribute;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    /**
     * @return array<int, string>
     */
    public function getSuggestions(): array
    {
        return $this->evaluate($this->suggestions);
    }

    /**
     * @return array<int, string>
     */
    public function getSplitKeys(): array
    {
        return $this->splitKeys;
    }

    public function getMinItems(): ?int
    {
        return $this->minItems;
    }

    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }

    public function isAllowNew(): bool
    {
        return $this->allowNew;
    }

    public function isAllowDuplicates(): bool
    {
        return $this->allowDuplicates;
    }

    public function getRelationship(): ?string
    {
        return $this->relationship;
    }

    public function getTitleAttribute(): ?string
    {
        return $this->titleAttribute;
    }

    public function getStateType(): string
    {
        return 'array';
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.tags';
    }
}
