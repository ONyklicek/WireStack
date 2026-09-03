<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Exceptions;

use NyonCode\WirePanels\Resources\Contracts\ProvidesResourcePages;
use RuntimeException;

/**
 * A resource was asked to route pages it does not declare.
 *
 * Loud rather than silent, and only on the explicit path: `Route::wireResource()`
 * names one resource, so being handed one with no pages is a mistake worth
 * saying out loud. `Route::wireResources()` skips such a resource instead —
 * there, having none is the ordinary way an internal resource stays unrouted.
 */
class ResourceRoutingException extends RuntimeException
{
    /**
     * @param  class-string  $resource
     */
    public static function declaresNoPages(string $resource): self
    {
        return new self(
            "[{$resource}] cannot be routed: it does not implement ".
            ProvidesResourcePages::class.', so nothing says which pages render it. '.
            'Declare pages() on it, or register its routes by hand.'
        );
    }
}
