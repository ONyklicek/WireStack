<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * Something the user can fold away, and whether it starts folded.
 *
 * One vocabulary, written three times before this: `Section`, `Repeater` and —
 * the one that made it worth extracting — the navigation group a sidebar draws.
 * All three had the same pair of properties, the same two setters and the same
 * rule underneath: **`collapsed()` implies `collapsible()`**, because a thing
 * that starts folded and cannot be unfolded is not a feature, it is a bug the
 * user cannot get out of.
 *
 * `NavigationGroup` deliberately went without it until now, and said why: the
 * vocabulary already existed twice and a third copy would be written before
 * anything could use it. The shell is that consumer, so this is the promised
 * canonical owner rather than a fourth copy.
 *
 * **What it does not own.** A table's `collapsibleGroups()` stays where it is:
 * that is a *host* switch — "may this table's groups fold at all" — beside
 * per-group runtime state in the table's own snapshot. Same word, different
 * question, and collapsing the two would be the universal helper this
 * repository's standard warns about.
 *
 * Closures are accepted for the same reason {@see HasVisibility} takes them: a
 * group that folds for one role and not another is a condition, not a constant.
 * They are evaluated on read, so a host composing this must also use
 * {@see EvaluatesClosures}.
 */
trait CanBeCollapsed
{
    protected bool|Closure $collapsible = false;

    protected bool|Closure $collapsed = false;

    /** Allow this to be folded away and opened again. */
    public function collapsible(bool|Closure $condition = true): static
    {
        $this->collapsible = $condition;

        return $this;
    }

    /** Start folded — which implies {@see collapsible()}, since it must be openable. */
    public function collapsed(bool|Closure $condition = true): static
    {
        $this->collapsed = $condition;

        if ($condition !== false) {
            $this->collapsible = $condition;
        }

        return $this;
    }

    public function isCollapsible(): bool
    {
        return (bool) $this->evaluate($this->collapsible);
    }

    public function isCollapsed(): bool
    {
        // Guarded by isCollapsible() rather than trusting the flag: a caller that
        // set `collapsed(fn () => true)` and `collapsible(false)` afterwards
        // would otherwise render something folded with no way to open it.
        return $this->isCollapsible() && (bool) $this->evaluate($this->collapsed);
    }
}
