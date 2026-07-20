<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

/**
 * MorphTo relationship select — renders two selects: type selector + ID selector.
 *
 * Usage:
 *   MorphToSelect::make('commentable')
 *       ->types([
 *           MorphToSelect\Type::make(Post::class)
 *               ->titleAttribute('title'),
 *           MorphToSelect\Type::make(Video::class)
 *               ->titleAttribute('name'),
 *       ])
 */
class MorphToSelect extends Field
{
    /** @var array<int, MorphToSelect\Type> */
    protected array $types = [];

    protected ?string $typeColumnSuffix = '_type';

    protected ?string $idColumnSuffix = '_id';

    /**
     * Set the polymorphic target types the field can point to.
     *
     * @param  array<int, MorphToSelect\Type>  $types
     */
    public function types(array $types): static
    {
        $this->types = $types;

        return $this;
    }

    /** Set the suffix of the morph type column (default "_type"). */
    public function typeColumnSuffix(string $suffix): static
    {
        $this->typeColumnSuffix = $suffix;

        return $this;
    }

    /** Set the suffix of the morph id column (default "_id"). */
    public function idColumnSuffix(string $suffix): static
    {
        $this->idColumnSuffix = $suffix;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    /**
     * @return array<int, MorphToSelect\Type>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function getTypeStatePath(): string
    {
        return $this->getStatePath().$this->typeColumnSuffix;
    }

    public function getIdStatePath(): string
    {
        return $this->getStatePath().$this->idColumnSuffix;
    }

    /** The morph *type* column name written on the parent (e.g. `commentable_type`). */
    public function getTypeColumn(): string
    {
        return $this->getName().$this->typeColumnSuffix;
    }

    /** The morph *id* column name written on the parent (e.g. `commentable_id`). */
    public function getIdColumn(): string
    {
        return $this->getName().$this->idColumnSuffix;
    }

    /**
     * Get type labels for the type dropdown.
     *
     * @return array<string, string>
     */
    public function getTypeOptions(): array
    {
        $options = [];

        foreach ($this->types as $type) {
            $options[$type->getModelClass()] = $type->getLabel();
        }

        return $options;
    }

    /**
     * Get ID options for a given morph type.
     *
     * @return array<string|int, string>
     */
    public function getIdOptionsForType(string $modelClass): array
    {
        foreach ($this->types as $type) {
            if ($type->getModelClass() === $modelClass) {
                return $type->getOptions();
            }
        }

        return [];
    }

    /**
     * Find a Type configuration by model class.
     */
    public function findType(string $modelClass): ?MorphToSelect\Type
    {
        foreach ($this->types as $type) {
            if ($type->getModelClass() === $modelClass) {
                return $type;
            }
        }

        return null;
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.morph-to-select';
    }
}
