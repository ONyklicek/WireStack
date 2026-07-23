<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Exception;
use NyonCode\WireTable\Services\CellFillWriter;
use NyonCode\WireTable\Support\CellFill;

/**
 * The Livewire endpoint behind the fill handle.
 *
 * A host concern: this is the method the browser calls, so it stays on the
 * component. It parses the payload, refuses a table that never offered the
 * affordance, and hands the work to {@see CellFillWriter} — the writing itself
 * is not in here, and neither is any decision about a record.
 *
 * Like `updateTableCell()` it `skipRender()`s: the table must not re-render, or
 * the DOM morph resets the Alpine state of every editable cell on the page. The
 * client reconciles each filled cell from the per-record results instead.
 */
trait CanFillCells
{
    /**
     * Write one value to many records, in one request.
     *
     * The payload is a *list* of `{column, value, records}` entries, where
     * `records` maps record key to the optimistic-lock version the client held.
     * The vertical fill sends a single entry; the shape is a list so horizontal
     * and rectangular fill need no second endpoint.
     *
     * @param  array<int, array<string, mixed>>  $fills
     * @return array{success: bool, results: array<string, array<string, array<string, mixed>>>, message: string|null}
     */
    public function fillTableCells(array $fills): array
    {
        if (method_exists($this, 'skipRender')) {
            $this->skipRender();
        }

        $table = $this->getTable();

        // The endpoint is public on the component, so a client could call it
        // against a table that never rendered a handle. Honour the opt-in here,
        // not only in the view.
        if (! $table->isFillHandleEnabled()) {
            return $this->fillRefused(__('wire-table::messages.fill_not_enabled'));
        }

        try {
            return app(CellFillWriter::class)
                ->write($table, CellFill::listFromPayload($fills), static::class)
                ->toArray();
        } catch (Exception $e) {
            // A malformed payload, a request over the cap, and a failed write all
            // reach the client the same way: as an answer, never as a raised
            // exception. The caller is an Alpine controller waiting on a result
            // shape, and an escaped throw would strand every dragged cell.
            return $this->fillRefused(__('wire-table::messages.fill_error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * @return array{success: bool, results: array<string, array<string, array<string, mixed>>>, message: string|null}
     */
    private function fillRefused(string $message): array
    {
        return ['success' => false, 'results' => [], 'message' => $message];
    }
}
