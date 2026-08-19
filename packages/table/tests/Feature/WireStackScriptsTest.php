<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

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
        ->assertSee('/vendor/wire-table/wire-table-selection.js', false)
        // wireRecordActions — row click/dblclick/context-menu triggers.
        ->assertSee('/vendor/wire-table/wire-table-records.js', false)
        // wireDropdown & friends, from the package below.
        ->assertSee('/vendor/wire-core/wire-core-dropdown.js', false)
        ->assertDontSee('<table', false);
});

it('cache-busts every bundle by its own mtime', function () {
    // The mtime is the mirrored copy's — PublishedAssets writes it into
    // public/vendor and `copy()` stamps it — which is what moves the query string
    // on an upgrade and makes data-navigate-track full-reload the app.
    $html = Blade::render('@wireStackScripts');

    expect($html)
        ->toContain('/vendor/wire-table/wire-table-selection.js?id='.filemtime(
            public_path('vendor/wire-table/wire-table-selection.js')
        ))
        ->toContain('/vendor/wire-table/wire-table-records.js?id='.filemtime(
            public_path('vendor/wire-table/wire-table-records.js')
        ));
});

it('emits each bundle exactly once', function () {
    // The per-surface @assets partials still exist for apps without the directive;
    // the directive must not turn into a second copy of them for apps with it.
    //
    // Eight: dropdown, copy and chart from core, image and fields from forms,
    // records, selection and live from the table. They ship on every page for the
    // same reason: the behaviour a table's markup reaches for has to exist before
    // a wire:navigate visit renders the table, and the page that visit is made
    // *from* may have no table on it at all.
    //
    // Chart is the one that moved. It was held back as an optional heavy body,
    // which it is not — 671 bytes of Alpine registrar around the app's own
    // Chart.js — and delivering a registrar late is precisely what ADR 0024
    // forbids.
    //
    // `wire-forms-fields.js` is the newest, and is here for the same rule: it
    // registers the date/time pickers, tags, rating and the two editors, so it is
    // a registrar rather than a body.
    expect(substr_count(Blade::render('@wireStackScripts'), '<script'))->toBe(8);
});
