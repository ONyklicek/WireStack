<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Resources\Navigation;

use NyonCode\WireCore\Foundation\Concerns\HasIcon;
use NyonCode\WireCore\Foundation\Concerns\HasLabel;
use NyonCode\WireCore\Foundation\Concerns\HasName;
use NyonCode\WireCore\Foundation\Concerns\HasSortOrder;
use NyonCode\WireCore\Foundation\Concerns\HasVisibility;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * One heading in an application's navigation, and the entries under it.
 *
 * Exists because a group used to be a bare string, and a string is four things
 * at once: the identity a resource points at, the text a heading renders, the
 * position among the other groups, and the answer to whether the group is shown
 * at all. Measured against a rendered menu (V2.6 step 1) each of those was
 * missing something a menu needs:
 *
 *  - **identity separate from text.** `->group(__('nav.billing'))` made the
 *    translation the array key, so the same menu was keyed differently per
 *    locale. The key is now a slug and {@see HasLabel} owns the heading; an
 *    undeclared key still reads well, because the label falls back to
 *    `Str::headline()` of the key.
 *  - **order.** Group order was the order in which the first resource of that
 *    group happened to be registered, so two domain modules could only agree on
 *    which comes first by agreeing on registration order.
 *  - **visibility.** Hiding a group meant repeating one condition on every
 *    resource in it — the same rule written n times, drifting on the n+1st.
 *
 * Built on the canonical concerns for all four (`HasName`, `HasLabel`,
 * `HasIcon`, `HasVisibility`, `HasSortOrder`) rather than on properties of its
 * own, so it is not a second vocabulary beside {@see NavigationItem}.
 *
 *   NavigationGroup::make('billing')
 *       ->label(__('nav.billing'))
 *       ->icon('outline:banknotes')
 *       ->sort(10)
 *       ->visible(fn (): bool => auth()->user()?->can('viewBilling') ?? false);
 *
 * Collapsing is deliberately absent: nothing renders a collapsible menu yet, and
 * the vocabulary for it already exists twice (`Section::collapsible()`, table
 * `collapsibleGroups()`). A third copy would be written before anything could
 * use it; when a consumer appears, that vocabulary gets a canonical owner first.
 */
final class NavigationGroup
{
    use EvaluatesClosures;
    use HasIcon;
    use HasLabel;
    use HasName;
    use HasSortOrder;
    use HasVisibility;

    /** @var array<string, NavigationItem> Keyed by resource key. */
    protected array $items = [];

    public function __construct(string $key)
    {
        $this->name = $key;
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    /** The slug a {@see NavigationItem} points at with `group()`. */
    public function getKey(): string
    {
        return $this->getName();
    }

    /**
     * A copy of this group carrying the entries a menu shows under it.
     *
     * A copy rather than a mutation, because the declared group is a singleton
     * in {@see NavigationGroups}: filling the registered instance would make a
     * second call to `Workspace::navigation()` answer with something different
     * from the first, which is the kind of bug that only appears on the page
     * that renders a menu twice.
     *
     * @param  array<string, NavigationItem>  $items
     */
    public function withItems(array $items): self
    {
        $clone = clone $this;
        $clone->items = $items;

        return $clone;
    }

    /** @return array<string, NavigationItem> Keyed by resource key. */
    public function getItems(): array
    {
        return $this->items;
    }

    public function hasItems(): bool
    {
        return $this->items !== [];
    }
}
