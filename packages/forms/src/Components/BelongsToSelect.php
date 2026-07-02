<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Components\Component;

/**
 * BelongsTo relationship select with auto-loading options from related model.
 *
 * Usage:
 *   BelongsToSelect::make('company_id')
 *       ->relationship('company', 'name')
 *       ->searchable()
 *       ->preload()
 */
class BelongsToSelect extends Select
{
    protected bool $preload = false;

    /** @var Closure|null fn(Builder) => Builder — modify the options query */
    protected ?Closure $modifyOptionsQueryUsing = null;

    /** @var array<int, Component>|Closure|null Schema for inline create modal */
    protected array|Closure|null $createOptionFormSchema = null;

    /** @var Closure|null fn(array) => Model — custom create handler */
    protected ?Closure $createOptionUsing = null;

    /** @var Model|null Resolved parent model instance (set by form runtime) */
    protected ?Model $record = null;

    public function preload(bool $condition = true): static
    {
        $this->preload = $condition;

        return $this;
    }

    public function modifyOptionsQueryUsing(?Closure $callback): static
    {
        $this->modifyOptionsQueryUsing = $callback;

        return $this;
    }

    /**
     * @param  array<int, Component>|Closure  $schema
     */
    public function createOptionForm(array|Closure $schema): static
    {
        $this->createOptionFormSchema = $schema;

        return $this;
    }

    public function createOptionUsing(?Closure $callback): static
    {
        $this->createOptionUsing = $callback;

        return $this;
    }

    public function record(?Model $record): static
    {
        $this->record = $record;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    public function isPreload(): bool
    {
        return $this->preload;
    }

    /**
     * @return array<int, Component>|Closure|null
     */
    public function getCreateOptionFormSchema(): array|Closure|null
    {
        return $this->createOptionFormSchema;
    }

    public function hasCreateOptionForm(): bool
    {
        return $this->createOptionFormSchema !== null;
    }

    /**
     * Get options by resolving the BelongsTo relationship from the record's model.
     *
     * @return array<string|int, string>
     */
    public function getOptions(): array
    {
        // If user manually provided options, use those
        $manualOptions = parent::getOptions();
        if (! empty($manualOptions)) {
            return $manualOptions;
        }

        return $this->resolveRelationshipOptions();
    }

    /**
     * A relationship select can always serve server-side matches (via
     * {@see searchOptions()}), so the host's remote-search endpoint
     * (`searchSelectOptions`) accepts it even without an explicit
     * `getSearchResultsUsing()` callback.
     */
    public function hasSearchResultsCallback(): bool
    {
        return parent::hasSearchResultsCallback()
            || ($this->getRelationship() !== null && $this->getTitleAttribute() !== null);
    }

    /**
     * Relationship-driven remote search: a searchable, non-preloaded relationship
     * select asks the server for matches as the user types ({@see searchOptions()});
     * `preload()` ships the full option list and keeps filtering client-side. An
     * explicit `getSearchResultsUsing()` callback follows the parent semantics
     * (where `preload()` only seeds the remote list).
     */
    public function isRemoteSearch(): bool
    {
        if (parent::hasSearchResultsCallback()) {
            return parent::isRemoteSearch();
        }

        return $this->isSearchable()
            && ! $this->isNative()
            && ! $this->isPreload()
            && $this->getRelationship() !== null
            && $this->getTitleAttribute() !== null;
    }

    /**
     * @return array<string|int, string>
     */
    public function getSearchResults(string $search): array
    {
        if (parent::hasSearchResultsCallback()) {
            return parent::getSearchResults($search);
        }

        return $this->searchOptions($search);
    }

    /**
     * Resolve the selected value's label. Remote mode ships no option list, so
     * fall back to a single keyed lookup on the related model instead of
     * loading the whole related table.
     */
    public function getOptionLabel(string|int|null $value): ?string
    {
        $label = parent::getOptionLabel($value);

        if ($label !== null || $this->optionLabelCallback !== null || $value === null || $value === '') {
            return $label;
        }

        $relatedModel = $this->resolveRelatedModel();
        $titleAttribute = $this->getTitleAttribute();

        if ($relatedModel === null || $titleAttribute === null) {
            return null;
        }

        $title = $relatedModel::query()->find($value)?->{$titleAttribute};

        return $title === null ? null : (string) $title;
    }

    /**
     * Search options by title attribute (for AJAX searchable mode).
     *
     * @return array<string|int, string>
     */
    public function searchOptions(string $search): array
    {
        $relationName = $this->getRelationship();
        $titleAttribute = $this->getTitleAttribute();

        if ($relationName === null || $titleAttribute === null) {
            return [];
        }

        $relatedModel = $this->resolveRelatedModel();
        if ($relatedModel === null) {
            return [];
        }

        $query = $relatedModel::query()
            ->where($titleAttribute, 'like', "%{$search}%")
            ->limit(50);

        if ($this->modifyOptionsQueryUsing) {
            $query = ($this->modifyOptionsQueryUsing)($query) ?? $query;
        }

        return $query->pluck($titleAttribute, $relatedModel->getKeyName())->all();
    }

    /**
     * Create a new related record using the inline form data.
     *
     * @param  array<string, mixed>  $data
     */
    public function createOption(array $data): ?Model
    {
        if ($this->createOptionUsing) {
            return ($this->createOptionUsing)($data);
        }

        $relatedModel = $this->resolveRelatedModel();
        if ($relatedModel === null) {
            return null;
        }

        return $relatedModel->newQuery()->create($data);
    }

    /**
     * Resolve options from the BelongsTo relationship.
     *
     * @return array<string|int, string>
     */
    protected function resolveRelationshipOptions(): array
    {
        $relationName = $this->getRelationship();
        $titleAttribute = $this->getTitleAttribute();

        if ($relationName === null || $titleAttribute === null) {
            return [];
        }

        $relatedModel = $this->resolveRelatedModel();
        if ($relatedModel === null) {
            return [];
        }

        // If searchable and not preload, return empty (will be loaded via AJAX)
        if ($this->isSearchable() && ! $this->isPreload()) {
            return [];
        }

        $query = $relatedModel::query();

        if ($this->modifyOptionsQueryUsing) {
            $query = ($this->modifyOptionsQueryUsing)($query) ?? $query;
        }

        return $query->pluck($titleAttribute, $relatedModel->getKeyName())->all();
    }

    /**
     * Resolve the related model class from the relationship name.
     *
     * @return Model|null A fresh instance of the related model
     */
    protected function resolveRelatedModel(): ?Model
    {
        $relationName = $this->getRelationship();
        if ($relationName === null) {
            return null;
        }

        // Try to get model from the record
        $model = $this->record;

        if ($model === null) {
            return null;
        }

        if (! method_exists($model, $relationName)) {
            return null;
        }

        try {
            $relation = $model->{$relationName}();

            return $relation->getRelated();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.belongs-to-select';
    }
}
