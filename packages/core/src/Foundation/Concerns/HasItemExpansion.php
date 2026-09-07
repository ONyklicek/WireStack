<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Enums\ItemExpansion;

/**
 * {@see CanBeCollapsed}, asked of a *list*: which items start open.
 *
 * A host composing both gets one vocabulary with two altitudes. `collapsible()`
 * still says whether folding is offered at all and `collapsed()` still means
 * "start folded", but a list can also start with only its first or only its last
 * item open — the two shapes a boolean cannot spell and that every long Repeater
 * wants. See {@see ItemExpansion} for why those are cases rather than a closure.
 *
 * The three setters imply `collapsible()` for the same reason `collapsed()` does:
 * a list that opens one row and offers no way to open the rest is not a feature.
 * `expandAll()` is the exception — it *is* the unfolded default, so it asserts
 * nothing about whether folding is offered.
 *
 * Reading is deliberately a two-step: {@see getItemExpansion()} answers with the
 * policy, and the policy answers per item. Nothing here knows how many items
 * there are; the view does.
 *
 * It composes {@see CanBeCollapsed} rather than sitting beside it so that a host
 * gets the whole vocabulary from one `use`, and so `collapsed()` can be the one
 * thing it has to override — see below. Use this *instead of* `CanBeCollapsed`,
 * never alongside it: a host doing both would bind the un-overridden
 * `collapsed()` and silently lose the reset.
 */
trait HasItemExpansion
{
    use CanBeCollapsed {
        collapsed as collapsedByDeclaration;
    }

    /**
     * Null means "nobody said", which is not the same as `All`: an unset policy
     * defers to {@see CanBeCollapsed::isCollapsed()}, so a host that only ever
     * called `collapsed()` keeps behaving exactly as it did before this existed.
     */
    protected ?ItemExpansion $itemExpansion = null;

    /**
     * Start folded — inherited meaning, plus dropping any positional policy.
     *
     * Without the reset, `->expandFirst()->collapsed()` would keep opening the
     * first row: the explicit policy is consulted before `isCollapsed()`. These
     * are two spellings of one setting, so the last call has to win, in both
     * directions.
     */
    public function collapsed(bool|Closure $condition = true): static
    {
        $this->itemExpansion = null;

        return $this->collapsedByDeclaration($condition);
    }

    /** Start with every item open. */
    public function expandAll(): static
    {
        $this->itemExpansion = ItemExpansion::All;

        return $this;
    }

    /** Start with only the first item open — implies {@see CanBeCollapsed::collapsible()}. */
    public function expandFirst(): static
    {
        $this->itemExpansion = ItemExpansion::First;
        $this->collapsible();

        return $this;
    }

    /** Start with only the last item open — implies {@see CanBeCollapsed::collapsible()}. */
    public function expandLast(): static
    {
        $this->itemExpansion = ItemExpansion::Last;
        $this->collapsible();

        return $this;
    }

    /** Start with every item folded — the list-shaped spelling of `collapsed()`. */
    public function collapseAll(): static
    {
        $this->itemExpansion = ItemExpansion::None;
        $this->collapsible();

        return $this;
    }

    /**
     * The policy in force, falling back to what `collapsed()` alone would mean.
     *
     * A list that cannot fold reports `All` whatever was set: the guard mirrors
     * `isCollapsed()`'s, where a host that asked for a folded start and then took
     * `collapsible()` away must not render something the user cannot open.
     */
    public function getItemExpansion(): ItemExpansion
    {
        if (! $this->isCollapsible()) {
            return ItemExpansion::All;
        }

        return $this->itemExpansion ?? ($this->isCollapsed() ? ItemExpansion::None : ItemExpansion::All);
    }

    /** Whether the item at `$index` of `$count` items starts folded. */
    public function isItemCollapsedByDefault(int $index, int $count): bool
    {
        return $this->getItemExpansion()->collapses($index, $count);
    }
}
