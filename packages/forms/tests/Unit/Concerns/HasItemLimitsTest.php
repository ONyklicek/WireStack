<?php

declare(strict_types=1);

use NyonCode\WireForms\Concerns\HasItemLimits;

class HasItemLimitsHost
{
    use HasItemLimits;
}

test('minItems and maxItems round-trip and are fluent', function () {
    $host = new HasItemLimitsHost;

    expect($host->getMinItems())->toBeNull()
        ->and($host->getMaxItems())->toBeNull();

    expect($host->minItems(2))->toBe($host)
        ->and($host->maxItems(8))->toBe($host);

    expect($host->getMinItems())->toBe(2)
        ->and($host->getMaxItems())->toBe(8);

    $host->minItems(null)->maxItems(null);

    expect($host->getMinItems())->toBeNull()
        ->and($host->getMaxItems())->toBeNull();
});
