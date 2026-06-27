<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Hydration;

use Closure;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

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

        return array_merge($model->getCasts(), $this->resolveMethodCasts($model));
    }

    /**
     * Resolve Laravel 11+ style cast definitions on Laravel versions where
     * Eloquent does not merge the casts() method into getCasts().
     *
     * @return array<string, string>
     */
    private function resolveMethodCasts(Model $model): array
    {
        // Eloquent's casts() method only exists on Laravel 11+. Detect it via
        // reflection (which also sees the protected method) so this stays a real
        // runtime guard on Laravel 10, where the method is absent.
        if (! (new ReflectionClass($model))->hasMethod('casts')) {
            return [];
        }

        $resolver = Closure::bind(
            static fn (Model $model): array => $model->casts(),
            null,
            $model::class,
        );

        return array_map(
            static fn (mixed $cast): string => (string) $cast,
            $resolver($model),
        );
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
