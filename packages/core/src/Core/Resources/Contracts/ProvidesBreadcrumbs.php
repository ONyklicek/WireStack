<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Resources\Contracts;

use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;

/**
 * A page that can say where it sits.
 *
 * Breadcrumbs are the one piece of navigation a menu cannot supply: the menu
 * knows which entry is active, and nothing knows that an edit page is *inside*
 * the list it came from until the page says so.
 *
 * The crumbs are {@see NavigationItem}s rather than a shape of their own — a
 * crumb is a label and, usually, a URL, which is exactly what that class has
 * carried since the menu needed it. A crumb with no URL is the page you are on,
 * and by convention it is the last one.
 *
 * Opt-in, like every other surface contract here: a page that is not inside
 * anything says so by not implementing this, rather than by returning an empty
 * array from a method it was forced to have.
 */
interface ProvidesBreadcrumbs
{
    /**
     * The trail, outermost first, ending with the current page.
     *
     * @return array<int, NavigationItem>
     */
    public function breadcrumbs(): array;
}
