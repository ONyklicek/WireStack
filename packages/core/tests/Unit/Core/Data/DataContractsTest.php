<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Capabilities\CapabilitySet;
use NyonCode\WireCore\Core\Data\PagingMode;
use NyonCode\WireCore\Core\Data\PagingRequest;
use NyonCode\WireCore\Exceptions\UnsupportedQueryAspectException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

// ─── PagingRequest ──────────────────────────────────────────────────────

it('builds a length-aware request with its page and page name', function () {
    $paging = PagingRequest::lengthAware(25, 3);

    expect($paging->mode)->toBe(PagingMode::LengthAware)
        ->and($paging->perPage)->toBe(25)
        ->and($paging->page)->toBe(3)
        ->and($paging->cursor)->toBeNull()
        ->and($paging->pageName)->toBe('page');
});

it('builds a simple request, which differs from length-aware only by mode', function () {
    // Simple paging is the mode that issues no COUNT(*); everything else about
    // the request is the same, which is why they share a shape rather than
    // getting two classes.
    $paging = PagingRequest::simple(10, 2);

    expect($paging->mode)->toBe(PagingMode::Simple)
        ->and($paging->perPage)->toBe(10)
        ->and($paging->page)->toBe(2);
});

it('builds a cursor request that carries a cursor instead of a page', function () {
    $paging = PagingRequest::cursor(50, 'eyJpZCI6NDJ9');

    expect($paging->mode)->toBe(PagingMode::Cursor)
        ->and($paging->cursor)->toBe('eyJpZCI6NDJ9')
        ->and($paging->pageName)->toBe('cursor')
        // Keyset paging has no page number; the field keeps its default rather
        // than pretending to mean something.
        ->and($paging->page)->toBe(1);
});

it('starts a cursor request with no cursor at all', function () {
    expect(PagingRequest::cursor(50)->cursor)->toBeNull();
});

it('accepts a custom page name, so two tables can page independently', function () {
    expect(PagingRequest::lengthAware(15, 1, 'ordersPage')->pageName)->toBe('ordersPage')
        ->and(PagingRequest::simple(15, 1, 'logsPage')->pageName)->toBe('logsPage');
});

it('covers exactly the modes paginateQuery already switches on', function () {
    // If a fourth paging shape ever appears here, WithTable::paginateQuery() is
    // the switch that has to grow with it.
    expect(array_map(fn (PagingMode $m): string => $m->value, PagingMode::cases()))
        ->toBe(['length_aware', 'simple', 'cursor']);
});

// ─── Capability ─────────────────────────────────────────────────────────

it('carries the four data-source aspects on the canonical enum', function () {
    // The point of these living on Capability rather than on a second
    // DataSourceCapabilities class: one vocabulary, which QueryPlanner and
    // Column already read.
    expect(Capability::tryFrom('joinable'))->toBe(Capability::Joinable)
        ->and(Capability::tryFrom('paginable'))->toBe(Capability::Paginable)
        ->and(Capability::tryFrom('sub_rows'))->toBe(Capability::SubRows)
        ->and(Capability::tryFrom('change_token'))->toBe(Capability::ChangeToken);
});

it('lets a set answer for a data-source aspect like any other', function () {
    $set = new CapabilitySet(Capability::Sortable, Capability::Paginable);

    expect($set->has(Capability::Paginable))->toBeTrue()
        ->and($set->has(Capability::ChangeToken))->toBeFalse()
        ->and($set->add(Capability::ChangeToken)->has(Capability::ChangeToken))->toBeTrue();
});

// ─── UnsupportedQueryAspectException ────────────────────────────────────

it('names the aspect and the source, and says both ways out', function () {
    $e = UnsupportedQueryAspectException::notDeclared('sortable', 'ApiDataSource');

    expect($e->getMessage())->toContain('ApiDataSource')
        ->toContain('[sortable]')
        ->toContain('capabilities()')
        ->and($e)->toBeInstanceOf(WireException::class);
});
