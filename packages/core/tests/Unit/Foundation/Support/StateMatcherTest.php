<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Support\StateMatcher;

test('matches performs strict equality for scalar expectations', function () {
    expect(StateMatcher::matches('business', 'business'))->toBeTrue()
        ->and(StateMatcher::matches('business', 'individual'))->toBeFalse()
        ->and(StateMatcher::matches('1', 1))->toBeFalse();
});

test('matches performs an in-array check for array expectations', function () {
    expect(StateMatcher::matches('nonprofit', ['business', 'nonprofit']))->toBeTrue()
        ->and(StateMatcher::matches('individual', ['business', 'nonprofit']))->toBeFalse();
});
