<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Icons;

/**
 * A fully resolved icon: inner SVG markup plus the rendering metadata needed to
 * wrap it in a correct `<svg>` element.
 *
 * Unlike the legacy "path string" model — which assumed every icon used the
 * Heroicons solid format (a `0 0 20 20` viewBox and `fill="currentColor"`) — a
 * ResolvedIcon carries its own viewBox and root attributes. This lets the
 * {@see IconManager} render icon sets of any origin side by side: Heroicons
 * solid (20x20, fill), Heroicons outline / Lucide / Feather (24x24, stroke),
 * brand SVGs with arbitrary viewBoxes, and so on.
 */
final class ResolvedIcon
{
    /**
     * Root `<svg>` attributes that carry styling and are preserved when an icon
     * is parsed from a complete SVG element.
     */
    private const PRESERVED_ATTRIBUTES = [
        'fill',
        'stroke',
        'stroke-width',
        'stroke-linecap',
        'stroke-linejoin',
        'stroke-miterlimit',
        'stroke-dasharray',
    ];

    /**
     * @param  string  $body  Inner SVG markup (paths, groups, …) without the `<svg>` wrapper.
     * @param  string  $viewBox  The SVG viewBox, e.g. `0 0 20 20` or `0 0 24 24`.
     * @param  array<string, string>  $attributes  Root `<svg>` attributes (fill/stroke/…).
     */
    public function __construct(
        public readonly string $body,
        public readonly string $viewBox = '0 0 20 20',
        public readonly array $attributes = ['fill' => 'currentColor'],
    ) {}

    /**
     * Build a ResolvedIcon from arbitrary SVG input.
     *
     * Accepts either a bare fragment (`<path d="…"/>`) or a complete `<svg>…</svg>`
     * element pasted straight from heroicons.com, lucide.dev, a file, etc. When a
     * full element is given, its viewBox and styling attributes (fill/stroke/…)
     * are extracted and preserved, so icons keep rendering correctly regardless of
     * their native format. A bare fragment defaults to the Heroicons solid format
     * (`0 0 20 20`, `fill="currentColor"`).
     */
    public static function fromSvg(string $svg): self
    {
        $svg = trim($svg);

        // Bare fragment: assume the default Heroicons solid format.
        if (stripos($svg, '<svg') === false) {
            return new self($svg);
        }

        // Capture the opening tag's attributes.
        preg_match('#<svg([^>]*)>#is', $svg, $openMatch);
        $openAttributes = $openMatch[1] ?? '';

        // Strip the outer <svg> wrapper to get the inner body.
        $body = preg_replace('#^.*?<svg[^>]*>#is', '', $svg) ?? $svg;
        $body = preg_replace('#</svg>\s*$#is', '', $body) ?? $body;
        $body = trim($body);

        $viewBox = '0 0 20 20';
        if (preg_match('#viewBox\s*=\s*"([^"]*)"#i', $openAttributes, $viewBoxMatch) === 1) {
            $viewBox = trim($viewBoxMatch[1]);
        }

        $attributes = [];
        foreach (self::PRESERVED_ATTRIBUTES as $attribute) {
            // The `\s*=` requirement keeps `stroke` from matching `stroke-width` etc.
            $pattern = '#(?:^|\s)'.preg_quote($attribute, '#').'\s*=\s*"([^"]*)"#i';
            if (preg_match($pattern, $openAttributes, $attributeMatch) === 1) {
                $attributes[$attribute] = $attributeMatch[1];
            }
        }

        if ($attributes === []) {
            $attributes = ['fill' => 'currentColor'];
        }

        return new self($body, $viewBox, $attributes);
    }

    /**
     * Render the complete `<svg>` element.
     *
     * @param  string  $classes  CSS classes for the root element.
     * @param  string  $label  Accessible label. When empty the icon is treated as
     *                         decorative (`aria-hidden`); otherwise it is exposed
     *                         to assistive tech as an image with this label.
     * @param  array<string, string>  $attributes  Extra root attributes appended to the
     *                                             opening tag (Alpine bindings, `data-*`).
     *                                             Lets an Alpine-bound icon be produced in
     *                                             PHP without the `<x-wire::icon>` component.
     */
    public function toSvg(string $classes = '', string $label = '', array $attributes = []): string
    {
        $classes = trim($classes);
        $classAttribute = $classes !== ''
            ? ' class="'.htmlspecialchars($classes, ENT_QUOTES).'"'
            : '';

        $styleAttributes = '';
        foreach ($this->attributes as $name => $value) {
            $styleAttributes .= ' '.$name.'="'.htmlspecialchars($value, ENT_QUOTES).'"';
        }

        $extraAttributes = '';
        foreach ($attributes as $name => $value) {
            $extraAttributes .= ' '.$name.'="'.htmlspecialchars($value, ENT_QUOTES).'"';
        }

        $viewBox = htmlspecialchars($this->viewBox, ENT_QUOTES);

        $accessibility = $label !== ''
            ? ' role="img" aria-label="'.htmlspecialchars($label, ENT_QUOTES).'"'
            : ' aria-hidden="true"';

        return "<svg{$classAttribute}{$styleAttributes} viewBox=\"{$viewBox}\"{$accessibility}{$extraAttributes}>{$this->body}</svg>";
    }
}
