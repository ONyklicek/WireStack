<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Actions\Concerns\HasBadge;
use NyonCode\WireCore\Foundation\Concerns\HasIcon;
use NyonCode\WireCore\Foundation\Concerns\HasLabel;
use NyonCode\WireCore\Foundation\Concerns\HasName;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * One tab above a list: a name, how it narrows the query, and what it says.
 *
 *   ListTab::make('all'),
 *   ListTab::make('open')->query(fn (Builder $q) => $q->whereNull('closed_at'))->showCount(),
 *   ListTab::make('overdue')->icon('outline:clock')->query(...)->badgeColor('danger'),
 *
 * A tab narrows the list's *base* query — before search, filters and sort — so
 * everything the table does still applies inside it. A tab with no `query()` is
 * the whole list. The label, the icon and the badge are the canonical concerns'
 * (`HasLabel`, `HasIcon`, `HasBadge`); a tab that names no label is its name,
 * humanised.
 */
final class ListTab
{
    use EvaluatesClosures;
    use HasBadge;
    use HasIcon;
    use HasLabel;
    use HasName;

    private ?Closure $query = null;

    private bool $showCount = false;

    private function __construct(string $name)
    {
        $this->name = $name;
    }

    /** A tab under this name — the name is what the URL carries. */
    public static function make(string $name): self
    {
        return new self($name);
    }

    /** Narrow the list: `fn (Builder $query) => $query->where(…)`, returning the builder or nothing. */
    public function query(?Closure $callback): static
    {
        $this->query = $callback;

        return $this;
    }

    /** Show how many records the tab holds, counted on its own query. */
    public function showCount(bool $condition = true): static
    {
        $this->showCount = $condition;

        return $this;
    }

    /**
     * Gray unless the tab says otherwise: a count beside a tab is a number, not
     * an alert, and `HasBadge` defaults to the colour of one.
     */
    public function getBadgeColor(): string
    {
        return $this->badgeColor ?? 'gray';
    }

    public function showsCount(): bool
    {
        return $this->showCount;
    }

    public function hasQuery(): bool
    {
        return $this->query !== null;
    }

    /**
     * The query, narrowed by this tab.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function apply(Builder $query): Builder
    {
        if ($this->query === null) {
            return $query;
        }

        return ($this->query)($query) ?? $query;
    }
}
