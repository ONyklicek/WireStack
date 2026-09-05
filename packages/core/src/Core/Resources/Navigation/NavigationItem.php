<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Resources\Navigation;

use Closure;
use NyonCode\WireCore\Foundation\Concerns\HasIcon;
use NyonCode\WireCore\Foundation\Concerns\HasLabel;
use NyonCode\WireCore\Foundation\Concerns\HasSortOrder;
use NyonCode\WireCore\Foundation\Concerns\HasVisibility;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * One entry in an application's navigation.
 *
 * Built on the canonical Foundation concerns rather than on properties of its
 * own: `HasLabel`, `HasIcon` and `HasVisibility` already own those words for
 * every component in the framework, so a menu entry that re-declared them would
 * be a second vocabulary for the same three questions — and the one that drifts,
 * because nothing renders it beside the others.
 *
 * What it adds is only what a *menu* needs and a component does not: which group
 * it sits in, where it sorts within that group, and an optional badge.
 *
 *   NavigationItem::make('Orders')
 *       ->icon('outline:shopping-cart')
 *       ->group('Sales')
 *       ->sort(10)
 *       ->badge(fn () => Order::whereNull('shipped_at')->count());
 *
 * ## Where it points
 *
 * This used to say "deliberately not a route", and the reason behind that is
 * unchanged: a *registry* that held URLs would be a panel, and this layer is not
 * one. What changed in ADR 0026 is who fills the URL in. Nothing declares one
 * here unless it wants to — `Workspace` asks {@see ResolvesPageUrls} for the
 * key's page and fills what it gets, which is `null` in an application that
 * routes nothing and stays null for a resource that declares no pages.
 *
 * So the entry still names itself and still declares no route. It simply stops
 * making every application write the key→URL map by hand, which is what the
 * absence actually cost — this repository's own workbench wrote three of them.
 *
 *   ->url('https://status.example.com')   // an entry that is not a page at all
 */
final class NavigationItem
{
    use EvaluatesClosures;
    use HasIcon;
    use HasLabel;
    use HasSortOrder;
    use HasVisibility;

    protected string|Closure|null $group = null;

    protected string|Closure|null $url = null;

    protected mixed $badge = null;

    protected string|Closure|null $badgeColor = null;

    public function __construct(string|Closure|null $label = null)
    {
        $this->label = $label;
    }

    public static function make(string|Closure|null $label = null): self
    {
        return new self($label);
    }

    /**
     * The entry's own text.
     *
     * Overridden for one reason: {@see HasLabel::getLabel()} falls back to
     * `Str::headline($this->getName())`, which assumes the using class is a
     * named component — a column, a field. A menu entry has no name; the label
     * *is* its identity. So the property, the setter and the closure evaluation
     * all come from the concern and only the fallback is dropped, rather than
     * declaring a second `label()` vocabulary beside the canonical one.
     */
    public function getLabel(): ?string
    {
        $label = $this->evaluate($this->label);

        return is_string($label) ? $label : null;
    }

    /**
     * The group this entry sits under, by key, or null for the top level.
     *
     * A key, not a heading: {@see NavigationGroup} owns what the heading says,
     * so a translated menu does not end up keyed by its own translation.
     * An undeclared key still groups — it simply carries no icon, order or
     * visibility of its own.
     */
    public function group(string|Closure|null $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function getGroup(): ?string
    {
        $value = $this->evaluate($this->group);

        return is_string($value) ? $value : null;
    }

    /**
     * Where this entry goes, when it is not the registered key's own page.
     *
     * An external link, a page outside the convention, or a shell that renders
     * its own URL scheme beside the menu. What is set here wins: the fallback
     * only fills an entry that named nowhere.
     */
    public function url(string|Closure|null $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getUrl(): ?string
    {
        $value = $this->evaluate($this->url);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * A count or short string shown beside the label.
     *
     * A Closure is resolved per read, not stored: a badge that says how many
     * orders are unshipped is wrong the moment it is cached, and caching it is
     * the mistake this signature is shaped to prevent.
     */
    public function badge(mixed $badge, string|Closure|null $color = null): self
    {
        $this->badge = $badge;

        if ($color !== null) {
            $this->badgeColor = $color;
        }

        return $this;
    }

    public function getBadge(): ?string
    {
        $value = $this->evaluate($this->badge);

        return $value === null || $value === '' ? null : (string) $value;
    }

    public function getBadgeColor(): ?string
    {
        $value = $this->evaluate($this->badgeColor);

        return is_string($value) ? $value : null;
    }
}
