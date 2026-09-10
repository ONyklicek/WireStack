<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin;

use Closure;

/**
 * A named position in the markup an application can put something into.
 *
 * The last of the three answers to "make it look different without publishing a
 * view". Tokens move a value everywhere; `@wireEl` hooks restyle an element that
 * is already there; this one is for the element that is *not* — a badge after a
 * page title, a note under the menu, a button beside a table's search box.
 *
 *     use NyonCode\WireCore\Core\Plugin\RenderHook;
 *
 *     RenderHook::add('panels.page.header.end', fn () => view('badges.beta'));
 *     RenderHook::add('table.toolbar.end', fn (array $scope) => view('export', $scope));
 *
 * and in a view, `@wireRenderHook('panels.page.header.end', ['page' => $page])`
 * — written inline here rather than as its own line, because a docblock line
 * that opens with `@` is a tag, and a tag whose value is PHP is one no parser
 * can read.
 *
 * Registration is {@see PluginManager}'s, not a second registry: priorities and
 * the `for:` scoping that decides which resource a callback applies to are the
 * same ones every lifecycle hook already uses, and a plugin registering both
 * kinds does it one way. What is new is the dispatcher —
 * {@see PluginManager::runRenderHook()} — which collects what callbacks return
 * instead of passing a payload along.
 *
 * **Names are public API and grow by consumer.** ADR 0030 §6 governs this and
 * the rule holds here: a position arrives because something needs it, never for
 * symmetry with a position that already exists. Four is not a starter set to be
 * completed — it is what had a reason on the day.
 *
 * Names read outside-in: `panels.page.header.end` is the panels package, its
 * page chrome, the header, at the end. `end` and `start` are positions inside an
 * element; `before` and `after` would be positions outside one, and no name uses
 * them yet because nothing has needed one.
 */
final class RenderHook
{
    /**
     * The positions this framework ships, and what each one is for.
     *
     * A list rather than an enum because an application may name its own — a
     * package built on this one can dispatch a position of its own and nothing
     * here has to know. What the list buys is a place to look, and the gate that
     * keeps a documented name honest.
     *
     * @var array<string, string>
     */
    public const POSITIONS = [
        'admin.topbar.end' => 'After the last control in the shell\'s top bar.',
        'admin.sidebar.end' => 'Below the menu, above the bottom of the sidebar.',
        'panels.page.header.end' => 'After a page\'s title and description.',
        'table.toolbar.end' => 'After the search box and filters, before the actions.',
    ];

    /**
     * Register something to render at a position.
     *
     * The callback receives what the position knows about itself and returns a
     * `View` or `Htmlable` to render, a string to render as text, or nothing at
     * all. See {@see PluginManager::runRenderHook()} for why a bare string is
     * escaped rather than trusted.
     *
     * `for:` narrows a callback to one resource, exactly as it does for a
     * lifecycle hook.
     */
    public static function add(string $name, Closure $render, int $priority = 0, ?string $for = null): void
    {
        app(PluginManager::class)->hook($name, $render, $priority, $for);
    }
}
