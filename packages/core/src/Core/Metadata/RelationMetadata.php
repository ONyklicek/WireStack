<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Metadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use NyonCode\WireCore\Core\Query\JoinScope;

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
        // "Through" relations (HasOneThrough) reach the far table via an
        // intermediate one, so a single join is not enough. These carry the
        // intermediate table and the two keys that bridge base -> through.
        public ?string $throughTable = null,
        public ?string $firstKey = null,
        public ?string $secondLocalKey = null,
        // How to constrain each joined table so it matches Eloquent's own
        // relation query (global scopes, and for belongsTo/hasOne the constraints
        // declared on the relation method). Null means a plain direct join.
        // `scope` is the far table's; `throughScope` the intermediate's.
        public ?JoinScope $scope = null,
        public ?JoinScope $throughScope = null,
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

        // HasOneThrough is singular, but on Laravel 10 it extends HasManyThrough
        // (Laravel 11 moved both onto a shared HasOneOrManyThrough), so the
        // HasManyThrough check below would wrongly flag it as to-many there.
        // Compute it up front, on the bare class-string, so it stays singular on
        // every version.
        $isSingularThrough = is_a($relationClass, HasOneThrough::class, true);

        $isToMany = ! $isSingularThrough && (
            is_a($relationClass, HasMany::class, true)
            || is_a($relationClass, BelongsToMany::class, true)
            || is_a($relationClass, HasManyThrough::class, true)
            || is_a($relationClass, MorphMany::class, true)
            || is_a($relationClass, MorphToMany::class, true)
        );

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

        $isThrough = $relation instanceof HasOneThrough || $relation instanceof HasManyThrough;

        // A "through" relation joins base -> intermediate -> far. getParent()
        // is the intermediate model here (Eloquent sets the through parent as
        // the query parent); firstKey/secondLocalKey bridge base to intermediate.
        $throughTable = null;
        $firstKey = null;
        $secondLocalKey = null;
        $throughScope = null;
        if ($isThrough) {
            $through = $relation->getParent();
            $throughTable = $through->getTable();
            $firstKey = $relation->getFirstKeyName();
            $secondLocalKey = $relation->getSecondLocalKeyName();
            // The intermediate table has no single relation object to rebuild, so
            // only its global scopes are applied (method constraints on a through
            // relation are not reflected).
            $throughScope = self::globalScopeOnly($through);
        }

        $scope = self::scopeForFarSide($name, $parentModel, $relation, $relatedModel, $isThrough);

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
            throughTable: $throughTable,
            firstKey: $firstKey,
            secondLocalKey: $secondLocalKey,
            scope: $scope,
            throughScope: $throughScope,
        );
    }

    /**
     * Scope for the far (target) table of a joinable relation, or null for a
     * plain join.
     *
     * A direct belongsTo/hasOne is scoped from its own relation query, which
     * carries both global scopes and method constraints (`->where(...)`). A
     * through relation's far table has no rebuildable relation query of its own,
     * so it falls back to global scopes only.
     *
     * @param  class-string<Model>  $parentModel
     * @param  Relation<Model, Model, mixed>  $relation
     * @param  class-string<Model>|null  $relatedModel
     */
    private static function scopeForFarSide(
        string $name,
        string $parentModel,
        Relation $relation,
        ?string $relatedModel,
        bool $isThrough,
    ): ?JoinScope {
        if ($relatedModel === null) {
            return null;
        }

        if ($isThrough) {
            return self::globalScopeOnly($relation->getRelated());
        }

        $method = Str::camel($name);
        $hasGlobalScopes = $relation->getRelated()->getGlobalScopes() !== [];

        if (! $hasGlobalScopes && ! self::relationDeclaresConstraints($parentModel, $method)) {
            return null;
        }

        return new JoinScope(
            model: $relatedModel,
            relationParent: $parentModel,
            relationMethod: $method,
        );
    }

    /**
     * A `model`-shaped scope when the model declares any global scope, else null.
     */
    private static function globalScopeOnly(Model $model): ?JoinScope
    {
        return $model->getGlobalScopes() === [] ? null : new JoinScope(model: $model::class);
    }

    /**
     * Whether the relation method adds its own query constraints (e.g.
     * `->where(...)`). Built without parent constraints so only the method's own
     * `where`s remain; global scopes are applied lazily and not counted here (the
     * caller detects those separately).
     *
     * @param  class-string<Model>  $parentModel
     */
    private static function relationDeclaresConstraints(string $parentModel, string $method): bool
    {
        // The relation was already resolvable (the caller built it to reach here),
        // so rebuilding it is safe; registerRelationChain still guards the walk.
        $relation = Relation::noConstraints(fn () => (new $parentModel)->{$method}());

        return $relation instanceof Relation
            && $relation->getQuery()->getQuery()->wheres !== [];
    }

    public function isJoinable(): bool
    {
        return ! $this->isMorph
            && ! $this->isToMany
            && $this->relatedModel !== null;
    }

    /**
     * A through relation needs an extra join to its intermediate table.
     */
    public function isThrough(): bool
    {
        return $this->throughTable !== null;
    }

    public function requiresEagerLoad(): bool
    {
        return $this->isMorph || $this->isToMany;
    }
}
