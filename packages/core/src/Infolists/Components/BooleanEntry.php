<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Components;

/**
 * Boolean entry — an {@see IconEntry} in boolean mode by default.
 *
 * First-class ergonomic alias for `IconEntry::make(...)->boolean()`: a truthy
 * state renders the success check icon, a falsy state the danger x icon. The
 * true/false icons and colors remain overridable via the inherited
 * `trueIcon()` / `falseIcon()` / `trueColor()` / `falseColor()` setters.
 */
class BooleanEntry extends IconEntry
{
    protected bool $boolean = true;
}
