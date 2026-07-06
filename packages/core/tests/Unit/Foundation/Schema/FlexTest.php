<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Schema\Flex;

it('defaults to a gap-4 row that grows children and stacks below md', function () {
    $flex = Flex::make();

    expect($flex->getRowClass())->toBe('md:flex-row')
        ->and($flex->getGapClass())->toBe('gap-4')
        ->and($flex->isGrow())->toBeTrue()
        ->and($flex->isWrap())->toBeFalse()
        ->and($flex->getJustifyClass())->toBe('')
        ->and($flex->getAlignClass())->toBe('');
});

it('scopes the horizontal breakpoint via from()', function () {
    expect(Flex::make()->from('sm')->getRowClass())->toBe('sm:flex-row')
        ->and(Flex::make()->from('lg')->getRowClass())->toBe('lg:flex-row');
});

it('maps justify/align to literal utilities', function () {
    expect(Flex::make()->justify('between')->getJustifyClass())->toBe('justify-between')
        ->and(Flex::make()->justify('center')->getJustifyClass())->toBe('justify-center')
        ->and(Flex::make()->align('center')->getAlignClass())->toBe('items-center')
        ->and(Flex::make()->align('baseline')->getAlignClass())->toBe('items-baseline')
        // Unknown values yield no class rather than an invalid one.
        ->and(Flex::make()->justify('bogus')->getJustifyClass())->toBe('');
});

it('clamps the gap to the Tailwind scale', function () {
    expect(Flex::make()->gap(8)->getGapClass())->toBe('gap-8')
        ->and(Flex::make()->gap(0)->getGapClass())->toBe('gap-0')
        ->and(Flex::make()->gap(99)->getGapClass())->toBe('gap-12');
});

it('toggles wrap and grow', function () {
    expect(Flex::make()->wrap()->isWrap())->toBeTrue()
        ->and(Flex::make()->grow(false)->isGrow())->toBeFalse();
});
