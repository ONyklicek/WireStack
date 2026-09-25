<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Contracts;

use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;

/**
 * A resource whose records belong to one record of another — an order's lines,
 * a project's tasks.
 *
 *   final class OrderLineResource implements DescribesResource, NestedResource, ProvidesPages, …
 *   {
 *       public static function parentResource(): string { return OrderResource::class; }
 *       public static function parentRelationship(): string { return 'lines'; }
 *   }
 *
 * What that changes, and nothing else changes it:
 *
 * - its pages are routed under the parent's record —
 *   `orders/{parent}/order-lines`, `…/create`, `…/{record}/edit`;
 * - the list shows the parent's lines only, a record page 404s on a line of
 *   another order, and a create page files the new line under the parent;
 * - the trail leads through the parent — *Orders › Order 17 › Order lines* —
 *   and the parent's record pages gain a tab to the list.
 *
 * One level: a nested resource's parent is not itself nested, because a route
 * carries one `{parent}`. The relationship is the parent model's method, and
 * creating through it needs one that makes a child with its key — `hasMany`,
 * `morphMany` and their one-to-one kin.
 */
interface NestedResource
{
    /** @return class-string<DescribesResource> */
    public static function parentResource(): string;

    /** The relationship on the parent's model that holds these records. */
    public static function parentRelationship(): string;
}
