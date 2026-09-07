<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The model class behind a stored polymorphic type.
 *
 * A stored type is written from `getMorphClass()`, so it is a class name in one
 * application and a morph alias in the next — `Relation::morphMap()` is how an
 * application keeps class names out of its database. Anything that reads such a
 * column back (an audit trail's `auditable_type`, a mention's
 * `data-mention-type`) has to answer the same question, so it is answered here
 * once rather than in each reader.
 */
final class MorphedModels
{
    /**
     * The class itself, or what the morph map says the alias means. Null when it
     * is neither — a type the application has since renamed or removed.
     *
     * @return class-string<Model>|null
     */
    public static function classFor(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        if (is_a($type, Model::class, true)) {
            /** @var class-string<Model> $type */
            return $type;
        }

        // Widened deliberately: Laravel types this as a model class, but a morph
        // map is application data — it can name a class that no longer extends
        // Model, or no longer exists at all, and that is a null here rather than
        // a fatal in whatever queries it next.
        /** @var string|null $mapped */
        $mapped = Relation::getMorphedModel($type);

        return is_string($mapped) && is_a($mapped, Model::class, true) ? $mapped : null;
    }
}
