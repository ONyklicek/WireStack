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

    /**
     * @param  string  $expected  The contract or base class the page needed. Defaults to
     *                            the resource contract; a dashboard page names its own,
     *                            because a message that says DescribesResource to someone
     *                            who pointed at the wrong dashboard sends them looking in
     *                            the wrong place.
     */
    public static function notAResource(string $page, string $resource, string $expected = DescribesResource::class): self
    {
        return new self(
            "[{$page}] points \$resource at [{$resource}], which does not implement ".
            $expected.'.'
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

    /**
     * A page that must write, holding a record it cannot write through.
     *
     * The form's save lifecycle is Eloquent — relationship repeaters, optimistic
     * locking, `$model->save()` — so a record that does not unwrap to a `Model`
     * cannot be bound to it. Refused here, at the point the page composes its
     * form, rather than allowed through to fail inside the save with a message
     * about a method on null.
     *
     * The remedy is the page's own `form()`: bind nothing, and give the form a
     * command with `Form::using()`, which is the write seam a non-Eloquent
     * source is meant to arrive through.
     */
    public static function recordIsNotEloquent(string $page, string $record): self
    {
        return new self(
            "[{$page}] resolved a record of type [{$record}], which does not unwrap to an ".
            'Eloquent model, and a form cannot be bound to it: saving is Eloquent all the '.
            'way down. Override form() on the page, leave the model unbound and give the '.
            'form its own command with Form::using().'
        );
    }
}
