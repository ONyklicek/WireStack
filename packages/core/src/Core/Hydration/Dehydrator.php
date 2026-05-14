<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Hydration;

use Illuminate\Database\Eloquent\Model;

/**
 * Converts a state array back into model attribute mutations.
 */
final class Dehydrator
{
    /**
     * Create a new Dehydrator instance.
     */
    public function __construct(
        private ValueTransformer $transformer,
        private CastResolver $castResolver,
    ) {}

    /**
     * Apply a full state array to a model.
     *
     * Handles both flat attributes and dot-notation nested relation paths.
     *
     * @param  array<string, mixed>  $state
     */
    public function dehydrate(array $state, Model $model): void
    {
        foreach ($state as $attribute => $value) {
            $this->dehydrateAttribute($attribute, $value, $model);
        }
    }

    /**
     * Apply a single attribute value to the model.
     *
     * For dot-notation keys, traverses the relation path and sets
     * the attribute on the related model.
     */
    public function dehydrateAttribute(string $attribute, mixed $value, Model $model): void
    {
        if (str_contains($attribute, '.')) {
            $this->dehydrateRelation($attribute, $value, $model);

            return;
        }

        $cast = $this->castResolver->resolve($model::class, $attribute);

        if ($cast !== null) {
            $value = $this->transformer->reverseTransform($value, $cast);
        }

        $model->setAttribute($attribute, $value);
    }

    /**
     * Dehydrate a nested relation attribute using dot-notation traversal.
     */
    private function dehydrateRelation(string $path, mixed $value, Model $model): void
    {
        $segments = explode('.', $path);
        $attribute = array_pop($segments);
        $current = $model;

        foreach ($segments as $segment) {
            $related = $current->getRelationValue($segment);

            if ($related === null || ! $related instanceof Model) {
                return;
            }

            $current = $related;
        }

        $cast = $this->castResolver->resolve($current::class, $attribute);

        if ($cast !== null) {
            $value = $this->transformer->reverseTransform($value, $cast);
        }

        $current->setAttribute($attribute, $value);
    }
}
