<?php

declare(strict_types=1);

use NyonCode\WireTable\WireTableServiceProvider;

/*
 * The fill handle's half of the editable-cell version protocol.
 *
 * The rules and the bug behind them are written up in wire-core's
 * EditableCellVersionSourceTest, which owns `editable/sync.js`: a cell root
 * carries `wire:ignore.self`, so Livewire stops refreshing its attributes and
 * `data-record-version` freezes at whatever the first render wrote. A reader that
 * takes the attribute instead of the component's state sends the version the page
 * LOADED with, the server refuses the range as somebody else's edit, and the fill
 * rolls back with no error anywhere.
 *
 * The fill handle is the reader that got it wrong. It shipped inside
 * `wire-core-dropdown.js` until ADR 0025 § step 10 moved it into this package's
 * own bundle, and its half of the guard came with it — asserted here against the
 * files that now hold it, so neither half can drift while the other looks green.
 */

$source = fn (string $path): string => file_get_contents(
    dirname(WireTableServiceProvider::ASSETS_PATH)."/resources/js/{$path}"
);

test('the fill grid re-exports the canonical version reader instead of re-deriving it', function () use ($source) {
    expect($source('fill/grid.js'))
        ->toContain('export { versionOf }')
        ->toContain('version: versionOf(el)')
        ->not->toContain('version: el.dataset.recordVersion');
});

test('the fill controller never reads the version off the attribute', function () use ($source) {
    $controller = $source('fill/controller.js');

    expect($controller)
        ->toContain("import { createGrid, versionOf } from './grid'")
        // The request payload, and the snapshot it rolls back to. A stale version
        // in either one refuses the write — the second silently poisons the fill
        // after it, which is how this hid for so long.
        ->toContain('records[cell.recordKey] = versionOf(cell.el)')
        ->toContain('version: versionOf(cell.el)');

    // The attribute may still be WRITTEN here (applyVersion keeps the DOM in step
    // with what the server handed back). It may never be read. Assert the shape
    // rather than the count, so adding another write does not fail this.
    $reads = preg_match_all('/dataset\.recordVersion(?!\s*=)/', $controller);

    expect($reads)->toBe(0, 'fill/controller.js reads data-record-version instead of the component version');
});

test('neither fill module reads the sync pair off the cell root', function () use ($source) {
    // The cell root is the frozen one; the sync node is not. Reading either of the
    // pair from the root is the bug this whole channel exists to fix.
    foreach (['fill/grid.js', 'fill/controller.js'] as $file) {
        expect(preg_match('/\bel\.dataset\.(serverValue|recordVersion)\b/', $source($file)))
            ->toBe(0, "{$file} reads the sync pair off the cell root");
    }
});

test('the shipped fill bundle carries the state-first version read', function () {
    // The rebuild guard: esbuild leaves property names alone, so this survives
    // minification and fails if dist drifts from source because
    // `npm run build:table-assets` was not run.
    $bundle = file_get_contents(WireTableServiceProvider::ASSETS_PATH.'/wire-table-fill.js');

    expect(preg_match('/\$data\([^)]*\)\??\.\w*[Rr]ecordVersion\s*\?\?/', $bundle))
        ->toBe(1, 'the shipped fill bundle no longer prefers the component version over the attribute');
});
