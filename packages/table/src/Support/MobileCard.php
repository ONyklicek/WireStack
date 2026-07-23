<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use Closure;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\Column;

/**
 * How a record reads on a stacked mobile card.
 *
 * The card used to be the column order in disguise: first column as the title,
 * every remaining column dumped into a two-column label/value grid. That is an
 * accident of declaration order, not a design — reorder the columns and the
 * phone layout changes meaning.
 *
 * This resolves the columns into the named {@see MobileSlot}s once per render.
 * An explicit choice always wins; everything unassigned is derived, so a table
 * that never heard of this class still gets a better card than the dump it has
 * today.
 */
final class MobileCard
{
    /** @param  array<int, Column>  $details */
    private function __construct(
        private readonly ?Column $title,
        private readonly ?Column $subtitle,
        private readonly ?Column $metric,
        /** @var array<int, Column> */
        private readonly array $meta,
        /** @var array<int, Column> */
        private readonly array $details,
    ) {}

    /**
     * Resolve the card for a set of already-visible columns.
     *
     * @param  array<int, Column>  $columns  visible columns, in table order
     * @param  Closure|null  $configure  fn (MobileCardConfig $card) => …, naming columns explicitly
     */
    public static function resolve(array $columns, ?Closure $configure = null): self
    {
        $columns = array_values($columns);

        $config = new MobileCardConfig;
        if ($configure !== null) {
            $configure($config);
        }

        /** @var array<string, MobileSlot> $assigned */
        $assigned = [];

        // 1. Names given to mobileCard() — the most specific statement of intent.
        foreach ($config->assignments() as $name => $slot) {
            $assigned[$name] = $slot;
        }

        // These read $assigned as it grows, so they must bind by reference —
        // an arrow function would capture the array as it was here and every
        // derivation below would then be blind to its own assignments.
        $taken = function (MobileSlot $slot) use (&$assigned): bool {
            return in_array($slot, $assigned, true);
        };
        $free = function (Column $c) use (&$assigned): bool {
            return ! isset($assigned[$c->getName()]);
        };

        // 2. Per-column declarations (->mobileMetric() and friends). A singular
        //    slot the config already named is not up for grabs.
        foreach ($columns as $column) {
            $slot = $column->getMobileSlot();

            if ($slot === null || ! $free($column)) {
                continue;
            }

            if ($slot->isSingular() && $taken($slot)) {
                continue;
            }

            $assigned[$column->getName()] = $slot;
        }

        // 3. Derivation, in the order the slots carry meaning.
        if (! $taken(MobileSlot::Title)) {
            $first = self::first($columns, $free);
            if ($first !== null) {
                $assigned[$first->getName()] = MobileSlot::Title;
            }
        }

        if (! $taken(MobileSlot::Metric)) {
            // The figure the list is read for: the last right-aligned column,
            // which is what money() and numeric() produce.
            $metric = self::last($columns, fn (Column $c): bool => $free($c) && $c->getAlignment() === 'right');
            if ($metric !== null) {
                $assigned[$metric->getName()] = MobileSlot::Metric;
            }
        }

        if (! $taken(MobileSlot::Meta)) {
            foreach ($columns as $column) {
                if ($free($column) && $column instanceof BadgeColumn) {
                    $assigned[$column->getName()] = MobileSlot::Meta;
                }
            }
        }

        if (! $taken(MobileSlot::Subtitle)) {
            $subtitle = self::first($columns, $free);
            if ($subtitle !== null) {
                $assigned[$subtitle->getName()] = MobileSlot::Subtitle;
            }
        }

        $pick = function (MobileSlot $slot) use ($columns, $assigned): ?Column {
            foreach ($columns as $column) {
                if (($assigned[$column->getName()] ?? null) === $slot) {
                    return $column;
                }
            }

            return null;
        };
        $pickAll = function (MobileSlot $slot) use ($columns, $assigned): array {
            return array_values(array_filter(
                $columns,
                fn (Column $c): bool => ($assigned[$c->getName()] ?? null) === $slot,
            ));
        };

        return new self(
            title: $pick(MobileSlot::Title),
            subtitle: $pick(MobileSlot::Subtitle),
            metric: $pick(MobileSlot::Metric),
            meta: $pickAll(MobileSlot::Meta),
            details: array_values(array_filter(
                $columns,
                fn (Column $c): bool => ! isset($assigned[$c->getName()])
                    || $assigned[$c->getName()] === MobileSlot::Detail,
            )),
        );
    }

    public function title(): ?Column
    {
        return $this->title;
    }

    public function subtitle(): ?Column
    {
        return $this->subtitle;
    }

    public function metric(): ?Column
    {
        return $this->metric;
    }

    /**
     * @return array<int, Column>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    /**
     * The label/value grid under the header — every column no slot claimed.
     *
     * @return array<int, Column>
     */
    public function details(): array
    {
        return $this->details;
    }

    /**
     * @param  array<int, Column>  $columns
     */
    private static function first(array $columns, callable $matches): ?Column
    {
        foreach ($columns as $column) {
            if ($matches($column)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @param  array<int, Column>  $columns
     */
    private static function last(array $columns, callable $matches): ?Column
    {
        foreach (array_reverse($columns) as $column) {
            if ($matches($column)) {
                return $column;
            }
        }

        return null;
    }
}
