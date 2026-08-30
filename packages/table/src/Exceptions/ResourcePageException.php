<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use NyonCode\WireTable\Resources\Contracts\DescribesResource;
use NyonCode\WireTable\Resources\Contracts\ProvidesResourceTable;
use RuntimeException;

/**
 * A resource page that cannot say what it is showing.
 *
 * Every case here is a page left half-declared, and all of them would otherwise
 * render as an empty table — which reads as "no records" rather than as a
 * mistake, and is why they throw instead.
 */
final class ResourcePageException extends RuntimeException implements WireException
{
    public static function noTableSource(string $page): self
    {
        return new self(
            "[{$page}] has no table to show: it declares no \$resource and does not ".
            'override table(). Point $resource at a resource implementing '.
            ProvidesResourceTable::class.', or write table() the way any WithTable '.
            'component does — both are supported.'
        );
    }

    public static function resourceCannotList(string $page, string $resource): self
    {
        return new self(
            "[{$page}] lists [{$resource}], which does not implement ".
            ProvidesResourceTable::class.'. A resource declares only the surfaces it '.
            'has; add that contract to it, or override table() on the page.'
        );
    }

    public static function notAResource(string $page, string $resource): self
    {
        return new self(
            "[{$page}] points \$resource at [{$resource}], which does not implement ".
            DescribesResource::class.'.'
        );
    }
}
