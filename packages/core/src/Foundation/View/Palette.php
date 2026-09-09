<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use NyonCode\WireCore\Foundation\Concerns\HasColor;

/**
 * The palette, as a class a Blade file may actually call.
 *
 * {@see HasColor}'s resolvers are static, and several views reach for them as
 * `HasColor::getTextColorClasses(…)` — which PHP 8.1 deprecated: *"Calling
 * static trait method … is deprecated, it should only be called on a class using
 * the trait"*. Every one of those call sites is one deprecation notice per
 * render, and the fix is not to give each view a different host class but to
 * name one.
 *
 * It carries no behaviour of its own and must not grow any. A colour rule
 * belongs in `HasColor` where every surface shares it; this is the door, not a
 * room.
 */
final class Palette
{
    use HasColor;
}
