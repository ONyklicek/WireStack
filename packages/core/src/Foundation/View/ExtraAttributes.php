<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\Support\Htmlable;

/**
 * The `extraAttributes()` a component was given, as attribute text for its root tag.
 *
 * Rendered through the `@wireExtraAttributes` directive rather than an
 * `@include`, and the difference is measurable rather than stylistic: an include
 * is a view render, and the surface that needs this most —
 * `field-wrapper-start` — runs once per field of every form. Routing it through
 * a partial moved a text field from three renders to four, which
 * `FormRenderCountTest` measures on purpose. A directive compiles inline and
 * costs nothing, so one owner and the hot path stop being a trade.
 *
 * Building attribute text in PHP is the one thing this does that a Blade loop
 * would otherwise do; it is the same shape Laravel's own `ComponentAttributeBag`
 * uses, and the escaping is the same `e()`. **Markup is still Blade's** — this
 * emits attributes onto a tag somebody else opened, and cannot open one:
 * {@see self::isSafeName()} refuses a name that is not a plain attribute, and
 * every value is escaped.
 */
final class ExtraAttributes implements Htmlable
{
    /**
     * `array-key`, not `string`, and that is the point of the guard in
     * {@see self::toHtml()}: PHP casts a decimal-string key to an int on the way
     * into an array, so `extraAttributes(['0' => 'x'])` arrives here keyed `0`.
     * A name that is not a string is not an attribute name.
     *
     * @param  array<array-key, mixed>  $attributes
     */
    private function __construct(private readonly array $attributes) {}

    /**
     * Read the attributes off a component, or none from anything that has none.
     *
     * A caller may pass a layout element that never had the trait — the schema
     * `Callout` renders the same partial as the forms `Alert` — so a missing
     * getter is an empty list rather than a fatal.
     */
    public static function for(mixed $component): self
    {
        if (! is_object($component) || ! method_exists($component, 'getExtraAttributes')) {
            return new self([]);
        }

        $attributes = $component->getExtraAttributes();

        return new self(is_array($attributes) ? $attributes : []);
    }

    /**
     * The same rendering for a caller holding the array rather than the component.
     *
     * A table column is the one: it stores what an author passed and hands the
     * cell a string, so it reaches this from the other end.
     *
     * @param  array<array-key, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self($attributes);
    }

    public function toHtml(): string
    {
        $html = '';

        foreach ($this->attributes as $name => $value) {
            if (! is_string($name) || ! self::isSafeName($name)) {
                continue;
            }

            $html .= ' '.$name.'="'.e((string) $value).'"';
        }

        return $html;
    }

    /**
     * A plain HTML attribute name, and nothing that could end the tag.
     *
     * Values are escaped, so the only way out of the attribute would be through
     * the *name* — which nothing in this framework generates dynamically, but a
     * component may set from an array an application built. Letters, digits,
     * `-`, `_` and `:` cover `data-*`, `aria-*`, `wire:*` and `x-*`.
     */
    private static function isSafeName(string $name): bool
    {
        return preg_match('/^[A-Za-z_:][A-Za-z0-9_:.-]*$/', $name) === 1;
    }
}
