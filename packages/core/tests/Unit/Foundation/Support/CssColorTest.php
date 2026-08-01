<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Support\CssColor;

it('accepts hex colors of every length', function (string $color) {
    expect(CssColor::sanitize($color))->toBe($color);
})->with(['#fff', '#FFFF', '#1a2b3c', '#1a2b3cff']);

it('accepts the rgb/hsl function family', function (string $color) {
    expect(CssColor::sanitize($color))->toBe($color);
})->with([
    'rgb(255, 0, 0)',
    'rgba(255, 0, 0, 0.5)',
    'rgb(255 0 0 / 50%)',
    'hsl(210, 50%, 40%)',
    'hsla(210 50% 40% / 0.2)',
]);

it('accepts bare css keywords', function (string $color) {
    expect(CssColor::sanitize($color))->toBe($color);
})->with(['red', 'rebeccapurple', 'transparent', 'currentColor']);

it('trims surrounding whitespace', function () {
    expect(CssColor::sanitize("  #abc \n"))->toBe('#abc');
});

it('rejects values that would smuggle extra declarations into the style attribute', function (string $color) {
    expect(CssColor::sanitize($color))->toBeNull();
})->with([
    'red; background-image: url(https://evil.test/x)',
    'url(https://evil.test/x)',
    '#fff"',
    "red'",
    'rgb(0,0,0);}',
    'expression(alert(1))',
    'rgb(var(--x))',
    '#12345',
    'a',
]);

it('rejects empty, blank and non-string values', function (mixed $value) {
    expect(CssColor::sanitize($value))->toBeNull();
})->with([[''], ['   '], [null], [123], [[]], [true]]);

it('accepts a stringable object', function () {
    $stringable = new class
    {
        public function __toString(): string
        {
            return '#0f0';
        }
    };

    expect(CssColor::sanitize($stringable))->toBe('#0f0');
});
