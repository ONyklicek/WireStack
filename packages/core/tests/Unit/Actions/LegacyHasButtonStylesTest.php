<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Concerns\HasButtonStyles;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasColor;

/**
 * The Actions\Concerns\HasButtonStyles trait is the canonical target behind the
 * deprecated WireCore\Concerns\HasButtonStyles shim. It is exercised here in
 * isolation through a double that also pulls in the HasColor color helpers it
 * depends on.
 */
function buttonStyleDouble(): object
{
    return new class
    {
        use HasButtonStyles;
        use HasColor;

        public function publicButtonClasses(bool $isIconButton = false): string
        {
            return $this->getButtonClasses($isIconButton);
        }

        public function publicSizeClasses(bool $isIconButton = false): string
        {
            return $this->getButtonSizeClasses($isIconButton);
        }
    };
}

it('exposes color, size and outlined defaults', function () {
    $d = buttonStyleDouble();

    expect($d->getColor())->toBe(Color::Primary->value)
        ->and($d->getSize())->toBe('sm')
        ->and($d->isOutlined())->toBeFalse();
});

it('accepts color as a string or Color enum and resets to null', function () {
    expect(buttonStyleDouble()->color('danger')->getColor())->toBe('danger')
        ->and(buttonStyleDouble()->color(Color::Success)->getColor())->toBe(Color::Success->value)
        ->and(buttonStyleDouble()->color(null)->getColor())->toBe(Color::Primary->value);
});

it('accepts size and outlined toggles', function () {
    expect(buttonStyleDouble()->size('lg')->getSize())->toBe('lg')
        ->and(buttonStyleDouble()->size(null)->getSize())->toBe('sm')
        ->and(buttonStyleDouble()->outlined()->isOutlined())->toBeTrue();
});

it('builds solid, outlined and icon button classes', function () {
    $solid = buttonStyleDouble()->publicButtonClasses();
    $outlined = buttonStyleDouble()->outlined()->publicButtonClasses();
    $icon = buttonStyleDouble()->publicButtonClasses(true);

    expect($solid)->toContain('inline-flex')->toContain('px-2.5')
        ->and($outlined)->toContain('inline-flex')
        ->and($icon)->toContain('p-1.5');
});

it('maps every size to button size classes', function () {
    foreach (['xs', 'sm', 'md', 'lg', 'xl'] as $size) {
        expect(buttonStyleDouble()->size($size)->publicSizeClasses())->toBeString()->not->toBeEmpty()
            ->and(buttonStyleDouble()->size($size)->publicSizeClasses(true))->toBeString()->not->toBeEmpty();
    }
});
