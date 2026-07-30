<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use NyonCode\WireTable\WireTableServiceProvider;

/**
 * The defect this closes: an app navigates from a page with no table to a page
 * with one, and gets `wireRecordSelection is not defined`. One tag in the layout
 * has to put the table's controllers on *every* page, table or not.
 */
it('ships the table controllers on a page with no table on it', function () {
    Route::get('/no-table', fn (): string => Blade::render(
        '<!DOCTYPE html><html><head>@wireStackScripts</head><body>No table here.</body></html>'
    ));

    $response = $this->get('/no-table')->assertOk();

    $response
        ->assertSee('No table here.')
        // wireRecordSelection — the factory the table wrapper's x-data references.
        ->assertSee('/wire-table/assets/selection.js', false)
        // wireRecordActions — row click/dblclick/context-menu triggers.
        ->assertSee('/wire-table/assets/records.js', false)
        // wireDropdown & friends, from the package below.
        ->assertSee('/wire-core/assets/dropdown.js', false)
        ->assertDontSee('<table', false);
});

it('cache-busts every bundle by its own mtime', function () {
    $html = Blade::render('@wireStackScripts');

    expect($html)
        ->toContain('/wire-table/assets/selection.js?id='.filemtime(
            WireTableServiceProvider::ASSETS_PATH.'/wire-table-selection.js'
        ))
        ->toContain('/wire-table/assets/records.js?id='.filemtime(
            WireTableServiceProvider::ASSETS_PATH.'/wire-table-records.js'
        ));
});

it('emits each bundle exactly once', function () {
    // The per-surface @assets partials still exist for apps without the directive;
    // the directive must not turn into a second copy of them for apps with it.
    expect(substr_count(Blade::render('@wireStackScripts'), '<script'))->toBe(4);
});
