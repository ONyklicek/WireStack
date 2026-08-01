<?php

declare(strict_types=1);

use NyonCode\WireCore\WireCoreServiceProvider;

/*
 * Where the optimistic-lock version of an editable cell may be read from.
 *
 * The cell root carries `wire:ignore.self` so a Livewire morph cannot stomp the
 * optimistic state it holds. The price is that Livewire never refreshes that
 * element's attributes either: the server keeps sending a fresh version and the
 * DOM is told to ignore it, so `data-record-version` sits at whatever the first
 * render wrote unless the component maintains it itself.
 *
 * That cost a silent bug. The fill handle read the version off the attribute, so
 * a cell edited inline advertised the version the page LOADED with; the server
 * refused the whole range as somebody else's edit, and the fill rolled it back
 * without an error anywhere — the refusal looked legitimate. Nothing in the PHP
 * suite could see it: those tests build the payload themselves instead of
 * reading it out of the DOM after an edit.
 *
 * Both halves of the fix are pinned here, because either one alone leaves the
 * two copies able to drift apart again:
 *   1. readers take the component's version, with the attribute as the fallback
 *      for a cell that has no component at all;
 *   2. the cell writes every new version to the attribute as well as its state.
 *
 * Behaviour lives in workbench/scripts/verify-fill-selection.mjs — a browser is
 * the only thing that can watch a version go stale. This is the cheap guard that
 * fails in CI when someone reaches for the attribute again, or ships a source
 * change without `npm run build:core-assets`.
 */

$source = fn (string $path): string => file_get_contents(
    dirname(WireCoreServiceProvider::ASSETS_PATH)."/resources/js/{$path}"
);

$bundle = fn (): string => file_get_contents(
    WireCoreServiceProvider::ASSETS_PATH.'/wire-core-dropdown.js'
);

test('one module owns where a cell version lives and how it is read', function () use ($source) {
    // One canonical reader, exported so nothing has to re-encode the precedence:
    // component state first, the sync node only as the fallback.
    expect($source('editable/sync.js'))
        ->toContain('export const syncNodeOf')
        ->toContain('export const versionOf')
        ->toContain('window.Alpine.$data(el)?.recordVersion ?? syncNodeOf(el)?.dataset?.recordVersion');

    // The grid keeps handing out the live version — re-exported rather than
    // re-derived, so there is exactly one definition to get wrong.
    expect($source('fill/grid.js'))
        ->toContain('export { versionOf }')
        ->toContain('version: versionOf(el)')
        ->not->toContain('version: el.dataset.recordVersion');
});

test('the value and the version are read off the sync node, never off the cell root', function () use ($source) {
    // The cell root carries `wire:ignore.self`, so Livewire stops refreshing ITS
    // attributes after the first render. Reading either of the pair from the root
    // is reading what the page loaded with — the bug this whole channel exists to
    // fix, and the one shape a reviewer will not notice returning.
    foreach (['editable/sync.js', 'fill/grid.js', 'fill/controller.js', 'dropdown.js'] as $file) {
        expect(preg_match('/\bel\.dataset\.(serverValue|recordVersion)\b/', $source($file)))
            ->toBe(0, "{$file} reads the sync pair off the cell root");
    }

    // And the cell observes the node, not itself.
    expect($source('dropdown.js'))
        ->toContain('this._sync = syncNodeOf(this.$el)')
        ->toContain('observer.observe(this._sync,');
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

test('the editable cell adopts versions in one place, and writes no attribute back', function () use ($source) {
    $dropdown = $source('dropdown.js');

    // One setter: its own commit, the conflict branch of that commit, a sibling
    // cell's broadcast, and the server→client attribute channel all go through it.
    expect($dropdown)->toContain('setRecordVersion(version) {')
        ->and(substr_count($dropdown, 'this.setRecordVersion('))->toBeGreaterThanOrEqual(4);

    // And nothing assigns the state around it.
    expect(preg_match('/this\.recordVersion\s*=/', $dropdown))
        ->toBe(1, 'only setRecordVersion() may assign this.recordVersion');

    // It must NOT write the version back to the DOM. The root it would write to is
    // the element the cell's own MutationObserver watches: touching
    // data-record-version wakes it, it re-reads the equally frozen
    // data-server-value, and "syncs" the cell back to the value the page loaded
    // with — the edit reaches the database and disappears off the screen a second
    // later. Keeping the attributes honest means writing the pair or neither.
    expect(preg_match('/setRecordVersion\(version\) \{(?:(?!\n    \},)[\s\S])*dataset\.recordVersion\s*=/', $dropdown))
        ->toBe(0, 'setRecordVersion() writes data-record-version back and will revert the cell it just saved');
});

test('the shipped bundle carries the state-first version read', function () use ($bundle) {
    // Also the rebuild guard: esbuild leaves property names alone, so these
    // survive minification and the assertions fail if dist drifts from source
    // because `npm run build:core-assets` was not run.
    expect($bundle())
        ->toContain('setRecordVersion')
        ->toContain('recordVersion');

    // The precedence itself, past minification: the state is consulted before
    // the attribute in the same expression.
    expect(preg_match('/\$data\([^)]*\)\??\.\w*[Rr]ecordVersion\s*\?\?/', $bundle()))
        ->toBe(1, 'the shipped bundle no longer prefers the component version over the attribute');
});
