<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\IconColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;

/**
 * §7 proof-of-concept + Rule 2 disproof test
 * (render-optimization-audit-2026-07-17.md).
 *
 * A component "renders itself" via a per-column Htmlable skeleton (resolve the view
 * once, splice per-row state) instead of `view()->render()` per cell. Rule 2 holds iff
 * the skeleton is (a) byte-identical to `renderCell()` and (b) materially cheaper. This
 * proves both for TextColumn, and measures the interactive TextInputColumn (the Rule 2
 * boundary, where per-record structure is not spliceable).
 */
function skelRecord(mixed $value): Model
{
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill(['val' => $value, 'id' => 1]);

    return $record;
}

function skelViewRenders(Closure $c): int
{
    $n = 0;
    View::composer('*', function () use (&$n) {
        $n++;
    });
    $c();

    return $n;
}

$contents = [
    'Alice', 'a & b < c > "d" \'e\'', '  spaced  ', 'Ünïcödé — ř', '', '0', '<b>x</b>',
];

it('skeleton splice is byte-identical to renderCell across configs and content', function () use ($contents) {
    $configs = [
        'plain' => fn () => TextColumn::make('val'),
        'sized+weight+color' => fn () => TextColumn::make('val')->size('lg')->weight('bold')->color('primary'),
        'icon-before' => fn () => TextColumn::make('val')->icon('pencil'),
        'html' => fn () => TextColumn::make('val')->html(),
        'static-tooltip' => fn () => TextColumn::make('val')->tooltip('a tip'),
        'static-description' => fn () => TextColumn::make('val')->description('a desc'),
        'compound' => fn () => TextColumn::make('val')->size('sm')->icon('pencil')->tooltip('t')->description('d'),
    ];

    foreach ($configs as $label => $make) {
        foreach ($contents as $content) {
            // Fresh columns so the skeleton cache is cold for the fast path.
            $slow = ($make)();
            $fast = ($make)();
            $record = skelRecord($content);

            expect($fast->renderCellFast($record))
                ->toBe($slow->renderCell($record), "config=$label content=".var_export($content, true));
        }
    }
});

it('falls back to renderCell (still identical) for non-skeletonable columns', function () use ($contents) {
    $configs = [
        'copyable' => fn () => TextColumn::make('val')->copyable(),
        'url' => fn () => TextColumn::make('val')->actionUrl(fn ($r) => 'https://x.test/'.$r->id),
        'description-closure' => fn () => TextColumn::make('val')->description(fn ($r) => 'desc-'.$r->id),
    ];

    foreach ($configs as $label => $make) {
        foreach ($contents as $content) {
            $slow = ($make)();
            $fast = ($make)();
            $record = skelRecord($content);
            expect($fast->renderCellFast($record))->toBe($slow->renderCell($record), $label);
        }
    }
});

it('view-overriding subclasses fall back to their own renderCell (no text skeleton)', function () {
    // Badge/Icon render their own view; renderCellFast must NOT apply the text
    // skeleton to them — it detects the overridden renderCell and delegates.
    $record = skelRecord('active');

    $badge = BadgeColumn::make('val');
    expect($badge->renderCellFast($record))->toBe($badge->renderCell($record));

    $icon = IconColumn::make('val');
    expect($icon->renderCellFast($record))->toBe($icon->renderCell($record));
});

it('renders the view once for the whole column, not once per row', function () {
    $rows = 100;
    $records = array_map(fn ($i) => skelRecord("row $i"), range(1, $rows));

    $fastCol = TextColumn::make('val');
    $fast = skelViewRenders(function () use ($fastCol, $records) {
        foreach ($records as $r) {
            $fastCol->renderCellFast($r);
        }
    });

    $slowCol = TextColumn::make('val');
    $slow = skelViewRenders(function () use ($slowCol, $records) {
        foreach ($records as $r) {
            $slowCol->renderCell($r);
        }
    });

    // Skeleton: 1 view render for all 100 rows. renderCell: 1 per row.
    expect($fast)->toBe(1)
        ->and($slow)->toBe($rows);
});

it('measures wall-clock and the TextInputColumn (inline-edit) boundary', function () {
    $rows = 2000;
    $records = array_map(fn ($i) => skelRecord("row $i"), range(1, $rows));

    $time = function (callable $fn): float {
        $t = microtime(true);
        $fn();

        return (microtime(true) - $t) * 1000;
    };

    $slowCol = TextColumn::make('val');
    $slowMs = $time(function () use ($slowCol, $records) {
        foreach ($records as $r) {
            $slowCol->renderCell($r);
        }
    });

    $fastCol = TextColumn::make('val');
    $fastMs = $time(function () use ($fastCol, $records) {
        foreach ($records as $r) {
            $fastCol->renderCellFast($r);
        }
    });

    // Inline-edit column: overrides renderCell with a per-record interactive view
    // (input value, wire:key, per-record Alpine commit config) — not skeletonable
    // via the content-splice mechanism. Measure its per-cell render cost.
    $editCol = TextInputColumn::make('val');
    $editRenders = skelViewRenders(function () use ($editCol, $records) {
        foreach (array_slice($records, 0, 100) as $r) {
            $editCol->renderCell($r);
        }
    });
    $editMs = $time(function () use ($editCol, $records) {
        foreach ($records as $r) {
            $editCol->renderCell($r);
        }
    });

    fwrite(STDERR, "\n=== §7 TextColumn skeleton — {$rows} cells ===\n");
    fwrite(STDERR, sprintf("  renderCell (view/cell): %.1f ms\n", $slowMs));
    fwrite(STDERR, sprintf("  renderCellFast (skeleton splice): %.1f ms   (%.1f× faster)\n", $fastMs, $slowMs / max($fastMs, 0.001)));
    fwrite(STDERR, sprintf("\n=== TextInputColumn (inline-edit) — Rule 2 boundary ===\n"));
    fwrite(STDERR, sprintf("  view renders / 100 cells: %d  (=> per-cell view render, NOT skeletonable)\n", $editRenders));
    fwrite(STDERR, sprintf("  renderCell (view/cell): %.1f ms for {$rows} cells\n\n", $editMs));

    expect($fastMs)->toBeLessThan($slowMs);
});
