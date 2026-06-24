<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Relations\Contracts\Segment;
use NyonCode\WireCore\Core\Relations\MorphSegment;

it('is a non-terminal relation segment', function () {
    $segment = new MorphSegment('commentable', 'App\\Post');

    expect($segment)->toBeInstanceOf(Segment::class)
        ->and($segment->getName())->toBe('commentable')
        ->and($segment->morphType)->toBe('App\\Post')
        ->and($segment->isTerminal())->toBeFalse();
});

it('allows a null morph type', function () {
    $segment = new MorphSegment('imageable');

    expect($segment->getName())->toBe('imageable')
        ->and($segment->morphType)->toBeNull();
});
