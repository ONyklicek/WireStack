<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Tours;

use NyonCode\WireCore\Exceptions\TourDefinitionException;
use NyonCode\WireCore\Foundation\View\ElementHook;

/**
 * One stop on a tour: an element to point at, and what to say about it.
 *
 * ## Why a step names a hook and not a selector
 *
 * `TourStep::make('table-search')` takes an {@see ElementHook} name — the same
 * vocabulary `@wireEl()` writes and a consuming application styles by. It does
 * not take a CSS selector, and that restriction is the whole reason a tour can
 * be expected to survive an upgrade.
 *
 * A hook name is public API: `docs/start/theming.md` promises a name may be
 * added and will not be renamed or removed in a minor release, and
 * `npm run hooks:verify` holds the promise by refusing to let the ledger shrink.
 * A class, an id or a structural selector carries no such promise — `.mt-4 > div`
 * is markup the framework is free to change, and a tour written against it would
 * break at a release nobody thought was breaking.
 *
 * It is also deliberately not `data-testid`, though the two carry the same name
 * wherever both are present. {@see ElementHook} states the split: a test id
 * stays free to change for testing reasons, so anything that depends on it in
 * production is depending on something nobody promised to keep.
 *
 * ## Narrowing
 *
 * One hook name can be on many elements at once — every sidebar entry carries
 * `admin-nav-item`. `where()` adds the sibling attribute that picks one out:
 *
 *     TourStep::make('admin-nav-item')->where('resource', 'orders')
 *     // [data-wire="admin-nav-item"][data-resource="orders"]
 *
 * That is the only narrowing the current markup needs, and it stays inside the
 * contracted surface: `data-resource` is written by the same view that writes
 * the hook.
 */
final class TourStep
{
    private ?string $heading = null;

    private ?string $text = null;

    private string $placement = 'bottom';

    /** @var array<string, string> */
    private array $attributes = [];

    private function __construct(private readonly string $anchor) {}

    /**
     * Point a step at an element hook — the name `@wireEl()` writes.
     *
     * The name is validated here rather than at render time because an invalid
     * one produces a selector that matches nothing, and a step whose element is
     * not on the page is *skipped*. A typo would therefore look exactly like a
     * screen that legitimately does not have that element, and would shorten
     * the tour in silence.
     */
    public static function make(string $anchor): self
    {
        if (! ElementHook::isValidName($anchor)) {
            throw TourDefinitionException::malformedStepAnchor($anchor);
        }

        return new self($anchor);
    }

    /** Set the step's title — the bold line at the top of the panel. */
    public function heading(?string $heading): self
    {
        $this->heading = $heading;

        return $this;
    }

    /** Set the step's body — one or two sentences about the element it points at. */
    public function text(?string $text): self
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Set where the panel sits relative to its element (a Floating UI placement).
     *
     * Not validated against a list: the value is handed to Floating UI as it
     * is, and Floating UI owns the vocabulary — `top`, `right`, `bottom`,
     * `left`, each optionally suffixed `-start` or `-end`. A second list here
     * would be one to keep in step with a dependency's. Only an empty value is
     * replaced, by `bottom`, in the browser; anything else is the author's to
     * spell correctly.
     */
    public function placement(string $placement): self
    {
        $this->placement = $placement;

        return $this;
    }

    /**
     * Narrow the step to one element carrying the hook, by a sibling `data-*`
     * attribute — `where('resource', 'orders')` matches `data-resource="orders"`.
     */
    public function where(string $attribute, string $value): self
    {
        if (! ElementHook::isValidName($attribute)) {
            throw TourDefinitionException::malformedStepAttribute($attribute);
        }

        $this->attributes[$attribute] = $value;

        return $this;
    }

    public function getAnchor(): string
    {
        return $this->anchor;
    }

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function getPlacement(): string
    {
        return $this->placement;
    }

    /**
     * The CSS selector the browser resolves this step against.
     *
     * Built from {@see ElementHook::selector()} rather than from a literal, so
     * the attribute name has one owner; the narrowing attributes are appended in
     * the order they were declared.
     *
     * Values are escaped for the quoted selector string. A value is application
     * data — a resource key, a record id — so it can contain a quote or a
     * backslash, and an unescaped one would not merely fail to match: it would
     * end the attribute early and leave the rest of the value as selector
     * syntax.
     */
    public function getSelector(): string
    {
        $selector = ElementHook::selector($this->anchor);

        foreach ($this->attributes as $attribute => $value) {
            $selector .= '[data-'.$attribute.'="'.addcslashes($value, '"\\').'"]';
        }

        return $selector;
    }
}
