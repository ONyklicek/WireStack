<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use NyonCode\WireForms\Concerns\HasOptions;
use NyonCode\WireForms\Contracts\ProvidesImplicitValidationRules;

/**
 * Select / dropdown field with search, multiple selection, native mode, and relationship support.
 */
class Select extends Field implements ProvidesImplicitValidationRules
{
    use HasOptions;

    protected bool $searchable = false;

    protected bool $multiple = false;

    protected bool $native = false;

    protected ?int $maxItems = null;

    protected ?int $minItems = null;

    protected ?string $noSearchResultsMessage = null;

    protected ?string $loadingMessage = null;

    protected ?string $searchPrompt = null;

    protected bool $allowHtml = false;

    /** @var array<string|int>|Closure */
    protected array|Closure $disabledOptionValues = [];

    protected ?string $relationship = null;

    protected ?string $titleAttribute = null;

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

    /**
     * @param  array<string|int>|Closure  $values  Option keys that should be rendered as disabled.
     */
    public function disabledOptions(array|Closure $values): static
    {
        $this->disabledOptionValues = $values;

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

    /**
     * @return array<string|int>
     */
    public function getDisabledOptionValues(): array
    {
        return $this->evaluate($this->disabledOptionValues);
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
        // Multi-selects bind an array; normalize state so a stray scalar is
        // wrapped rather than left as a string the <select multiple> can't use.
        return $this->isMultiple() ? 'array' : 'string';
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.select';
    }
}
