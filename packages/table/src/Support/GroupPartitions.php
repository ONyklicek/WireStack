<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * One page of records, split into its groups, in page order.
 *
 * Group subtotals render once per group, and each of them needs that group's
 * rows. Filtering the whole page per group is O(groups × page size) for an
 * answer that never changes within a render, so the page is split once and
 * looked up by value.
 *
 * ## The key is normalised, and that is the whole trick
 *
 * The grouping value is read through the table's comparison key rather than off
 * the model, because a cast column hands back a **fresh object per record** — a
 * `date` cast is a new Carbon every time, and a strict compare of two of them is
 * false even for the same day. Group by such a column with the raw value and
 * every single row forms its own group, each with its own one-row subtotal. The
 * caller is handed the same normalised key by the view, so the lookup matches.
 *
 * ## It remembers which page it split
 *
 * A partitioning is only true of the record set it was built from. That used to
 * be maintained by hand — five places drop the page memo, and three of them
 * remembered to drop the partitions too. The two that did not were `setPage()`
 * and `setTableCursor()`, so paging inside one request left every group subtotal
 * describing the previous page: the group on screen totalled zero, and a group
 * that was no longer there still showed its figure.
 *
 * So the identity of the source is carried here instead of being asserted by
 * callers. {@see describes()} answers whether this partitioning is still about a
 * given record set, and the reference is held rather than an id compared — a
 * freed collection's `spl_object_id` can be handed to its successor, and the
 * records are already referenced by the partitions anyway.
 */
final class GroupPartitions
{
    /**
     * @param  array<int, array{value: mixed, records: Collection<int, Model>}>  $partitions
     */
    private function __construct(
        private readonly mixed $source,
        private readonly array $partitions,
    ) {}

    /**
     * Split a page by its normalised group key, preserving page order both
     * within a group and between groups.
     *
     * @param  iterable<int, Model>  $records  the page, in the order it renders
     * @param  Closure(Model): mixed  $key  the table's normalised comparison key
     * @param  mixed  $source  the record set this page came from, for {@see describes()}
     */
    public static function of(iterable $records, Closure $key, mixed $source = null): self
    {
        /** @var array<int, array{value: mixed, records: Collection<int, Model>}> $partitions */
        $partitions = [];

        foreach ($records as $record) {
            $value = $key($record);
            $matched = false;

            // 'records' is a Collection object, so push() mutates it in place.
            foreach ($partitions as $partition) {
                if ($partition['value'] === $value) {
                    $partition['records']->push($record);
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                $partitions[] = ['value' => $value, 'records' => collect([$record])];
            }
        }

        return new self($source, $partitions);
    }

    /**
     * Whether this partitioning is still about `$records` — the same object the
     * page memo is currently holding, not merely an equal one.
     */
    public function describes(mixed $records): bool
    {
        return $this->source === $records;
    }

    /**
     * The rows of one group, by its normalised key.
     *
     * A value no group on this page carries returns nothing rather than
     * guessing: a subtotal for an absent group is an empty subtotal.
     *
     * @return Collection<int, Model>
     */
    public function get(mixed $groupValue): Collection
    {
        foreach ($this->partitions as $partition) {
            if ($partition['value'] === $groupValue) {
                return $partition['records'];
            }
        }

        return collect();
    }

    /**
     * The group keys on this page, in the order they render.
     *
     * @return array<int, mixed>
     */
    public function values(): array
    {
        return array_map(fn (array $partition): mixed => $partition['value'], $this->partitions);
    }

    public function count(): int
    {
        return count($this->partitions);
    }
}
