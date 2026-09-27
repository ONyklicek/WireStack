<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Routing\Contracts;

/**
 * A registered class that lives inside a cluster — one section of the admin,
 * with one menu entry, one URL prefix and a way across between its screens.
 *
 *   final class CurrencyResource implements BelongsToCluster, DescribesResource, …
 *   {
 *       public static function cluster(): ?string
 *       {
 *           return Settings::class;
 *       }
 *   }
 *
 * Declared by the member, not by the cluster, for the reason a menu entry names
 * its own `parent()`: the package that ships a settings screen does not own the
 * application's settings section, and could not list itself in it.
 *
 * Here, in L0, because two layers read it and neither may name the other: the
 * menu (`Workspace`, wire-core) takes a member out of the grouped menu, and the
 * router (wire-panels) puts its pages under the cluster's prefix. What a cluster
 * *is* belongs to wire-panels, so this names it only as a class string (ADR 0039).
 */
interface BelongsToCluster
{
    /**
     * The cluster this class belongs to, or null for none.
     *
     * @return class-string|null
     */
    public static function cluster(): ?string;
}
