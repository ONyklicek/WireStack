<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Runtime;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when an optimistic-lock check detects that the record was modified by
 * someone else between the time the form was filled and the time it was saved.
 *
 * The save is aborted so the stale write never overwrites the newer data.
 */
final class StaleModelException extends RuntimeException
{
    public function __construct(
        public readonly Model $model,
        public readonly string $lockColumn,
    ) {
        parent::__construct(sprintf(
            'Optimistic lock failed: %s#%s was modified concurrently (column "%s").',
            $model::class,
            (string) $model->getKey(),
            $lockColumn,
        ));
    }
}
