<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * Thrown when a `QueryPlan` asks a `DataSource` for something the source has
 * not declared it can do.
 *
 * The alternative — quietly dropping the aspect — is the failure mode this
 * whole contract exists to prevent. A table backed by an API that cannot sort
 * would return rows in the wrong order and look like it worked, and a filter
 * that silently does nothing shows the user data they believe is filtered.
 * Degrading loudly is the only safe degradation.
 *
 * Takes the aspect as a string rather than a `Capability`, and the plan's
 * suggested `Core\Data\Exceptions\` home was passed over, for the same reason:
 * every exception in this package lives in this one flat directory, and this
 * directory is L0 — it may not import from `Core\` (see ADR 0025). The caller
 * has the enum and passes its value.
 */
final class UnsupportedQueryAspectException extends RuntimeException implements WireException
{
    public static function notDeclared(string $aspect, string $source): self
    {
        return new self(
            "The data source [{$source}] does not declare the [{$aspect}] capability, "
            .'but the query plan asks for it. Either declare it in capabilities() and implement '
            .'it, or stop the table from requesting it (e.g. mark the column non-sortable).'
        );
    }
}
