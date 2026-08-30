<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Resources\Concerns;

use Illuminate\Support\Str;
use NyonCode\WireTable\Resources\Contracts\DescribesResource;

/**
 * Default {@see DescribesResource}
 * answers derived from the model class, so a resource declares only what
 * differs from them.
 *
 * The derivation is the interesting part, not the saving of four lines. A key
 * and a label taken from two different places drift the moment someone renames
 * one, and the pair is what the registry routes on and what the menu shows — so
 * both come off `modelClass()` here, and a resource that overrides one is
 * visibly choosing to.
 *
 * `App\Models\OrderLine` gives key `order-lines`, label `Order Line`, plural
 * `Order Lines`. Pluralisation is Laravel's, so an irregular noun is right
 * without being spelled out, and a resource whose plural the inflector gets
 * wrong overrides `pluralLabel()` alone.
 */
trait DescribesRecords
{
    public static function key(): string
    {
        return Str::of(static::shortModelName())->kebab()->plural()->value();
    }

    public static function label(): string
    {
        return Str::headline(static::shortModelName());
    }

    public static function pluralLabel(): string
    {
        return Str::plural(static::label());
    }

    /**
     * The model's class name without its namespace — or the resource's own,
     * with a trailing "Resource" dropped, when the resource has no model.
     *
     * The fallback is what keeps a non-Eloquent resource (V2.0 `DataSource`,
     * no model class) from having to spell out all four answers to get a
     * reasonable menu entry.
     */
    protected static function shortModelName(): string
    {
        $model = static::modelClass();

        if ($model !== null) {
            return class_basename($model);
        }

        return Str::of(class_basename(static::class))->beforeLast('Resource')->value()
            ?: class_basename(static::class);
    }
}
