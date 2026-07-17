<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Runtime;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * Thrown when an optimistic-lock check detects that the record was modified by
 * someone else between the time the form was filled and the time it was saved.
 *
 * The save is aborted so the stale write never overwrites the newer data.
 *
 * NOTE: this class predates the `Exceptions/` convention and stays here rather
 * than moving to `NyonCode\WireForms\Exceptions\`. The docs publish this exact
 * FQCN and tell applications to catch it, so relocating it would break real
 * code for tidiness alone. It moves in 2.0; see ADR 0022.
 */
final class StaleModelException extends RuntimeException implements WireException
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
