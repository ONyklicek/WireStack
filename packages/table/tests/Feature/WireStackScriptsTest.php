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
    // Asserted as the list rather than as a count, because a count that moves
    // says only that it moved: the ten below are named so a failure names the
    // newcomer and this comment can say why it ships.
    //
    // They all ship on every page for one reason: the behaviour a page's markup
    // reaches for has to exist before a wire:navigate visit renders it, and the
    // page that visit is made *from* may have none of that markup on it.
    //
    //   core    dropdown (every shared Alpine controller), copy, chart, notifications
    //   forms   image, fields (the date/time pickers, tags, rating, the editors)
    //   table   records, selection, live, fill
    //
    // Chart and notifications are the two that look like optional heavy bodies
    // and are not: 671 bytes and 1.2 kB of Alpine registrar around `window.Chart`
    // and `window.Echo`, both of them the consuming app's own dependency and
    // neither shipped here. Delivering a registrar late is precisely what ADR
    // 0024 forbids, and the bell can arrive on a navigate visit like anything
    // else.
    //
    // `wire-table-fill.js` added no page weight when it landed: it left
    // `wire-core-dropdown.js`, which shrank by the same 9 KB (ADR 0025 § step
    // 10). What changed is who pays — an application with no table now ships
    // nine bundles here and none of them carries the fill handle.
    preg_match_all('/src="[^"]*\/vendor\/[^"\/]+\/([^"\/?]+\.js)/', Blade::render('@wireStackScripts'), $matches);

    $emitted = $matches[1];
    sort($emitted);

    expect($emitted)->toBe([
        'wire-core-chart.js',
        'wire-core-copy.js',
        'wire-core-dropdown.js',
        'wire-core-notifications.js',
        'wire-forms-fields.js',
        'wire-forms-image.js',
        'wire-table-fill.js',
        'wire-table-live.js',
        'wire-table-records.js',
        'wire-table-selection.js',
    ]);
});
