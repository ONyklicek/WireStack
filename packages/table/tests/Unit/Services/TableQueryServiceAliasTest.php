<?php

declare(strict_types=1);

use NyonCode\WireTable\Services\TableQueryService;

/*
 * TableQueryService moved out of Concerns/ (a final class in a directory the
 * coding standard reserves for traits) into Services/. Nothing in docs asks a
 * user to construct it, but the old FQCN was published, so a deprecated alias
 * carries it to v2.0 — pinned here because an alias nobody exercises is an alias
 * that silently stops working.
 */

test('the deprecated Concerns FQCN still resolves to the service', function () {
    $legacy = 'NyonCode\WireTable\Concerns\TableQueryService';

    expect(class_exists($legacy))->toBeTrue()
        ->and(new $legacy)->toBeInstanceOf(TableQueryService::class);
});

test('the container hands out a fresh service, never a shared one', function () {
    // The service memoises the last query plan, so a singleton would leak one
    // table's plan into the next.
    expect(app(TableQueryService::class))->not->toBe(app(TableQueryService::class));
});
