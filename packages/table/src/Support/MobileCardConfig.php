<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

/**
 * The fluent form of a mobile card, naming columns by name:
 *
 *   ->mobileCard(fn (MobileCardConfig $card) => $card
 *       ->title('number')
 *       ->subtitle('customer')
 *       ->metric('total')
 *       ->meta(['status', 'due_at']))
 *
 * Kept apart from {@see MobileCard} because that one is the resolved result —
 * columns already picked — while this is the declaration a table writes. Any
 * slot left unnamed is derived.
 */
final class MobileCardConfig
{
    /** @var array<string, MobileSlot> column name => slot */
    private array $assignments = [];

    public function title(string $column): static
    {
        return $this->assign($column, MobileSlot::Title);
    }

    public function subtitle(string $column): static
    {
        return $this->assign($column, MobileSlot::Subtitle);
    }

    public function metric(string $column): static
    {
        return $this->assign($column, MobileSlot::Metric);
    }

    /**
     * @param  string|array<int, string>  $columns
     */
    public function meta(string|array $columns): static
    {
        foreach ((array) $columns as $column) {
            $this->assign($column, MobileSlot::Meta);
        }

        return $this;
    }

    /**
     * Push a column down into the label/value grid, out of the header.
     *
     * @param  string|array<int, string>  $columns
     */
    public function detail(string|array $columns): static
    {
        foreach ((array) $columns as $column) {
            $this->assign($column, MobileSlot::Detail);
        }

        return $this;
    }

    /**
     * @return array<string, MobileSlot>
     */
    public function assignments(): array
    {
        return $this->assignments;
    }

    private function assign(string $column, MobileSlot $slot): static
    {
        // A singular slot holds one column: naming a second one replaces the
        // first rather than quietly keeping both.
        if ($slot->isSingular()) {
            $this->assignments = array_filter(
                $this->assignments,
                fn (MobileSlot $assigned): bool => $assigned !== $slot,
            );
        }

        $this->assignments[$column] = $slot;

        return $this;
    }
}
