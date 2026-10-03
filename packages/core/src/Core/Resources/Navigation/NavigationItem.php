<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Resources\Navigation;

use Closure;
use NyonCode\WireCore\Foundation\Concerns\HasIcon;
use NyonCode\WireCore\Foundation\Concerns\HasLabel;
use NyonCode\WireCore\Foundation\Concerns\HasSortOrder;
use NyonCode\WireCore\Foundation\Concerns\HasVisibility;
use NyonCode\WireCore\Foundation\Registration\Contracts\HasRegistryKey;
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
 * it sits in, where it sorts within that group, an optional badge, and — when
 * the convention is wrong about it — when the entry counts as the page you are
 * on ({@see activeWhen()}).
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

    /** @var Closure|array<int, string>|null */
    protected Closure|array|null $activeWhen = null;

    /** @var array<int, self>|Closure */
    protected array|Closure $children = [];

    /** @var array<int, self> Entries `Workspace` placed under this one because they named it as their parent. */
    protected array $adoptedChildren = [];

    protected ?string $parent = null;

    protected ?string $key = null;

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

    /**
     * When this entry counts as the one you are on, if the convention is wrong.
     *
     * Nothing needs it in the common case. A registered entry is active on every
     * page of its resource — the key says so — and a hand-written entry is
     * active on the URL it points at. What neither covers is the entry that
     * points at a *section*:
     *
     *   NavigationItem::make('Settings')
     *       ->url(route('settings.general'))
     *       ->activeWhen('settings/*');       // and on every page under it
     *
     * A pattern is matched against the current path **and** the current route
     * name, so `settings/*` and `admin.settings.*` both mean what they look
     * like. A Closure receives the {@see ActiveNavigation} reading and decides:
     *
     *   ->activeWhen(fn (ActiveNavigation $active): bool => $active->page === 'edit')
     *
     * Declaring this **replaces** the convention rather than adding to it. That
     * is the point: the entry that says when it is active is the entry whose
     * author knows something this framework does not, and a rule that still
     * ored in the default would make "never active here" impossible to write.
     *
     * @param  Closure|array<int, string>|string|null  $activeWhen
     */
    public function activeWhen(Closure|array|string|null $activeWhen): self
    {
        $this->activeWhen = is_string($activeWhen) ? [$activeWhen] : $activeWhen;

        return $this;
    }

    /**
     * The entry's own answer, or null when it declared none.
     *
     * Three-valued on purpose. `false` is "I am not active, and I have said so",
     * which must not fall through to the conventions — {@see ActiveNavigation}
     * branches on the difference, and a bool return would have collapsed it.
     *
     * The Closure is resolved here rather than in the reader because resolving
     * closures is this class's job ({@see EvaluatesClosures}); what a *request*
     * matches is the reader's, and the patterns are handed straight to it.
     */
    public function isActiveWhen(ActiveNavigation $active): ?bool
    {
        if ($this->activeWhen === null) {
            return null;
        }

        if ($this->activeWhen instanceof Closure) {
            return (bool) $this->evaluate($this->activeWhen, ['active' => $active]);
        }

        return $active->matchesPatterns($this->activeWhen);
    }

    /**
     * Entries that belong under this one.
     *
     *   NavigationItem::make('Catalogue')
     *       ->icon('outline:squares-2x2')
     *       ->children([
     *           NavigationItem::make('Products')->url(route('products.index')),
     *           NavigationItem::make('Categories')->url(route('categories.index')),
     *       ]);
     *
     * **One level, and that is on purpose.** A child's own children are not read
     * by anything that draws a menu, because a sidebar that nests three deep is
     * a sidebar nobody can hit with a mouse — the third level belongs on the
     * page, as tabs or as a secondary nav. Nesting further is not rejected, it
     * is simply not drawn, so a caller who does it sees it immediately.
     *
     * A Closure is resolved per read for the same reason a badge is: children
     * that depend on what the current user may see must not be decided once, at
     * registration, and remembered for everybody.
     *
     * @param  array<int, self>|Closure  $children
     */
    public function children(array|Closure $children): self
    {
        $this->children = $children;

        return $this;
    }

    /**
     * The entry this one belongs under, named from the child's side.
     *
     *   // CategoryResource
     *   public static function navigation(): NavigationItem
     *   {
     *       return NavigationItem::make()->parent(ProductResource::class);
     *   }
     *
     * {@see children()} is the same menu written from the parent's side, and it
     * needs the parent to know every child and to spell out their URLs. This is
     * for the other case — a resource, a page or a module that wants to sit
     * under an entry it does not own — and needs neither: `Workspace` moves the
     * entry under its parent, where it keeps its own URL, badge and sort.
     *
     * A registered **key** or the class registered under it; the class is
     * reduced to its key, so renaming a key does not orphan the entry.
     *
     * @param  class-string<HasRegistryKey>|string|null  $parent
     */
    public function parent(?string $parent): self
    {
        $this->parent = $parent !== null && is_a($parent, HasRegistryKey::class, true)
            ? $parent::key()
            : $parent;

        return $this;
    }

    /** The registered key of the entry this one belongs under, or null for none. */
    public function getParent(): ?string
    {
        return $this->parent;
    }

    /**
     * The key this entry was registered under — set by `Workspace` on a
     * registered entry.
     *
     * The menu has always keyed its entries by it; the entry carries it as well
     * so a row that ends up *under* another one — where the array key is gone —
     * is still recognised as its resource's row on that resource's edit page.
     *
     * A hand-written child may name one so it can be pinned: pins are kept by
     * key. Use a key no resource is registered under — `settings.server` for a
     * resource's page — because a key equal to a registered one lights the
     * child on every page of that resource.
     */
    public function key(?string $key): self
    {
        $this->key = $key;

        return $this;
    }

    /** The key this entry was registered under, or null for a hand-written child that named none. */
    public function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * A copy carrying the entries that named this one with {@see parent()}.
     *
     * A copy, like `NavigationGroup::withItems()`, and kept apart from
     * {@see children()} rather than merged into it: those may be a Closure,
     * resolved per read, and folding a fixed list into it would either resolve
     * the Closure once for everybody or lose the list.
     *
     * @param  array<int, self>  $children
     */
    public function withAdoptedChildren(array $children): self
    {
        $copy = clone $this;
        $copy->adoptedChildren = array_values($children);

        return $copy;
    }

    /**
     * The visible children, in `sort()` order.
     *
     * Hidden ones are dropped here rather than in the view, so every surface
     * that draws a submenu agrees about what is in it without repeating the
     * rule — the same reason `Workspace` filters entries instead of the sidebar.
     *
     * @return array<int, self>
     */
    public function getChildren(): array
    {
        $children = $this->evaluate($this->children);

        $children = [...(is_array($children) ? array_values($children) : []), ...$this->adoptedChildren];

        $visible = array_values(array_filter(
            $children,
            static fn (mixed $child): bool => $child instanceof self && $child->isVisible(),
        ));

        usort($visible, static fn (self $a, self $b): int => $a->getSort() <=> $b->getSort());

        return $visible;
    }

    /**
     * Whether anything would be drawn under this entry.
     *
     * Asked rather than `count(getChildren())` at every call site, because the
     * children may be a Closure and the answer decides whether a row is a link
     * or a disclosure — a distinction a view should be able to make in one word.
     */
    public function hasChildren(): bool
    {
        return $this->getChildren() !== [];
    }
}
