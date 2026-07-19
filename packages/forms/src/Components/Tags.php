<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use NyonCode\WireCore\Foundation\Concerns\HasSheetOnMobile;

/**
 * Free-form tag input with optional suggestions, limits, and relationship support.
 */
class Tags extends Field
{
    use HasSheetOnMobile;

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
     * Set predefined tag values offered as autocomplete suggestions.
     *
     * @param  array<int, string>|Closure  $suggestions  Predefined values shown as autocomplete.
     */
    public function suggestions(array|Closure $suggestions): static
    {
        $this->suggestions = $suggestions;

        return $this;
    }

    /**
     * Set the keys that commit the current input as a tag.
     *
     * @param  array<int, string>  $keys  Keys that commit the current input as a tag (e.g. ['Enter', ',']).
     */
    public function splitKeys(array $keys): static
    {
        $this->splitKeys = $keys;

        return $this;
    }

    /** Require at least this many tags. */
    public function minItems(?int $count): static
    {
        $this->minItems = $count;

        return $this;
    }

    /** Allow at most this many tags. */
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

    /** Allow the same tag value to be added more than once. */
    public function allowDuplicates(bool $condition = true): static
    {
        $this->allowDuplicates = $condition;

        return $this;
    }

    /** Bind the tags to a relationship, resolving labels from the given title attribute. */
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
