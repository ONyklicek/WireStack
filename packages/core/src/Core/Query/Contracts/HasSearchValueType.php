<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Contracts;

use NyonCode\WireCore\Core\Query\Search\SearchValueType;

/**
 * A component that knows what kind of value its column holds.
 *
 * The planner infers this from the model's casts and the registered database
 * type, which covers most columns. This contract is the override for the rest:
 * an uncast `decimal` column, a date stored as a string, an SQL expression the
 * model knows nothing about — cases where only the owner can say that `>100`
 * is a sensible thing to ask.
 *
 * Returning null means "no opinion", leaving the inference in place.
 */
interface HasSearchValueType
{
    public function getSearchValueType(): ?SearchValueType;
}
