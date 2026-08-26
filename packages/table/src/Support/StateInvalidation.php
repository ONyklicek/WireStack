<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

/**
 * What a write to a table-state path invalidates.
 *
 * A decision, not an action: this says *what* must be reset, and the host does
 * the resetting. The distinction is what makes the rules readable, because they
 * are not uniform and the exceptions are the interesting part.
 *
 * Narrowing the set — a search, a filter — resets the page **and** the selection
 * scope, because "everything the filter matches" was defined by the filter that
 * was on screen. Redefining the set while a select-all-matching selection stands
 * would silently change what a bulk action is about to touch.
 *
 * Re-sorting or resizing the page does **not** touch the selection: the same
 * records still match, they are merely arranged differently, and a user who
 * selected everything and then sorted has not changed their mind about what
 * they selected.
 *
 * A cursor never survives any of it. It points into an ordering that no longer
 * exists once the set is narrowed or re-sorted, unlike a page number, which is
 * nominally still meaningful.
 */
final readonly class StateInvalidation
{
    /**
     * The paths that invalidate anything at all. A write below one of these —
     * `filters.role.value` under `filters` — counts as a write to it.
     *
     * @var array<int, string>
     */
    private const PATHS = [
        'pagination.perPage',
        'search',
        'filters',
        'columnFilters',
        'sort.column',
        'sort.direction',
    ];

    /**
     * The paths that rearrange rather than narrow, and so leave the selection
     * alone.
     *
     * @var array<int, string>
     */
    private const REARRANGING = [
        'sort.column',
        'sort.direction',
        'pagination.perPage',
    ];

    private function __construct(
        public bool $resetsPage,
        public bool $clearsCursor,
        public bool $resetsSelectionScope,
        public bool $normalisesPerPage,
        public bool $marksViewChanged,
    ) {}

    /**
     * What this path invalidates, or null when it invalidates nothing.
     *
     * Null is the ordinary answer: most writes to table state — a modal form
     * field, a selection toggle, an expanded row — leave the query alone.
     */
    public static function forPath(string $path): ?self
    {
        $matched = null;

        foreach (self::PATHS as $candidate) {
            if ($path === $candidate || str_starts_with($path, $candidate.'.')) {
                $matched = $candidate;

                break;
            }
        }

        if ($matched === null) {
            return null;
        }

        return new self(
            resetsPage: true,
            clearsCursor: true,
            resetsSelectionScope: ! in_array($matched, self::REARRANGING, true),
            normalisesPerPage: $matched === 'pagination.perPage',
            // The view this render must produce is not the one the poll checksum
            // was taken for.
            marksViewChanged: true,
        );
    }
}
