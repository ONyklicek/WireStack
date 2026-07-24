<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Concerns\CanBeCopyable;
use NyonCode\WireCore\Foundation\Concerns\HasImageConfig;

class CanBeCopyableHost
{
    use CanBeCopyable;
}

class HasImageConfigHost
{
    use HasImageConfig;
}

test('copyable defaults to false and toggles fluently', function () {
    $host = new CanBeCopyableHost;

    expect($host->isCopyable())->toBeFalse();

    expect($host->copyable())->toBe($host);
    expect($host->isCopyable())->toBeTrue();

    $host->copyable(false);
    expect($host->isCopyable())->toBeFalse();
});

test('image config knobs round-trip and are fluent', function () {
    $host = new HasImageConfigHost;

    expect($host->getDisk())->toBeNull()
        ->and($host->isCircular())->toBeFalse()
        ->and($host->isStacked())->toBeFalse()
        ->and($host->getDefaultImageUrl())->toBeNull();

    expect($host->disk('s3'))->toBe($host)
        ->and($host->circular())->toBe($host)
        ->and($host->stacked())->toBe($host)
        ->and($host->defaultImageUrl('/img/fallback.png'))->toBe($host);

    expect($host->getDisk())->toBe('s3')
        ->and($host->isCircular())->toBeTrue()
        ->and($host->isStacked())->toBeTrue()
        ->and($host->getDefaultImageUrl())->toBe('/img/fallback.png');

    $host->circular(false)->stacked(false)->disk(null)->defaultImageUrl(null);

    expect($host->isCircular())->toBeFalse()
        ->and($host->isStacked())->toBeFalse()
        ->and($host->getDisk())->toBeNull()
        ->and($host->getDefaultImageUrl())->toBeNull();
});
