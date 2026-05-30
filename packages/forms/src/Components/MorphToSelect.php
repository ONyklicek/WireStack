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
     * @param  array<int, MorphToSelect\Type>  $types
     */
    public function types(array $types): static
    {
        $this->types = $types;

        return $this;
    }

    public function typeColumnSuffix(string $suffix): static
    {
        $this->typeColumnSuffix = $suffix;

        return $this;
    }

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
