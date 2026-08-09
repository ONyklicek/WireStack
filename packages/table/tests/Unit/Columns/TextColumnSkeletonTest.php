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
function skelRecord(mixed $value, mixed $id = 1): Model
{
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill(['val' => $value, 'id' => $id]);

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

/**
 * The per-record configs — the ones that used to drop the column back onto a
 * per-cell render, at a measured 18–33× the cost of a splice.
 *
 * Each is now its own slot, and each slot is one position with one encoding: the
 * url inside `href="…"`, the copy value inside `data-copy="…"`, the description
 * inside a `<p>`, the icon as raw markup. That is the property the whole multi-slot
 * move rests on, so the ids below are hostile on purpose — a quote or an ampersand
 * reaching a slot under the wrong encoding is exactly what byte-identity catches.
 */
$skelPerRecordConfigs = [
    'copyable' => fn () => TextColumn::make('val')->copyable(),
    'url' => fn () => TextColumn::make('val')->actionUrl(fn ($r) => 'https://x.test/'.$r->id),
    'url+newtab' => fn () => TextColumn::make('val')->actionUrl(fn ($r) => '/p?a=1&b="2"&c='.$r->id, true),
    'description-closure' => fn () => TextColumn::make('val')->description(fn ($r) => 'desc & <b>'.$r->id.'</b>'),
    'icon-closure' => fn () => TextColumn::make('val')->icon(fn ($record) => $record->id % 2 ? 'pencil' : 'trash'),
    'copyable+url' => fn () => TextColumn::make('val')->copyable()->actionUrl(fn ($r) => 'https://x.test/'.$r->id),
    'all-at-once' => fn () => TextColumn::make('val')
        ->copyable()
        ->actionUrl(fn ($r) => 'https://x.test/?q='.$r->id)
        ->description(fn ($r) => 'd'.$r->id)
        ->icon(fn ($record) => 'pencil')
        ->tooltip('t'),
];

$skelIds = [1, 7, 'a&b', 'q"uote', "ap'os", '<x>', 'ünï'];

it('splices per-record url / copy / description / icon byte-identically', function () use ($contents, $skelPerRecordConfigs, $skelIds) {
    foreach ($skelPerRecordConfigs as $label => $make) {
        foreach ($contents as $content) {
            foreach ($skelIds as $id) {
                // One fresh column per case so the shape cache starts cold, and one
                // shared column below to prove the cache does not leak between rows.
                $slow = ($make)();
                $fast = ($make)();
                $record = skelRecord($content, $id);

                expect($fast->renderCellFast($record))->toBe(
                    $slow->renderCell($record),
                    "config=$label id=".var_export($id, true).' content='.var_export($content, true)
                );
            }
        }
    }
});

it('keeps rows apart when one column serves many records', function () use ($skelPerRecordConfigs, $skelIds) {
    // The real loop: ONE column instance, many records. A skeleton cached from the
    // first row must not carry that row's url, copy value, description or icon into
    // the next — the failure mode a per-column cache invites.
    foreach ($skelPerRecordConfigs as $label => $make) {
        $shared = ($make)();

        foreach ($skelIds as $id) {
            $record = skelRecord('v', $id);

            expect($shared->renderCellFast($record))->toBe(
                ($make)()->renderCell($record),
                "config=$label id=".var_export($id, true)
            );
        }
    }
});

it('renders one view per cell SHAPE, not one per row', function () {
    // A url-bearing column is now spliced, so 100 rows cost the one skeleton render
    // their shape needs — where before every row paid a full view render.
    $records = array_map(fn ($i) => skelRecord("row $i", $i), range(1, 100));

    $column = TextColumn::make('val')->actionUrl(fn ($r) => 'https://x.test/'.$r->id)->copyable();
    $renders = skelViewRenders(function () use ($column, $records) {
        foreach ($records as $r) {
            $column->renderCellFast($r);
        }
    });

    // 3, all one-off: the text partial, the copyable partial it includes, and core's
    // copy-button partial that one compiles into its own skeleton. What matters is
    // that the number does not move with the row count — 100 rows, 3 renders.
    expect($renders)->toBeLessThanOrEqual(3);
});

it('renders one skeleton per shape when a record turns a part off', function () {
    // A closure that answers for some records and not others is two shapes, and each
    // is compiled once however many rows share it — never once per row.
    $records = array_map(fn ($i) => skelRecord("row $i", $i), range(1, 100));

    $column = TextColumn::make('val')->actionUrl(fn ($r) => $r->id % 2 ? 'https://x.test/'.$r->id : null);
    $renders = skelViewRenders(function () use ($column, $records) {
        foreach ($records as $r) {
            $column->renderCellFast($r);
        }
    });

    expect($renders)->toBe(2);

    // …and both shapes are still right.
    $withUrl = TextColumn::make('val')->actionUrl(fn ($r) => $r->id % 2 ? 'https://x.test/'.$r->id : null);
    foreach ([1, 2] as $id) {
        $record = skelRecord('v', $id);
        expect($column->renderCellFast($record))->toBe($withUrl->renderCell($record), "id=$id");
    }
});

it('leaks no sentinel into a rendered cell', function () use ($skelPerRecordConfigs) {
    // The one failure a skeleton can produce that no other assertion would notice:
    // an unfilled hole shipping its placeholder to the browser.
    foreach ($skelPerRecordConfigs as $label => $make) {
        $html = ($make)()->renderCellFast(skelRecord('v', 3));

        expect($html)->not->toContain('ᐊWIRE_SLOT_', $label);
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
