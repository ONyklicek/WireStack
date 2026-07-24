<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

/**
 * Adds a client-side search box to an option list.
 *
 * Shared by choice fields that let the user filter their options
 * (Select, CheckboxList). Fields that force search on for other reasons
 * (remote results, inline option creation) simply set the trait-owned flag.
 */
trait CanBeSearchable
{
    protected bool $searchable = false;

    protected ?string $searchPrompt = null;

    /** Add a search box to filter the options. */
    public function searchable(bool $condition = true): static
    {
        $this->searchable = $condition;

        return $this;
    }

    /** Set the placeholder text shown in the search box. */
    public function searchPrompt(?string $prompt): static
    {
        $this->searchPrompt = $prompt;

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function getSearchPrompt(): string
    {
        return $this->searchPrompt ?? trans('wire-forms::fields.search');
    }
}
