<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use NyonCode\WireCore\Foundation\Concerns\HasSheetOnMobile;
use NyonCode\WireForms\Concerns\HasItemLimits;
use NyonCode\WireForms\Concerns\HasRelationship;

/**
 * Free-form tag input with optional suggestions, limits, and relationship support.
 */
class Tags extends Field
{
    use HasItemLimits;
    use HasRelationship;
    use HasSheetOnMobile;

    /** @var array<int, string>|Closure */
    protected array|Closure $suggestions = [];

    /** @var array<int, string> */
    protected array $splitKeys = ['Enter', ','];

    protected bool $allowNew = true;

    protected bool $allowDuplicates = false;

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

    public function isAllowNew(): bool
    {
        return $this->allowNew;
    }

    public function isAllowDuplicates(): bool
    {
        return $this->allowDuplicates;
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
