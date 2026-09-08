<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Illuminate\View\ComponentAttributeBag;
use Illuminate\View\ComponentSlot;

/**
 * A `Foundation\View` component rendered from PHP, without its `<x-*>` tag.
 *
 * Rendering rule 5 says the framework must render with `<x-*>` disabled: the
 * component tags are the *consumer-facing* API — registered so an app can write
 * `<x-wire::button>` in a published view — and the framework's own partials must
 * not depend on that registration, or on Blade's component compiler, to draw
 * their chrome.
 *
 * Three of them did anyway (`<x-wire::breadcrumbs>` in the page header,
 * `<x-wire::button>` in the form actions, `<x-wire::file-thumb>` in the upload
 * preview), and the reason was practical rather than lazy: the alternative was
 * hand-written utility classes, which is how the submit button became the one
 * button in the framework that did not move when the canonical colour and size
 * resolvers did. This is the third option — the same class, the same single
 * view, addressed as an object.
 *
 * Modals reached the same place from the other direction, by making the modal an
 * Htmlable value object over its shell view. These components are Blade
 * components rather than value objects (they take an attribute bag and a slot,
 * which is exactly what a consumer wants from a tag), so what they need is not a
 * parallel object family but the two arguments Blade would have handed them.
 *
 * One view render per call — the same as the tag costs — so this is a rule-5
 * fix, not a performance one. Chrome rendered per row still belongs in a
 * {@see Skeleton}.
 */
final class ComponentRenderer
{
    /**
     * @param  string|Htmlable  $slot  the component's default slot, if it takes one.
     *                                 A plain string is escaped, as Blade would
     *                                 have escaped it between the tags.
     * @param  array<string, mixed>  $attributes  what would have been written on the
     *                                            tag; merged by the view itself
     */
    public static function render(Component $component, string|Htmlable $slot = '', array $attributes = []): string
    {
        // Part of the component contract, and not a detail: Breadcrumbs draws
        // nothing for a trail of one, and a renderer that skips this would put an
        // empty <nav> on every list page.
        if (! $component->shouldRender()) {
            return '';
        }

        $component->withAttributes([]);

        $data = $component->data();
        $data['attributes'] = new ComponentAttributeBag($attributes);
        $data['slot'] = new ComponentSlot(
            $slot instanceof Htmlable ? $slot->toHtml() : e($slot),
        );

        /** @var View $view every Foundation\View component answers render() with one */
        $view = $component->resolveView();

        // Merged on top of the view the component built, so the data it passed
        // itself survives — `crumbs` arrives that way, and is protected precisely
        // so it does not also reach the view as a public method.
        return $view->with($data)->render();
    }
}
