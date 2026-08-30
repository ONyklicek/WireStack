<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Concerns;

use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WirePanels\Exceptions\ResourcePageException;

/**
 * The half of a page that is about *which* resource it shows.
 *
 * Every page asks the same three questions — is a resource declared, is it
 * really a resource, does it have the surface I need — and answering them once
 * per page is how four pages end up disagreeing about what a missing surface
 * means. The surface-specific half stays on each page, because that is the part
 * that genuinely differs.
 */
trait BelongsToResource
{
    /**
     * Optional heading. Each page decides what it falls back to, because a list
     * wants the plural and a form wants the singular.
     */
    protected ?string $title = null;

    /** @return class-string<DescribesResource>|null */
    public static function resourceClass(): ?string
    {
        return static::$resource;
    }

    /**
     * The declared resource, checked.
     *
     * @param  class-string  $surface  The contract this page needs it to implement.
     *
     * @throws ResourcePageException When nothing is declared, it is not a
     *                               resource, or it lacks the surface — each of which would otherwise
     *                               render as an empty page, and empty reads as "nothing here" rather
     *                               than as a mistake.
     */
    protected function requireResource(string $surface): object
    {
        $resource = static::$resource;

        if ($resource === null) {
            throw ResourcePageException::noSource(static::class, $surface);
        }

        if (! in_array(DescribesResource::class, class_implements($resource) ?: [], true)) {
            throw ResourcePageException::notAResource(static::class, $resource);
        }

        if (! is_subclass_of($resource, $surface)) {
            throw ResourcePageException::resourceLacksSurface(static::class, $resource, $surface);
        }

        // Through the container, so a resource may type-hint its own dependencies.
        return app($resource);
    }

    /** The resource's singular label, or null when no resource is declared. */
    protected function resourceLabel(): ?string
    {
        $resource = static::$resource;

        return $resource !== null ? $resource::label() : null;
    }
}
