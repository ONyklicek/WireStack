<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Hydration;

use Illuminate\Database\Eloquent\Model;

/**
 * Converts an Eloquent model into a flat state array.
 */
final class Hydrator
{
    /**
     * Create a new Hydrator instance.
     */
    public function __construct(
        private ValueTransformer $transformer,
        private CastResolver $castResolver,
    ) {}

    /**
     * Hydrate a model into a flat state array.
     *
     * If no attributes are specified, all model attributes are used.
     * Dot-notation keys are used for nested relation attributes.
     *
     * @param  array<int, string>  $attributes
     * @return array<string, mixed>
     */
    public function hydrate(Model $model, array $attributes = []): array
    {
        if ($attributes === []) {
            $attributes = array_keys($model->getAttributes());
        }

        $state = [];

        foreach ($attributes as $attribute) {
            if (str_contains($attribute, '.')) {
                $state[$attribute] = $this->hydrateRelation($model, $attribute);
            } else {
                $state[$attribute] = $this->hydrateAttribute($model, $attribute);
            }
        }

        return $state;
    }

    /**
     * Hydrate a single attribute value from the model.
     */
    private function hydrateAttribute(Model $model, string $attribute): mixed
    {
        $value = $model->getAttribute($attribute);
        $cast = $this->castResolver->resolve($model::class, $attribute);

        if ($cast !== null) {
            return $this->transformer->transform($value, $cast);
        }

        return $value;
    }

    /**
     * Hydrate a nested relation attribute using dot-notation traversal.
     */
    private function hydrateRelation(Model $model, string $path): mixed
    {
        $segments = explode('.', $path);
        $attribute = array_pop($segments);
        $current = $model;

        foreach ($segments as $segment) {
            $related = $current->getRelationValue($segment);

            if ($related === null) {
                return null;
            }

            if (! $related instanceof Model) {
                return null;
            }

            $current = $related;
        }

        return $this->hydrateAttribute($current, $attribute);
    }
}
