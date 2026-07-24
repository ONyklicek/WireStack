<?php

declare(strict_types=1);

use NyonCode\WireForms\Concerns\CanBeSearchable;

class CanBeSearchableHost
{
    use CanBeSearchable;
}

test('searchable defaults to false and toggles fluently', function () {
    $host = new CanBeSearchableHost;

    expect($host->isSearchable())->toBeFalse();

    expect($host->searchable())->toBe($host);
    expect($host->isSearchable())->toBeTrue();

    $host->searchable(false);
    expect($host->isSearchable())->toBeFalse();
});

test('searchPrompt falls back to a non-empty string then round-trips', function () {
    $host = new CanBeSearchableHost;

    expect($host->getSearchPrompt())->toBeString()->not->toBe('');

    expect($host->searchPrompt('Find…'))->toBe($host);
    expect($host->getSearchPrompt())->toBe('Find…');
});
