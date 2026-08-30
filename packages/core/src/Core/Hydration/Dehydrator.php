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
     * Sets attributes; it never persists. Saving the model is the caller's job —
     * see {@see dehydrateAttribute()} for what that means for a dot-notation key.
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
     * For a dot-notation key, walks the path over **already-loaded** relations and
     * sets the attribute on the related model — it does not load, create or save
     * anything, so a caller that saves only the root model drops that write. An
     * unloaded or non-model segment ends the walk and the value is discarded.
     *
     * The only caller in this repository, `SaveHandler::persist()`, cannot reach
     * that branch: a dotted field name arrives from Livewire already nested
     * (`company.name` → `['company' => ['name' => …]]`), so the key it sees has no
     * dot in it. Writing back through a relation from a form has its own owner and
     * its own documented matrix — `BelongsToSelect`, `docs/forms/fields/belongs-to-select.md`.
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
     *
     * Sets only — the related model is left dirty and unsaved. See
     * {@see dehydrateAttribute()}.
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
