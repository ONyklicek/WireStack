<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Thrown when a documentation id names nothing in the corpus.
 *
 * Ids are minted by search-wire-docs, so an unknown one usually means the agent
 * assembled it by hand — hence the reminder of where real ids come from.
 */
final class DocumentNotFoundException extends InvalidArgumentException implements WireException
{
    public static function section(string $id): self
    {
        return new self(
            "Unknown documentation section [{$id}]. Ids come from search-wire-docs; fetch the document "
            .'id (the part before "#") to list its sections.'
        );
    }

    public static function document(string $id): self
    {
        return new self(
            "Unknown document [{$id}]. Use search-wire-docs to find a document id, "
            .'e.g. "docs/table/columns/index.md".'
        );
    }
}
