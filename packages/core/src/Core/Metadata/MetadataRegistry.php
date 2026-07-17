<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Metadata;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Exceptions\ModelNotRegisteredException;

/**
 * Central registry for model metadata.
 *
 * Resolves and caches metadata per model class.
 */
final class MetadataRegistry
{
    /** @var array<class-string<Model>, ModelMetadata> */
    private array $models = [];

    /** @var array<class-string<Model>, array<string, RelationMetadata>> */
    private array $relations = [];

    /** @var array<class-string<Model>, array<string, ColumnMetadata>> */
    private array $columns = [];

    /** @var array<class-string<Model>, array<string, AccessorMetadata>> */
    private array $accessors = [];

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $relations  Explicit relation names
     */
    public function registerModel(string $modelClass, array $relations = []): self
    {
        $this->models[$modelClass] = ModelMetadata::fromModel($modelClass, $relations);

        return $this;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function registerModelMetadata(string $modelClass, ModelMetadata $metadata): self
    {
        $this->models[$modelClass] = $metadata;

        return $this;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function registerRelation(string $modelClass, RelationMetadata $relation): self
    {
        $this->relations[$modelClass][$relation->name] = $relation;

        return $this;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function registerColumn(string $modelClass, ColumnMetadata $column): self
    {
        $this->columns[$modelClass][$column->name] = $column;

        return $this;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function registerAccessor(string $modelClass, AccessorMetadata $accessor): self
    {
        $this->accessors[$modelClass][$accessor->name] = $accessor;

        return $this;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function getModelMetadata(string $modelClass): ModelMetadata
    {
        return $this->models[$modelClass]
            ?? throw ModelNotRegisteredException::make($modelClass);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function hasModel(string $modelClass): bool
    {
        return isset($this->models[$modelClass]);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function getRelation(string $modelClass, string $name): ?RelationMetadata
    {
        return $this->relations[$modelClass][$name] ?? null;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<string, RelationMetadata>
     */
    public function getRelations(string $modelClass): array
    {
        return $this->relations[$modelClass] ?? [];
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function getColumn(string $modelClass, string $name): ?ColumnMetadata
    {
        return $this->columns[$modelClass][$name] ?? null;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<string, ColumnMetadata>
     */
    public function getColumns(string $modelClass): array
    {
        return $this->columns[$modelClass] ?? [];
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function getAccessor(string $modelClass, string $name): ?AccessorMetadata
    {
        return $this->accessors[$modelClass][$name] ?? null;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<string, AccessorMetadata>
     */
    public function getAccessors(string $modelClass): array
    {
        return $this->accessors[$modelClass] ?? [];
    }

    /**
     * Whether any column or accessor metadata has been registered at all.
     *
     * Capability auto-resolution walks every table column against this
     * metadata; when none exists the walk can be skipped entirely.
     */
    public function hasAttributeMetadata(): bool
    {
        return $this->columns !== [] || $this->accessors !== [];
    }

    public function flush(): void
    {
        $this->models = [];
        $this->relations = [];
        $this->columns = [];
        $this->accessors = [];
    }
}
