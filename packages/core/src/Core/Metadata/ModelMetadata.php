<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Metadata;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable metadata about an Eloquent model.
 *
 * Extracted from model instance — never from source code parsing.
 */
final readonly class ModelMetadata
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, string>  $casts
     * @param  array<int, string>  $fillable
     * @param  array<int, string>  $guarded
     * @param  array<int, string>  $relations
     * @param  array<int, string>  $appends
     */
    public function __construct(
        public string $modelClass,
        public string $table,
        public string $primaryKey,
        public string $primaryKeyType,
        public bool $incrementing,
        public bool $usesTimestamps,
        public bool $usesSoftDeletes,
        public array $casts,
        public array $fillable,
        public array $guarded,
        public array $relations,
        public array $appends,
    ) {}

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $relations  Explicit relation names (from user config, not reflection)
     */
    public static function fromModel(string $modelClass, array $relations = []): self
    {
        /** @var Model $instance */
        $instance = new $modelClass;

        return new self(
            modelClass: $modelClass,
            table: $instance->getTable(),
            primaryKey: $instance->getKeyName(),
            primaryKeyType: $instance->getKeyType(),
            incrementing: $instance->getIncrementing(),
            usesTimestamps: $instance->usesTimestamps(),
            usesSoftDeletes: method_exists($instance, 'trashed'),
            casts: $instance->getCasts(),
            fillable: $instance->getFillable(),
            guarded: $instance->getGuarded(),
            relations: $relations,
            appends: $instance->getAppends(),
        );
    }

    public function isFillable(string $attribute): bool
    {
        return in_array($attribute, $this->fillable, true);
    }

    public function hasCast(string $attribute): bool
    {
        return array_key_exists($attribute, $this->casts);
    }

    public function getCast(string $attribute): ?string
    {
        return $this->casts[$attribute] ?? null;
    }

    public function hasRelation(string $name): bool
    {
        return in_array($name, $this->relations, true);
    }
}
