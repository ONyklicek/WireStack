<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Hydration;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolves Laravel cast definitions from Eloquent models.
 */
final class CastResolver
{
    /**
     * Get the cast type for a specific attribute on a model.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function resolve(string $modelClass, string $attribute): ?string
    {
        $casts = $this->resolveAll($modelClass);

        return $casts[$attribute] ?? null;
    }

    /**
     * Get all cast definitions for a model.
     *
     * @param  class-string<Model>  $modelClass
     * @return array<string, string>
     */
    public function resolveAll(string $modelClass): array
    {
        /** @var Model $model */
        $model = new $modelClass;

        return $model->getCasts();
    }

    /**
     * Determine if a model attribute has a cast defined.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function hasCast(string $modelClass, string $attribute): bool
    {
        return $this->resolve($modelClass, $attribute) !== null;
    }
}
