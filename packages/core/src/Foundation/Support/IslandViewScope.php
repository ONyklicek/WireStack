<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\ViewErrorBag;
use Livewire\Drawer\Utils;
use Livewire\Mechanisms\ExtendBlade\ExtendBlade;

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
            // Only what is not already there. Livewire v4.4.3 took this fix
            // upstream for `__livewire` and shares it around the island's own
            // render — but it reverts *before* calling this hook's finisher, so a
            // second share layered on top would hand back the component instead of
            // what was there first, and leave it shared with every view for the
            // rest of the request. Skipping the key that is already ours keeps
            // this working on the versions that share nothing, without fighting
            // the ones that do.
            $reverts = [];

            foreach (['__livewire', '_instance'] as $key) { // `_instance` is deprecated, and mirrors the full render
                if ((app('view')->getShared()[$key] ?? null) === $component) {
                    continue;
                }

                $reverts[] = Utils::shareWithViews($key, $component);
            }

            return function ($html, $replaceHtml) use ($reverts): void {
                foreach (array_reverse($reverts) as $revert) {
                    $revert();
                }
            };
        });
    }

    /**
     * Render something with the component in view scope, as a full render has it.
     *
     * For the render that does NOT go through Livewire's pipeline at all: a
     * component's own view rendered straight from PHP, which is what
     * `AI_CODING_STANDARD.md` rule 3 asks of every `Htmlable` — `{{ $table }}`
     * must produce the table without a helper.
     *
     * Nothing shares `$__livewire` on that path, and `@island` compiles to
     * `if (isset($__livewire)) echo $__livewire->renderIslandDirective(…)`. The
     * guard means a missing component does not throw — it emits **nothing**, so a
     * view whose content sits inside an island renders its chrome and none of its
     * content, silently. Borrowing the scope for the duration restores it.
     *
     * @param  Closure(): string  $render
     */
    public static function within(mixed $component, Closure $render): string
    {
        $revertLivewire = Utils::shareWithViews('__livewire', $component);
        $revertInstance = Utils::shareWithViews('_instance', $component);

        try {
            return $render();
        } finally {
            $revertLivewire();
            $revertInstance();
        }
    }

    /**
     * Render as though Livewire were rendering the component itself.
     *
     * {@see within()} shares the component, which is enough for `@entangle` and
     * `@this`. It is not enough for a view that has to come out *the same as it
     * would inside a full render*, which is what a partial replacing part of one
     * needs. Three things are missing from the narrower scope, and each was a
     * thrown exception or a silent difference:
     *
     *  - **the view error bag.** Livewire shares it from its `render` and
     *    `renderIsland` hooks only, so a view reading `$errors` — every wire-forms
     *    field wrapper does — dies with `Undefined variable $errors`. Sharing the
     *    *component's* bag rather than whatever the web middleware left behind is
     *    also what makes an error added during this request reach the markup.
     *  - **`$this`.** `ExtendedCompilerEngine` binds it only while
     *    `ExtendBlade::isRenderingLivewireComponent()`, so `FileUpload`,
     *    `Repeater` and `Builder` throw `Using $this when not in object context`.
     *  - **morph markers.** Compiled Blade emits its `[if BLOCK]` pairs behind the
     *    same flag. Markup rendered without them cannot pair against markup
     *    rendered with them, so a partial and the region it replaces have to agree.
     *
     * Kept separate from `within()` rather than folded into it, because that
     * third point cuts both ways: `Table::toHtml()` renders `{{ $table }}` outside
     * any Livewire render and switching markers on there would change what a
     * standalone render emits.
     *
     * @param  Closure(): string  $render
     */
    public static function asLivewireRender(mixed $component, Closure $render): string
    {
        $blade = app(ExtendBlade::class);
        $shared = app('view')->getShared()['errors'] ?? null;

        $errors = $shared instanceof ViewErrorBag ? clone $shared : new ViewErrorBag;

        if (is_object($component) && method_exists($component, 'getErrorBag')) {
            $errors->put('default', $component->getErrorBag());
        }

        $revertErrors = Utils::shareWithViews('errors', $errors);
        $blade->startLivewireRendering($component);

        try {
            return self::within($component, $render);
        } finally {
            $blade->endLivewireRendering();
            $revertErrors();
        }
    }
}
