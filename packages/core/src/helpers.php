<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Icons\IconManager;

if (! function_exists('icon')) {
    /**
     * Resolve an icon to its cached `<svg>` string through the canonical
     * {@see IconManager}.
     *
     * This is the Htmlable-first render path: PHP produces the markup (the
     * manager's memoised SVG cache), Blade only consumes it via
     * `{!! icon(...) !!}`. Use it in every framework render path — especially
     * per-row / per-item / per-entry loops — instead of:
     *  - `<x-wire::icon>` (a Blade component = one view render per call), or
     *  - a hardcoded inline `<svg>` (breaks theming and the icon-set abstraction).
     *
     * For an Alpine-bound icon (a binding that must live on the `<svg>` root,
     * e.g. `x-show`, `::class`), pass `$attributes` — the manager forwards them
     * onto the root, so a dynamic icon still comes from PHP rather than falling
     * back to the component.
     *
     * @param  string  $name  Icon name, optionally prefixed (e.g. "outline:chevron-right").
     * @param  string  $size  Size utility classes (default "w-4 h-4").
     * @param  string  $class  Additional classes merged after the size.
     * @param  string  $label  Accessible label; empty ⇒ decorative (aria-hidden).
     * @param  array<string, string>  $attributes  Extra root attributes (Alpine bindings, data-*).
     */
    function icon(string $name, string $size = 'w-4 h-4', string $class = '', string $label = '', array $attributes = []): string
    {
        return app(IconManager::class)->render($name, $size, $class, $label, $attributes);
    }
}
