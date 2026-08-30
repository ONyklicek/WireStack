<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Resources\Contracts;

use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;

/**
 * A resource that puts itself in a menu.
 *
 * Its own capability, and static for the same reason identity is: a menu is
 * built from every registered resource at once, and instantiating each one to
 * ask what it is called would compose a table and a form per entry to render a
 * sidebar.
 *
 * A resource that does not implement this is still registered and routable — it
 * simply does not appear in navigation, which is what an internal or nested
 * resource wants.
 */
interface ProvidesNavigation
{
    public static function navigation(): NavigationItem;
}
