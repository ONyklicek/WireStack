<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use NyonCode\WireTable\Columns\TextInputColumn;

/**
 * §7 boundary evaluation — inline-edit multi-token skeleton
 * (render-optimization-audit-2026-07-17.md).
 *
 * The editable cell is NOT finite-state (value/key/version are per-record, unbounded),
 * but its structure is fixed: exactly three per-record values. This tests whether a
 * multi-token skeleton splice can reproduce `renderCell` byte-for-byte despite the
 * value appearing in two encodings (JSON in the Alpine config, HTML attr in
 * data-server-value), and measures the speed-up. The verdict is the pass/fail below.
 */
function editRecord(int|string $id, string $value, int $ts): Model
{
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill([
        'id' => $id,
        'name' => $value,
        'updated_at' => Carbon::createFromTimestamp($ts),
    ]);

    return $record;
}

it('inline-edit skeleton splice is byte-identical to renderCell', function () {
    // The value is the hard case: it lands in a JSON string AND an HTML attribute.
    $values = [
        'Alice', 'a "quote" & <b>x</b>', "it's", 'back\\slash', '', '0', 'Ünïcödé — ř',
        "tab\tnew\nline", '{"json":true}', '<script>alert(1)</script>',
    ];
    $col = TextInputColumn::make('name');

    foreach ($values as $i => $value) {
        // Vary key and version per record too, so their splices are exercised.
        $record = editRecord($i + 1, $value, 1_600_000_000 + $i * 3600);

        expect($col->renderEditableCellFast($record))
            ->toBe($col->renderCell($record), 'value='.var_export($value, true));
    }
});

it('handles a non-numeric (uuid-like) record key identically', function () {
    $col = TextInputColumn::make('name');
    $record = editRecord('9f3c-ab12-"x"&y', 'val', 1_600_000_000);

    expect($col->renderEditableCellFast($record))->toBe($col->renderCell($record));
});

it('renders the editable view once for the whole column, not per row', function () {
    $col = TextInputColumn::make('name');
    $records = array_map(fn ($i) => editRecord($i, "v$i", 1_600_000_000 + $i), range(1, 100));

    $n = 0;
    View::composer('wire-table::tables.columns.text-input-editable', function () use (&$n) {
        $n++;
    });

    foreach ($records as $r) {
        $col->renderEditableCellFast($r);
    }

    // One skeleton render for all 100 rows.
    expect($n)->toBe(1);
});

it('memoises input classes/attributes per column without changing output (Tier-2g)', function () {
    $col = TextInputColumn::make('name')->numeric()->placeholder('Amount')->inputClass('font-mono');

    // Repeated calls are identical (cache correct) …
    expect($col->buildInputAttributes())->toBe($col->buildInputAttributes())
        ->and($col->buildInputClasses(true, false))->toBe($col->buildInputClasses(true, false))
        // … the attributes reflect the config …
        ->and($col->buildInputAttributes())->toContain('type="number"')
        ->and($col->buildInputAttributes())->toContain('placeholder="Amount"')
        // … and the prefix/suffix variants stay distinct and correct.
        ->and($col->buildInputClasses(true, false))->toContain('pl-7')
        ->and($col->buildInputClasses(false, true))->toContain('pr-8')
        ->and($col->buildInputClasses(true, false))->not->toBe($col->buildInputClasses(false, true))
        ->and($col->buildInputClasses(false, false))->toContain('font-mono');
});

it('measures the inline-edit skeleton speed-up', function () {
    $rows = 2000;
    $records = array_map(fn ($i) => editRecord($i, "value $i", 1_600_000_000 + $i), range(1, $rows));

    $time = function (callable $fn): float {
        $t = microtime(true);
        $fn();

        return (microtime(true) - $t) * 1000;
    };

    $slowCol = TextInputColumn::make('name');
    $slowMs = $time(function () use ($slowCol, $records) {
        foreach ($records as $r) {
            $slowCol->renderCell($r);
        }
    });

    $fastCol = TextInputColumn::make('name');
    $fastMs = $time(function () use ($fastCol, $records) {
        foreach ($records as $r) {
            $fastCol->renderEditableCellFast($r);
        }
    });

    fwrite(STDERR, "\n=== §7 inline-edit multi-token skeleton — {$rows} cells ===\n");
    fwrite(STDERR, sprintf("  renderCell (view/cell): %.1f ms\n", $slowMs));
    fwrite(STDERR, sprintf("  renderEditableCellFast (skeleton splice): %.1f ms   (%.1f× faster)\n\n", $fastMs, $slowMs / max($fastMs, 0.001)));

    expect($fastMs)->toBeLessThan($slowMs);
});
