<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use Illuminate\Contracts\Support\Htmlable;
use Livewire\Drawer\Utils;

use function Livewire\on;

/**
 * Puts `$__livewire` back in scope while an island renders on its own.
 *
 * A full component render shares the component with the *view factory*
 * (`HandleComponents::render()` → `Utils::shareWithViews('__livewire', …)`), so
 * every nested view sees it — including one built from PHP with an explicit data
 * array, which is how this framework renders modals, forms and every other
 * {@see Htmlable} surface (Modal Best Practices
 * rule 5). Shared data is the only channel that reaches those: they do not
 * inherit the including view's variables.
 *
 * A *targeted island* render does not share it. `Component::renderIslandView()`
 * puts `__livewire` into the island body's own data instead, which carries
 * through `@include` but stops at the first `view(...)->render()`. The symptom is
 * an `Undefined variable $__livewire` from whatever the island renders — from
 * `@entangle`, which compiles to `$__livewire->getId()`, or from `@this`.
 *
 * So the island path is given the same shared scope the full path already has,
 * for exactly the duration of that render. Without it, `@island` would be usable
 * only around views that avoid both directives — which is no modal in this
 * framework, and rules out the one place islands pay off most: an action modal
 * that opens without re-rendering the table behind it.
 */
final class IslandViewScope
{
    public static function register(): void
    {
        on('renderIsland', function ($component, $name, $view, $properties) {
            $revertLivewire = Utils::shareWithViews('__livewire', $component);
            $revertInstance = Utils::shareWithViews('_instance', $component); // @deprecated, mirrors the full render

            return function ($html, $replaceHtml) use ($revertLivewire, $revertInstance): void {
                $revertLivewire();
                $revertInstance();
            };
        });
    }
}
