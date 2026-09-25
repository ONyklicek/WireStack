<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Support;

use NyonCode\WireCore\Core\Metadata\ModelMetadata;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WirePanels\Exceptions\ResourcePageException;
use NyonCode\WirePanels\Resources\Contracts\ManagesTrashedRecords;

/**
 * Whether a resource's trashed records are the panel's to show.
 *
 * The one place that asks, so the list and the record pages cannot disagree
 * about it — a list offering *Restore* on a row whose own page answers 404 is
 * the failure this prevents.
 */
final class TrashedRecords
{
    /**
     * @param  class-string|null  $resource
     *
     * @throws ResourcePageException When the resource asks for it over a model
     *                               that does not soft-delete: every trashed
     *                               surface would otherwise fail deep inside a
     *                               query with a method that does not exist.
     */
    public static function managedBy(?string $resource): bool
    {
        if ($resource === null
            || ! is_subclass_of($resource, ManagesTrashedRecords::class)
            || ! is_subclass_of($resource, DescribesResource::class)) {
            return false;
        }

        $model = $resource::modelClass();

        if ($model === null || ! ModelMetadata::fromModel($model)->usesSoftDeletes) {
            throw ResourcePageException::notSoftDeletable($resource, (string) $model);
        }

        return true;
    }
}
