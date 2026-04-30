<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;

/**
 * Select / dropdown field with search, multiple selection, native mode, and relationship support.
 */
class Select extends Field
{
    /** @var array<string|int, string>|Closure */
    protected array|Closure $options = [];

    protected bool $searchable = false;

    protected bool $multiple = false;

    protected bool $native = false;

    protected ?int $maxItems = null;

    protected ?int $minItems = null;

    protected ?string $noSearchResultsMessage = null;

    protected ?string $loadingMessage = null;

    protected ?string $searchPrompt = null;

    protected bool $allowHtml = false;

    protected ?string $relationship = null;

    protected ?string $titleAttribute = null;

    /**
     * @param  array<string|int, string>|Closure  $options
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function searchable(bool $condition = true): static
    {
        $this->searchable = $condition;

        return $this;
    }

    public function multiple(bool $condition = true): static
    {
        $this->multiple = $condition;

        return $this;
    }

    public function native(bool $condition = true): static
    {
        $this->native = $condition;

        return $this;
    }

    public function maxItems(?int $count): static
    {
        $this->maxItems = $count;

        return $this;
    }

    public function minItems(?int $count): static
    {
        $this->minItems = $count;

        return $this;
    }

    public function noSearchResultsMessage(?string $message): static
    {
        $this->noSearchResultsMessage = $message;

        return $this;
    }

    public function loadingMessage(?string $message): static
    {
        $this->loadingMessage = $message;

        return $this;
    }

    public function searchPrompt(?string $prompt): static
    {
        $this->searchPrompt = $prompt;

        return $this;
    }

    public function allowHtml(bool $condition = true): static
    {
        $this->allowHtml = $condition;

        return $this;
    }

    public function relationship(?string $name, ?string $titleAttribute = null): static
    {
        $this->relationship = $name;
        $this->titleAttribute = $titleAttribute;

        return $this;
    }

    public function boolean(): static
    {
        $this->options(function () {
            try {
                $yes = trans('wire-forms::fields.yes');
                $no = trans('wire-forms::fields.no');
            } catch (\Throwable) {
                $yes = 'Yes';
                $no = 'No';
            }

            return [
                true => $yes,
                false => $no,
            ];
        });

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    /**
     * @return array<string|int, string>
     */
    public function getOptions(): array
    {
        return $this->evaluate($this->options);
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function isNative(): bool
    {
        return $this->native;
    }

    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }

    public function getMinItems(): ?int
    {
        return $this->minItems;
    }

    public function getNoSearchResultsMessage(): string
    {
        return $this->noSearchResultsMessage ?? trans('wire-forms::fields.no_results');
    }

    public function getLoadingMessage(): string
    {
        return $this->loadingMessage ?? trans('wire-forms::fields.loading');
    }

    public function getSearchPrompt(): string
    {
        return $this->searchPrompt ?? trans('wire-forms::fields.search');
    }

    public function isAllowHtml(): bool
    {
        return $this->allowHtml;
    }

    public function getRelationship(): ?string
    {
        return $this->relationship;
    }

    public function getTitleAttribute(): ?string
    {
        return $this->titleAttribute;
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.select';
    }
}
