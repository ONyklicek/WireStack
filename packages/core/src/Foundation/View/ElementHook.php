<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

/**
 * The stable name an application styles an element by.
 *
 * Rendered by `@wireEl('admin-sidebar')` as `data-wire="admin-sidebar"`, so a
 * consuming application can write CSS against this framework's markup without
 * publishing a view — which is the thing publishing a view is otherwise for, and
 * the thing that then breaks at the next upgrade because a published view is a
 * fork.
 *
 *     [data-wire="admin-sidebar"] { @apply bg-gray-50 dark:bg-gray-950; }
 *
 * `@apply` works on it, dark variants and pseudo-classes included.
 *
 * **An attribute rather than a class, and that is not a preference.** The
 * `plans/theming-and-customisation.md` audit recommended a `wire-*` class, on
 * the reasoning that `@apply` targets classes and the repository already had a
 * few. Measuring it overturned that: 143 elements across 79 views build their
 * class list with `@class([…])`, and a directive that emitted a second `class`
 * attribute beside one would produce `<div class="wire-x" class="p-4 …">` —
 * where **the browser keeps the first and silently drops the rest**. Every
 * Tailwind class on that element would vanish, the raw markup would still
 * contain both, and no server-side test could see it. An attribute cannot
 * collide with anything.
 *
 * **This is not `data-testid`.** That one exists so a test can find an element,
 * and it has to stay free to change for testing reasons; the day styling depends
 * on it, it stops being. The two carry the same name where both are present, on
 * purpose — the vocabulary was worth keeping — but they are separate contracts:
 * a hook name is public API and may not be renamed in a minor release, and a
 * test id may.
 *
 * Names describe the thing, never its appearance: `table-toolbar`, never
 * `table-grey-bar`. A name that describes how something looks has to be renamed
 * the first time it stops looking that way.
 */
final class ElementHook
{
    /**
     * Render one hook name as its attribute, or nothing for an unusable name.
     *
     * Kebab-case, which is the shape all 387 names the framework already carries
     * are written in. A name that is not is dropped rather than escaped into the
     * markup, because a hook nobody can predict the spelling of is not a
     * contract — and a caller that got here with a variable should find out at
     * the first render, not from a stylesheet that quietly matches nothing.
     */
    public static function render(string $name): string
    {
        if (preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $name) !== 1) {
            return '';
        }

        return ' data-wire="'.$name.'"';
    }
}
