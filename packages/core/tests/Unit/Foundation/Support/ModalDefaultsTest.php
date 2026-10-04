<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use NyonCode\WireCore\Foundation\Enums\ModalWidth;
use NyonCode\WireCore\Foundation\Support\ModalDefaults;

/*
 * The one owner of what a modal does when nobody said: `wire-core.modals`.
 */

it('answers the configured widths and close behaviour', function () {
    config()->set('wire-core.modals', [
        'default_width' => 'xl',
        'slide_over_width' => '2xl',
        'close_on_click_away' => false,
        'close_on_escape' => false,
    ]);

    expect(ModalDefaults::width())->toBe('xl')
        ->and(ModalDefaults::slideOverWidth())->toBe('2xl')
        ->and(ModalDefaults::closeOnClickAway())->toBeFalse()
        ->and(ModalDefaults::closeOnEscape())->toBeFalse();
});

it('accepts a width configured as the enum', function () {
    config()->set('wire-core.modals.default_width', ModalWidth::ThreeXl);

    expect(ModalDefaults::width())->toBe('3xl');
});

it('keeps the shipped width for an empty or non-string value', function (mixed $width) {
    config()->set('wire-core.modals.slide_over_width', $width);

    expect(ModalDefaults::slideOverWidth())->toBe('md');
})->with(['empty' => '', 'null' => null, 'number' => 42]);

it('answers the shipped defaults without a container', function () {
    $container = Container::getInstance();
    Container::setInstance(new Container);

    try {
        expect(ModalDefaults::width())->toBe('md')
            ->and(ModalDefaults::closeOnClickAway())->toBeTrue()
            ->and(ModalDefaults::closeOnEscape())->toBeTrue();
    } finally {
        Container::setInstance($container);
    }
});
