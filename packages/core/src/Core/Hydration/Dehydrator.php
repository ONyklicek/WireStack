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
     * **Sets attributes; it never persists.** Saving is the caller's job, which is
     * what lets a caller apply state, run its own hooks and then decide whether
     * the write happens at all.
     *
     * Deliberately flat: a key is an attribute name on *this* model. Writing back
     * through a relation is not this class's job and has a real owner with a
     * documented matrix — `BelongsToSelect`, `docs/forms/fields/belongs-to-select.md`.
     * A dot-notation branch used to live here, walking already-loaded relations
     * and setting attributes on the related model; it was removed 2026-09-02
     * because nothing could reach it — a dotted field name arrives from Livewire
     * already nested (`company.name` → `['company' => ['name' => …]]`), so no key
     * the only caller passes ever contained a dot — and because what it did was
     * wrong for anyone who did reach it: it left the related model dirty and
     * unsaved, so a caller saving only the root silently dropped the write.
     *
     * @param  array<string, mixed>  $state
     */
    public function dehydrate(array $state, Model $model): void
    {
        foreach ($state as $attribute => $value) {
            $cast = $this->castResolver->resolve($model::class, $attribute);

            if ($cast !== null) {
                $value = $this->transformer->reverseTransform($value, $cast);
            }

            $model->setAttribute($attribute, $value);
        }
    }
}
