<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\BooleanColumn;
use NyonCode\WireTable\Columns\IconColumn;

/**
 * §7 for state-driven columns — the data-payload render memo
 * (render-optimization-audit-2026-07-17.md).
 *
 * A BadgeColumn's markup is a function of its low-cardinality state (value + colour +
 * icon derived from it), so `renderCell` memoises the view render by its data payload:
 * rows sharing a status reuse one render. Keying on the actual data (not a "pure
 * function" assumption) keeps it byte-identical; the win is O(distinct states), not
 * O(rows). This proves correctness, the render count, and the wall-clock.
 */
function badgeRecord(string $status): Model
{
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill(['status' => $status]);

    return $record;
}

function badgeViewRenders(Closure $c): int
{
    $n = 0;
    View::composer('wire-table::tables.columns.badge', function () use (&$n) {
        $n++;
    });
    $c();

    return $n;
}

function badgeColumn(): BadgeColumn
{
    return BadgeColumn::make('status')->colors([
        'active' => 'success', 'inactive' => 'gray', 'pending' => 'warning',
    ])->icons(['active' => 'check', 'pending' => 'clock']);
}

it('renders each distinct state correctly and identically on repeat', function () {
    $col = badgeColumn();

    $active1 = $col->renderCell(badgeRecord('active'));
    $active2 = $col->renderCell(badgeRecord('active'));   // cache hit
    $pending = $col->renderCell(badgeRecord('pending'));

    // Same state → identical (cache correct); different state → different markup.
    expect($active2)->toBe($active1)
        ->and($pending)->not->toBe($active1)
        ->and($active1)->toContain('active')
        ->and($pending)->toContain('pending');
});

it('renders once per distinct state, not once per row', function () {
    $col = badgeColumn();
    $states = ['active', 'inactive', 'pending'];
    $records = array_map(fn ($i) => badgeRecord($states[$i % 3]), range(1, 300));

    $renders = badgeViewRenders(function () use ($col, $records) {
        foreach ($records as $r) {
            $col->renderCell($r);
        }
    });

    // 300 rows, 3 distinct states → 3 view renders, not 300.
    expect($renders)->toBe(3);
});

it('icon and boolean columns adopt the same data memo — render once per state', function () {
    $viewRenders = function (string $view, Closure $c): int {
        $n = 0;
        View::composer($view, function () use (&$n) {
            $n++;
        });
        $c();

        return $n;
    };

    // IconColumn: state → icon; 3 distinct states over 300 rows → 3 renders.
    $iconCol = IconColumn::make('status')->icons([
        'active' => 'check', 'inactive' => 'x-mark', 'pending' => 'clock',
    ]);
    $states = ['active', 'inactive', 'pending'];
    $iconRecords = array_map(fn ($i) => badgeRecord($states[$i % 3]), range(1, 300));
    $iconRenders = $viewRenders('wire-table::tables.columns.icon', function () use ($iconCol, $iconRecords) {
        foreach ($iconRecords as $r) {
            $iconCol->renderCell($r);
        }
    });
    expect($iconRenders)->toBe(3);

    // BooleanColumn: only true/false → at most 2 renders over 300 rows.
    $boolCol = BooleanColumn::make('flag');
    $boolRecords = array_map(function ($i) {
        $record = new class extends Model
        {
            protected $guarded = [];
        };
        $record->forceFill(['flag' => $i % 2 === 0]);

        return $record;
    }, range(1, 300));
    $boolRenders = $viewRenders('wire-table::tables.columns.boolean', function () use ($boolCol, $boolRecords) {
        foreach ($boolRecords as $r) {
            $boolCol->renderCell($r);
        }
    });
    expect($boolRenders)->toBe(2);
});

it('is far cheaper for low-cardinality state than a per-cell render', function () {
    $rows = 2000;

    $time = function (callable $fn): float {
        $t = microtime(true);
        $fn();

        return (microtime(true) - $t) * 1000;
    };

    // Realistic: 4 distinct statuses across 2000 rows → 4 renders.
    $realStates = ['active', 'inactive', 'pending', 'active'];
    $realCol = badgeColumn();
    $realRecords = array_map(fn ($i) => badgeRecord($realStates[$i % 4]), range(1, $rows));
    $realMs = $time(function () use ($realCol, $realRecords) {
        foreach ($realRecords as $r) {
            $realCol->renderCell($r);
        }
    });

    // Worst case: a unique status per row → the memo never hits (≈ old per-cell cost).
    $uniqueCol = badgeColumn();
    $uniqueRecords = array_map(fn ($i) => badgeRecord('s'.$i), range(1, $rows));
    $uniqueMs = $time(function () use ($uniqueCol, $uniqueRecords) {
        foreach ($uniqueRecords as $r) {
            $uniqueCol->renderCell($r);
        }
    });

    fwrite(STDERR, "\n=== §7 BadgeColumn data-memo — {$rows} cells ===\n");
    fwrite(STDERR, sprintf("  4 distinct states (memoised): %.1f ms\n", $realMs));
    fwrite(STDERR, sprintf("  2000 unique states (memo defeated ≈ old): %.1f ms   (%.1f× faster)\n\n", $uniqueMs, $uniqueMs / max($realMs, 0.001)));

    expect($realMs)->toBeLessThan($uniqueMs);
});
