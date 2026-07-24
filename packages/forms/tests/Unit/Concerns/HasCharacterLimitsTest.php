<?php

declare(strict_types=1);

use NyonCode\WireForms\Concerns\HasCharacterLimits;

class HasCharacterLimitsHost
{
    use HasCharacterLimits;
}

test('minLength and maxLength round-trip and are fluent', function () {
    $host = new HasCharacterLimitsHost;

    expect($host->getMinLength())->toBeNull()
        ->and($host->getMaxLength())->toBeNull();

    expect($host->minLength(3))->toBe($host)
        ->and($host->maxLength(255))->toBe($host);

    expect($host->getMinLength())->toBe(3)
        ->and($host->getMaxLength())->toBe(255);

    $host->minLength(null)->maxLength(null);

    expect($host->getMinLength())->toBeNull()
        ->and($host->getMaxLength())->toBeNull();
});
