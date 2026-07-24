<?php

declare(strict_types=1);

use NyonCode\WireForms\Concerns\CanBeMultiple;

class CanBeMultipleHost
{
    use CanBeMultiple;
}

test('multiple defaults to false and toggles fluently', function () {
    $host = new CanBeMultipleHost;

    expect($host->isMultiple())->toBeFalse();

    expect($host->multiple())->toBe($host);
    expect($host->isMultiple())->toBeTrue();

    $host->multiple(false);
    expect($host->isMultiple())->toBeFalse();
});
