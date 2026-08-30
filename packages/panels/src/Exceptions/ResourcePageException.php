<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Exceptions;

use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * A resource page that cannot say what it is showing.
 *
 * Every case is a page left half-declared, and all of them would otherwise
 * render as an empty page — which reads as "nothing here" rather than as a
 * mistake, and is why they throw instead.
 */
final class ResourcePageException extends RuntimeException implements WireException
{
    public static function noSource(string $page, string $surface): self
    {
        return new self(
            "[{$page}] has nothing to render: it declares no \$resource and does not ".
            'build the surface itself. Point $resource at a resource implementing '.
            "[{$surface}], or write the method yourself the way any host component ".
            'does — both are supported.'
        );
    }

    public static function resourceLacksSurface(string $page, string $resource, string $surface): self
    {
        return new self(
            "[{$page}] renders [{$resource}], which does not implement [{$surface}]. ".
            'A resource declares only the surfaces it has; add that contract to it, '.
            'or build the surface on the page.'
        );
    }

    public static function notAResource(string $page, string $resource): self
    {
        return new self(
            "[{$page}] points \$resource at [{$resource}], which does not implement ".
            DescribesResource::class.'.'
        );
    }

    public static function missingRecord(string $page): self
    {
        return new self(
            "[{$page}] was mounted without a record. An edit or view page shows one ".
            'record, so it needs a key: mount it with `[\'record\' => $key]`, or '.
            'override resolveRecord() to find it another way.'
        );
    }

    public static function unresolvableRecord(string $page, string $resource): self
    {
        return new self(
            "[{$page}] could not resolve its record: [{$resource}] declares no model, ".
            'so there is nothing to look a key up against. Give the resource a '.
            'modelClass(), or override resolveRecord() on the page.'
        );
    }
}
