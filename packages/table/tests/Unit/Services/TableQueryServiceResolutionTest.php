<?php

declare(strict_types=1);

use NyonCode\WireTable\Services\TableQueryService;

/*
 * TableQueryService moved out of Concerns/ (a final class in a directory the
 * coding standard reserves for traits) into Services/. The deprecated alias on
 * the old FQCN carried that move through 1.x and fell in 2.0 with the rest of
 * the shims.
 */

test('the container hands out a fresh service, never a shared one', function () {
    // The service memoises the last query plan, so a singleton would leak one
    // table's plan into the next.
    expect(app(TableQueryService::class))->not->toBe(app(TableQueryService::class));
});
