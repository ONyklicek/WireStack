<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Concerns;

use NyonCode\WirePanels\Resources\Contracts\ProvidesRelationManagers;
use NyonCode\WireTable\RelationManagers\RelationManager;

/**
 * The relation managers a page renders beside its record.
 *
 * {@see RelationManager} was already a working owner and nothing about it
 * changes — it is still mounted with `@livewire(PostsRelationManager::class,
 * ['ownerRecord' => $user])` and still works standalone. This only lets a
 * resource *say* which ones belong to it, so an edit or view page can embed them
 * without the application repeating that wiring on every page.
 *
 * A page whose resource declares none renders none, which is why this returns an
 * empty list rather than refusing: unlike a missing table or form, having no
 * related lists is an ordinary thing for a record to be.
 */
trait EmbedsRelationManagers
{
    /**
     * @return array<int, class-string<RelationManager>>
     */
    public function relationManagers(): array
    {
        $resource = static::$resource;

        if ($resource === null || ! is_subclass_of($resource, ProvidesRelationManagers::class)) {
            return [];
        }

        return array_values(array_filter(
            app($resource)->relationManagers(),
            // A class that is not a RelationManager would fail deep inside
            // Livewire's mount with a message about the wrong component; saying
            // nothing here and dropping it would be worse, so it is filtered and
            // the page renders what it can.
            static fn (string $manager): bool => is_subclass_of($manager, RelationManager::class),
        ));
    }
}
