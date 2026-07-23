<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

/**
 * The per-record outcomes of one fill, and the envelope the client reads.
 *
 * A fill is deliberately not all-or-nothing: one row losing an optimistic-lock
 * race, or being disabled for this user, must not discard the other forty-nine.
 * So every record gets its own {@see CellEditOutcome} and the client reconciles
 * cell by cell — confirming the ones that landed and rolling back only the ones
 * that did not.
 */
final readonly class FillResult
{
    /**
     * @param  array<string, array<string, CellEditOutcome>>  $outcomes  column => record key => outcome
     */
    public function __construct(public array $outcomes) {}

    public function total(): int
    {
        return array_sum(array_map('count', $this->outcomes));
    }

    public function filled(): int
    {
        $filled = 0;

        foreach ($this->outcomes as $records) {
            foreach ($records as $outcome) {
                if ($outcome->success) {
                    $filled++;
                }
            }
        }

        return $filled;
    }

    public function allSucceeded(): bool
    {
        return $this->filled() === $this->total();
    }

    /**
     * The wire shape. `results` is keyed the way the client indexed its cells —
     * by column, then by record key — so a cell finds its own outcome without
     * scanning.
     *
     * @return array{success: bool, results: array<string, array<string, array<string, mixed>>>, message: string|null}
     */
    public function toArray(): array
    {
        $results = [];

        foreach ($this->outcomes as $column => $records) {
            foreach ($records as $key => $outcome) {
                $results[$column][$key] = $outcome->toArray();
            }
        }

        $succeeded = $this->allSucceeded();

        return [
            'success' => $succeeded,
            'results' => $results,
            'message' => $succeeded
                ? null
                : __('wire-table::messages.fill_partial', [
                    'filled' => $this->filled(),
                    'total' => $this->total(),
                ]),
        ];
    }
}
