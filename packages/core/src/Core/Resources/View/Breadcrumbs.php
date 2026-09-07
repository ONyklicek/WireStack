<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Resources\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;

/**
 * The trail above a page: `<x-wire::breadcrumbs :items="$breadcrumbs" />`.
 *
 * In `wire-core` rather than in the shell, deliberately. A page knows where it
 * sits and renders its own trail, so an application that draws its own chrome
 * gets breadcrumbs too — and `wire-panels` may render them without depending on
 * `wire-admin`, which is a direction the package graph forbids.
 *
 * In `Core/` rather than beside the other `<x-wire::…>` tags in `Foundation/`,
 * because it names {@see NavigationItem} and that is L1 — the layer test caught
 * the import the moment it was written (ADR 0025). The tag is registered by hand
 * for the same reason the action components are, so the name a user types stays
 * `<x-wire::breadcrumbs>` while the class lives where its dependencies do.
 *
 * An empty trail renders nothing at all. One crumb renders nothing either: a
 * trail of length one is the page saying "you are here", which the heading
 * beneath it already says.
 */
class Breadcrumbs extends Component
{
    /**
     * @param  array<int, mixed>  $items  Crumbs, outermost first. Typed loosely
     *                                    on purpose: this arrives as a Blade attribute, so the boundary is
     *                                    where a stray value shows up, and a page that renders is worth more
     *                                    than a page that fatals over one bad crumb.
     */
    public function __construct(public array $items = []) {}

    /**
     * The crumbs worth drawing.
     *
     * **Protected on purpose.** A public method on a class component is exposed
     * to its view as an `InvokableComponentVariable` under the same name — which
     * shadowed the `crumbs` this passes to `render()`. That object is iterable
     * but not countable, so `@foreach` built a loop with no size and
     * `$loop->last` was **false on the last item, forever**: every crumb linked,
     * every crumb followed by a separator, and no error anywhere. Renaming one of
     * the two is the whole fix, and the invisible half is why it is written down.
     *
     * @return array<int, NavigationItem>
     */
    protected function crumbs(): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (mixed $item): bool => $item instanceof NavigationItem,
        ));
    }

    public function shouldRender(): bool
    {
        return count($this->crumbs()) > 1;
    }

    public function render(): View
    {
        return view('wire-core::navigation.breadcrumbs', [
            'crumbs' => $this->crumbs(),
        ]);
    }
}
