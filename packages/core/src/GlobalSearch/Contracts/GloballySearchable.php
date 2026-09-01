<?php

declare(strict_types=1);

namespace NyonCode\WireCore\GlobalSearch\Contracts;

use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\GlobalSearch\GlobalSearchResult;

/**
 * A resource that the command palette may search.
 *
 * Opt-in, and separate from {@see DescribesResource}
 * for the reason every surface is: a resource that should not be searchable —
 * an audit log, a join table given a resource for routing — says so by not
 * implementing this, rather than by returning an empty array from a method it
 * was forced to have.
 *
 * Static, like identity and navigation: the palette asks every registered
 * resource what it can search before anything is instantiated, and a query that
 * had to construct fifty resources to ask fifty questions would be the reason
 * nobody turns the palette on.
 */
interface GloballySearchable
{
    /**
     * Model attributes the palette matches a term against.
     *
     * Plain column names on the resource's own model. Relations are not walked:
     * a palette is a fast first guess, and a join per resource per keystroke is
     * how it stops being one. A resource that needs more overrides
     * {@see globalSearchQuery()}.
     *
     * @return array<int, string>
     */
    public static function globallySearchableAttributes(): array;

    /**
     * Turn one matched record into the row a user sees.
     *
     * @param  object  $record  An instance of this resource's model.
     */
    public static function toGlobalSearchResult(object $record): GlobalSearchResult;
}
