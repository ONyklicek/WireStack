<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Resources\Contracts;

/**
 * Somewhere a menu's entries can come from.
 *
 * A menu was resources and nothing else until V2.6 step 3, when a dashboard
 * needed to appear in one. That could not be done by teaching `Workspace` about
 * dashboards: `Workspace` lives in `Core/` (L1) and `Widgets/` is L2, so the
 * import would be a layer violation `ModuleLayersTest` fails on — and it would
 * have to be repeated for the next thing that wants a menu entry.
 *
 * So the direction is inverted. `Workspace` knows only this contract and the
 * classes it hands back; whoever registers those classes decides what they are.
 * A registry implements it, a package's own registry can implement it, and
 * nothing above L1 has to be visible from inside L1 for a menu to list it.
 *
 * The classes themselves are still filtered by
 * {@see ProvidesNavigation} — being registered
 * somewhere does not put a class in a menu, declaring an entry does.
 */
interface NavigationSource
{
    /**
     * The classes this source offers to a menu, keyed by the key their entries
     * are addressed by.
     *
     * Keys are unique across *all* sources: `Workspace` refuses a collision
     * rather than letting one entry take another's place, because a menu that
     * quietly lost a row is the kind of thing nobody notices until the row was
     * the one they needed.
     *
     * @return array<string, class-string>
     */
    public function navigableClasses(): array;
}
