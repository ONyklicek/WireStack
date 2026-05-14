<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Metadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Immutable metadata about an Eloquent relation.
 */
final readonly class RelationMetadata
{
    /**
     * @param  class-string<Model>  $parentModel
     * @param  class-string<Model>|null  $relatedModel  Null for MorphTo (resolved at runtime)
     */
    public function __construct(
        public string $name,
        public string $type,
        public string $parentModel,
        public ?string $relatedModel,
        public ?string $foreignKey,
        public ?string $localKey,
        public ?string $morphType,
        public ?string $pivotTable,
        public bool $isMorph,
        public bool $isToMany,
    ) {}

    /**
     * @param  class-string<Model>  $parentModel
     * @param  Relation<Model, Model, mixed>  $relation
     */
    public static function fromRelation(string $name, string $parentModel, Relation $relation): self
    {
        $type = class_basename($relation);
        $relationClass = get_class($relation);
        $isMorph = is_a($relationClass, MorphOne::class, true)
            || is_a($relationClass, MorphMany::class, true)
            || is_a($relationClass, MorphTo::class, true)
            || is_a($relationClass, MorphToMany::class, true);

        $isToMany = is_a($relationClass, HasMany::class, true)
            || is_a($relationClass, BelongsToMany::class, true)
            || is_a($relationClass, HasManyThrough::class, true)
            || is_a($relationClass, MorphMany::class, true)
            || is_a($relationClass, MorphToMany::class, true);

        $foreignKey = method_exists($relation, 'getForeignKeyName')
            ? $relation->getForeignKeyName()
            : (method_exists($relation, 'getQualifiedForeignKeyName')
                ? last(explode('.', $relation->getQualifiedForeignKeyName()))
                : null);

        $localKey = method_exists($relation, 'getLocalKeyName')
            ? $relation->getLocalKeyName()
            : (method_exists($relation, 'getParentKeyName')
                ? $relation->getParentKeyName()
                : null);

        $morphType = null;
        if (method_exists($relation, 'getMorphType')) {
            $morphType = $relation->getMorphType();
        }

        $pivotTable = null;
        if ($relation instanceof BelongsToMany) {
            $pivotTable = $relation->getTable();
        }

        $relatedModel = $relation instanceof MorphTo
            ? null
            : get_class($relation->getRelated());

        return new self(
            name: $name,
            type: $type,
            parentModel: $parentModel,
            relatedModel: $relatedModel,
            foreignKey: $foreignKey,
            localKey: $localKey,
            morphType: $morphType,
            pivotTable: $pivotTable,
            isMorph: $isMorph,
            isToMany: $isToMany,
        );
    }

    public function isJoinable(): bool
    {
        return ! $this->isMorph
            && ! $this->isToMany
            && $this->relatedModel !== null;
    }

    public function requiresEagerLoad(): bool
    {
        return $this->isMorph || $this->isToMany;
    }
}
