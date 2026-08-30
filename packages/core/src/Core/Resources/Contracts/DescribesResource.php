<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Resources\Contracts;

/**
 * What a resource *is*: the entity it owns and the words used for it.
 *
 * The one contract every resource implements, and the only one the registry
 * reads — listing resources, routing a model to its owner and building a menu
 * must not require composing a table or a form, which is why identity is a
 * capability of its own rather than four more methods on a surface interface.
 *
 * Static on purpose (ADR 0020 Q1, locked to a hybrid): a registry answers "which
 * resource owns App\Models\Order" and a menu asks for a label before anything is
 * instantiated, so metadata cannot require an instance.
 *
 * The surfaces are the opposite — instance methods, because they compose a
 * builder the caller owns — and they live in the package that owns the type each
 * one names, which is why they are not here: `ProvidesResourceTable` in
 * wire-table, `ProvidesResourceForm` in wire-forms, `ProvidesResourceInfolist`
 * beside the Infolists surface. This contract names none of them, so a resource
 * that only has a form never pulls in a table package to declare its identity.
 */
interface DescribesResource
{
    /**
     * Stable identifier, unique within a registry.
     *
     * Used as the config key, the route segment and the introspection handle, so
     * it must not change with a label or a namespace move.
     */
    public static function key(): string;

    /**
     * The Eloquent model this resource owns, or null when it is backed by a
     * non-Eloquent source.
     *
     * Nullable because V2.0 made the table run over a `DataSource` with neither
     * a model nor a builder; a resource over one of those is registered and
     * routed like any other, it just cannot be found by model.
     *
     * @return class-string|null
     */
    public static function modelClass(): ?string;

    /** Singular human name, e.g. "Order". */
    public static function label(): string;

    /** Plural human name, e.g. "Orders". */
    public static function pluralLabel(): string;
}
