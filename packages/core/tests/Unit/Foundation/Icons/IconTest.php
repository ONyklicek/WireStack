<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Icons\Icon;

it('resolves canonical heroicon names', function () {
    expect(Icon::academicCap->value())->toBe('academic-cap')
        ->and(Icon::arrowDownTray->value())->toBe('arrow-down-tray')
        ->and(Icon::qrCode->value())->toBe('qr-code')
        ->and(Icon::h1->value())->toBe('h1')
        ->and(Icon::xMark->value())->toBe('x-mark');
});

it('resolves wire friendly aliases', function () {
    expect(Icon::pen->value())->toBe('pencil')
        ->and(Icon::download->value())->toBe('arrow-down-tray')
        ->and(Icon::mail->value())->toBe('envelope')
        ->and(Icon::more->value())->toBe('ellipsis-vertical')
        ->and(Icon::filter->value())->toBe('funnel');
});

it('resolves icons from case names and values', function () {
    expect(Icon::tryResolve('arrowDownTray'))->toBe(Icon::arrowDownTray)
        ->and(Icon::tryResolve('arrow-down-tray'))->toBe(Icon::arrowDownTray)
        ->and(Icon::tryResolve('pen'))->toBe(Icon::pen)
        ->and(Icon::tryResolve('pencil'))->toBe(Icon::pencil)
        ->and(Icon::tryResolve('missing-icon'))->toBeNull();
});

it('resolves strings and enum instances to icon values', function () {
    expect(Icon::resolve(Icon::pen))->toBe('pencil')
        ->and(Icon::resolve('arrow-down-tray'))->toBe('arrow-down-tray')
        ->and(Icon::resolve('missing-icon'))->toBe('missing-icon');
});

it('returns unique values', function () {
    $values = Icon::values();

    expect($values)->toContain('academic-cap')
        ->toContain('pencil')
        ->toContain('arrow-down-tray')
        ->toContain('ellipsis-horizontal')
        ->and($values)->toBe(array_values(array_unique($values)));
});
