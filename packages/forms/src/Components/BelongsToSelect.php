<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireForms\Forms\Runtime\RelationshipSaveHandler;

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

    /** @var Closure|null fn(array) => Model — custom create handler */
    protected ?Closure $createOptionUsing = null;

    /** @var Model|null Resolved parent model instance (set by form runtime) */
    protected ?Model $record = null;

    /** Eager-load all related options up front instead of searching on demand. */
    public function preload(bool $condition = true): static
    {
        $this->preload = $condition;

        return $this;
    }

    /** Modify the options query with a callback (e.g. to scope or order it). */
    public function modifyOptionsQueryUsing(?Closure $callback): static
    {
        $this->modifyOptionsQueryUsing = $callback;

        return $this;
    }

    /** Handle creation of a new related record from a typed-in option. */
    public function createOptionUsing(?Closure $callback): static
    {
        $this->createOptionUsing = $callback;

        return $this;
    }

    /**
     * The parent record this select hangs off, used to introspect the belongsTo
     * relationship.
     *
     * Runtime wiring, not user API: FormRuntime::prepare() propagates the form's
     * ->model() into every field exposing this setter, so an owner configures
     * the record on the form, never here.
     *
     * @docs-ignore
     */
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
        // Canonical storage lives on the parent Select ({@see Select::createOptionForm()}),
        // so the shared InteractsWithSelectCreation modal flow works unchanged.
        return $this->createOptionSchema;
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
     * Fill the edit-option form from the selected related record. Like
     * {@see createOption()}, an explicit {@see fillEditOptionUsing()} wins;
     * otherwise the related model is loaded by key from the resolved relationship
     * (so `editOptionForm()` auto-fills without a callback, mirroring auto-create).
     *
     * Only the columns the edit form actually declares are pulled from the record
     * — a form with `[TextInput::make('name')]` fills `name` alone, never the id,
     * timestamps, or unrelated columns — so the modal shows exactly its own fields.
     * A dotted field name walks nested `belongsTo` relations, so
     * `TextInput::make('company.name')` fills from `$related->company->name` and
     * lands under a nested `['company' => ['name' => …]]` state path.
     *
     * @return array<string, mixed>
     */
    public function getEditOptionFormData(string|int $value): array
    {
        if ($this->fillEditOptionCallback !== null) {
            // Enrich the callback with the resolved related record so it can read
            // through relations (e.g. a nested belongsTo) itself.
            $data = $this->evaluate($this->fillEditOptionCallback, [
                'value' => $value,
                'record' => $this->resolveRelatedModel()?->newQuery()->find($value),
            ]);

            return is_array($data) ? $data : [];
        }

        $relatedModel = $this->resolveRelatedModel();
        if ($relatedModel === null) {
            return [];
        }

        $model = $relatedModel::query()->find($value);
        if ($model === null) {
            return [];
        }

        $data = [];

        foreach ($this->getEditOptionFieldNames() as $name) {
            // data_get resolves dot notation through nested relations/attributes;
            // data_set mirrors it back into the (possibly nested) form state path.
            data_set($data, $name, data_get($model, $name));
        }

        // Relationship-backed repeaters (hasMany) load their existing rows — each
        // scoped to the repeater's own subfields plus the primary key, so the sync
        // on save can match existing rows instead of recreating them. Filling them
        // is essential: an unfilled repeater would submit an empty array and the
        // sync would delete every existing child.
        foreach ($this->getEditOptionRelationshipRepeaters() as $repeater) {
            $relation = $repeater->getRelationship();

            if ($relation === null || ! method_exists($model, $relation)) {
                continue;
            }

            $relationInstance = $model->{$relation}();
            $keyName = $relationInstance->getRelated()->getKeyName();
            $subfields = $this->repeaterSubfieldNames($repeater);

            if ($relationInstance instanceof BelongsToMany) {
                // Each row is the related key plus its pivot columns (read from the
                // loaded pivot); the write-back sync() mirrors the same shape.
                $pivotColumns = array_values(array_diff($subfields, [$keyName]));

                $data[$repeater->getName()] = $model->{$relation}
                    ->map(static function (Model $item) use ($keyName, $pivotColumns): array {
                        $row = [$keyName => $item->getKey()];

                        foreach ($pivotColumns as $column) {
                            $row[$column] = data_get($item->getRelation('pivot'), $column);
                        }

                        return $row;
                    })
                    ->values()
                    ->all();

                continue;
            }

            $columns = array_values(array_unique([...$subfields, $keyName]));

            $data[$repeater->getName()] = $model->{$relation}
                ->map(static fn (Model $item): array => $item->only($columns))
                ->values()
                ->all();
        }

        return $data;
    }

    /**
     * The field names declared by the edit-option schema (flattened through any
     * layouts), i.e. the columns to load from the related record.
     *
     * @return array<int, string>
     */
    protected function getEditOptionFieldNames(): array
    {
        $form = $this->getEditOptionForm();

        if ($form === null) {
            return [];
        }

        return array_values(array_map(
            static fn ($component): string => $component->getName(),
            $form->getFlatComponents(),
        ));
    }

    /**
     * Persist an edit to the selected related record. An explicit
     * {@see updateOptionUsing()} wins; otherwise the related model is loaded by
     * key from the resolved relationship and updated with the form data.
     *
     * The related model's own (non-dotted) columns are written directly. A dotted
     * field targets a nested relation: an owned `hasOne` (a 1:1 record the related
     * model owns) is written back through that relation (created if missing),
     * while a `belongsTo` or any other relation is left to
     * {@see updateOptionUsing()} — its target is a shared record, not the related
     * model's to own.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateOption(string|int $value, array $data): void
    {
        if ($this->updateOptionCallback !== null) {
            // A full override, enriched with the resolved related record — the
            // canonical way to write back a nested belongsTo (a shared record the
            // auto path deliberately never mutates): the callback holds the parent
            // and can `$record->company->update(...)` / `associate(...)` itself.
            $this->evaluate($this->updateOptionCallback, [
                'value' => $value,
                'data' => $data,
                'record' => $this->resolveRelatedModel()?->newQuery()->find($value),
            ]);

            return;
        }

        $relatedModel = $this->resolveRelatedModel();
        if ($relatedModel === null) {
            return;
        }

        $model = $relatedModel::query()->find($value);
        if ($model === null) {
            return;
        }

        $own = [];
        /** @var array<string, array<string, mixed>> $nested */
        $nested = [];

        foreach ($this->getEditOptionFieldNames() as $name) {
            if (! str_contains($name, '.')) {
                if (array_key_exists($name, $data)) {
                    $own[$name] = $data[$name];
                }

                continue;
            }

            [$relation, $column] = explode('.', $name, 2);

            // Only a single level of nesting is auto-written; deeper paths are
            // left to updateOptionUsing().
            if (! str_contains($column, '.')) {
                $nested[$relation][$column] = data_get($data, $name);
            }
        }

        if ($own !== []) {
            $model->update($own);
        }

        foreach ($nested as $relation => $columns) {
            if (! method_exists($model, $relation)) {
                continue;
            }

            $relationInstance = $model->{$relation}();

            // Owned 1:1 only — update the existing record or create it. Both hasOne
            // and its polymorphic morphOne qualify (a morph create sets the
            // *_type/*_id on its own); the *-many variants are intentionally
            // excluded, since updateOrCreate([]) on a collection is ambiguous.
            if ($relationInstance instanceof HasOne || $relationInstance instanceof MorphOne) {
                $relationInstance->updateOrCreate([], $columns);
            }
        }

        // Relationship-backed repeaters (hasMany) sync their rows through the
        // canonical cascade handler — create new, update matched, delete removed —
        // exactly as the main form save does.
        $repeaters = $this->getEditOptionRelationshipRepeaters();

        if ($repeaters !== []) {
            (new RelationshipSaveHandler)->save($model, $repeaters, $data);
        }
    }

    /**
     * The relationship-backed repeaters declared by the edit-option schema
     * (recursively, through any layouts). These carry hasMany rows rather than
     * scalar columns, so they are filled and synced separately.
     *
     * @return array<int, Repeater>
     */
    protected function getEditOptionRelationshipRepeaters(): array
    {
        return $this->collectRelationshipRepeaters($this->resolveEditOptionSchema());
    }

    /**
     * @param  array<int, mixed>  $components
     * @return array<int, Repeater>
     */
    private function collectRelationshipRepeaters(array $components): array
    {
        $repeaters = [];

        foreach ($components as $component) {
            if ($component instanceof Repeater) {
                if ($component->getRelationship() !== null) {
                    $repeaters[] = $component;
                }

                continue;
            }

            if ($component instanceof LayoutComponent) {
                $repeaters = array_merge($repeaters, $this->collectRelationshipRepeaters($component->getSchema()));
            }
        }

        return $repeaters;
    }

    /**
     * The resolved edit-option schema components (evaluating a Closure schema).
     *
     * @return array<int, mixed>
     */
    protected function resolveEditOptionSchema(): array
    {
        $schema = $this->editOptionSchema instanceof Closure
            ? $this->evaluate($this->editOptionSchema)
            : $this->editOptionSchema;

        return is_array($schema) ? $schema : [];
    }

    /**
     * Field names declared directly by a repeater's item schema (through layouts,
     * but not descending into a further nested repeater).
     *
     * @return array<int, string>
     */
    private function repeaterSubfieldNames(Repeater $repeater): array
    {
        $names = [];

        $walk = static function (array $components) use (&$walk, &$names): void {
            foreach ($components as $component) {
                if ($component instanceof Repeater) {
                    continue;
                }

                if ($component instanceof LayoutComponent) {
                    $walk($component->getSchema());
                } elseif ($component instanceof Component) {
                    $names[] = $component->getName();
                }
            }
        };

        $walk($repeater->getSchema());

        return $names;
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
