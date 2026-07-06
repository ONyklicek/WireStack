<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Enums\FontWeight;

it('lists all weight values from lightest to heaviest', function () {
    expect(FontWeight::values())->toBe([
        'thin', 'extralight', 'light', 'normal', 'medium', 'semibold', 'bold', 'extrabold', 'black',
    ]);
});

it('resolves a raw token to the matching case', function () {
    expect(FontWeight::resolve('semibold'))->toBe(FontWeight::SemiBold)
        ->and(FontWeight::resolve('extralight'))->toBe(FontWeight::ExtraLight)
        ->and(FontWeight::resolve('extrabold'))->toBe(FontWeight::ExtraBold)
        ->and(FontWeight::resolve('black'))->toBe(FontWeight::Black);
});

it('passes an already-resolved enum through resolve unchanged', function () {
    expect(FontWeight::resolve(FontWeight::Bold))->toBe(FontWeight::Bold);
});

it('falls back to the default for unknown tokens', function () {
    expect(FontWeight::resolve('heavier'))->toBe(FontWeight::Normal)
        ->and(FontWeight::resolve('heavier', FontWeight::Bold))->toBe(FontWeight::Bold);
});
