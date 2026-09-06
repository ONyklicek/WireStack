<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\ValueObjects\DialingCode;
use NyonCode\WireCore\Foundation\ValueObjects\DialingCodes;

/**
 * The dialling vocabulary, owned once for every phone surface: the field that
 * edits a number, the column that shows one, and the rule that validates
 * either. Matching and writing live together here for the same reason
 * MoneyFormat's parse and format do — they are inverses, and a surface holding
 * one of them would drift from the other.
 */
it('matches a number to the longest prefix that fits', function () {
    expect(DialingCodes::match('+420123456789')?->country)->toBe('CZ')
        // +1 is the whole North American plan; it must not swallow +1868.
        ->and(DialingCodes::match('+12125551234')?->country)->toBe('US')
        // Spacing is not part of the number.
        ->and(DialingCodes::match('+420 123 456 789')?->country)->toBe('CZ')
        ->and(DialingCodes::match('+9995551234'))->toBeNull();
});

it('answers for one country, and refuses one it does not carry', function () {
    expect(DialingCodes::find('cz')?->dialingCode)->toBe('+420')
        ->and(DialingCodes::find('CZ')?->minDigits)->toBe(9)
        ->and(DialingCodes::find('XX'))->toBeNull();
});

it('offers the named countries in the order they were asked for', function () {
    expect(array_map(fn (DialingCode $c): string => $c->country, DialingCodes::only(['sk', 'CZ', 'XX'])))
        ->toBe(['SK', 'CZ'])
        ->and(DialingCodes::only([]))->toBe([])
        ->and(count(DialingCodes::all()))->toBeGreaterThan(50);
});

it('writes a number the way it is read', function () {
    expect(DialingCodes::written('+420123456789'))->toBe('+420 123 456 789')
        // Ten digits: the trailing one joins the group before it.
        ->and(DialingCodes::written('+12125551234'))->toBe('+1 212 555 1234')
        // Already spaced, and spaced the same way afterwards.
        ->and(DialingCodes::written('+420 123 456 789'))->toBe('+420 123 456 789');
});

it('leaves a number it cannot place alone', function () {
    // Better an unformatted number than one grouped against a prefix that is
    // not really there.
    expect(DialingCodes::written('+9995551234'))->toBe('+9995551234')
        ->and(DialingCodes::written('  '))->toBe('')
        ->and(DialingCodes::written('not a number'))->toBe('not a number');
});

it('computes a flag from the ISO code rather than storing one', function () {
    $czech = DialingCodes::find('CZ');

    expect($czech?->flag())->toBe('🇨🇿')
        ->and($czech?->label())->toBe('🇨🇿 +420');
});

it('knows how many digits a country issues', function () {
    $czech = DialingCodes::find('CZ');

    expect($czech?->accepts(9))->toBeTrue()
        ->and($czech?->accepts(8))->toBeFalse()
        ->and($czech?->accepts(10))->toBeFalse();
});

it('groups a national number in threes', function () {
    $czech = DialingCodes::find('CZ');

    expect($czech?->write('123456789'))->toBe('123 456 789')
        ->and($czech?->write('2125551234'))->toBe('212 555 1234')
        ->and($czech?->write('12'))->toBe('12')
        ->and($czech?->write(''))->toBe('');
});
