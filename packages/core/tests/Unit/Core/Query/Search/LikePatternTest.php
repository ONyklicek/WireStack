<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Query\Search\LikePattern;

it('wraps a term as a contains pattern', function () {
    expect(LikePattern::contains('john'))->toBe('%john%');
});

it('escapes the LIKE metacharacters a user can type', function () {
    // Left raw, `50%` matches every row: the percent sign is the wildcard.
    expect(LikePattern::contains('50%'))->toBe('%50!%%')
        ->and(LikePattern::contains('a_b'))->toBe('%a!_b%');
});

it('escapes the escape character itself', function () {
    expect(LikePattern::contains('a!b'))->toBe('%a!!b%');
});

it('leaves a backslash alone', function () {
    // Nothing declares the backslash as an escape character any more, so it is
    // ordinary text — and doubling it would search for two of them.
    expect(LikePattern::contains('C:\\dir'))->toBe('%C:\\dir%');
});

it('leaves star and question mark literal when wildcards are off', function () {
    expect(LikePattern::contains('nov*'))->toBe('%nov*%')
        ->and(LikePattern::contains('a?b'))->toBe('%a?b%');
});

it('translates star and question mark when wildcards are on', function () {
    expect(LikePattern::contains('nov*', wildcards: true))->toBe('%nov%%')
        // The user's `?` becomes the real single-character wildcard, while the
        // `_` they could equally have typed stays escaped and literal.
        ->and(LikePattern::contains('a?b', wildcards: true))->toBe('%a_b%')
        ->and(LikePattern::contains('a_b', wildcards: true))->toBe('%a!_b%');
});

it('still escapes a typed percent when wildcards are on', function () {
    // `*` is the user's wildcard; `%` stays literal, so the two never collide.
    expect(LikePattern::contains('50%*', wildcards: true))->toBe('%50!%%%');
});

it('escapes an empty term to nothing but the wrapper', function () {
    expect(LikePattern::contains(''))->toBe('%%');
});

it('exposes an escape clause naming its escape character', function () {
    expect(LikePattern::escapeClause())->toBe(" ESCAPE '!'")
        ->and(LikePattern::ESCAPE)->toBe('!');
});
