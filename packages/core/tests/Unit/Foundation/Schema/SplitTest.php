<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Schema\Split;

it('defaults to a gap-4 row that grows children and stacks below md', function () {
    $split = Split::make();

    expect($split->getRowClass())->toBe('md:flex-row')
        ->and($split->getGapClass())->toBe('gap-4')
        ->and($split->isGrow())->toBeTrue()
        ->and($split->isWrap())->toBeFalse()
        ->and($split->getJustifyClass())->toBe('')
        ->and($split->getAlignClass())->toBe('');
});

it('scopes the horizontal breakpoint via from()', function () {
    expect(Split::make()->from('sm')->getRowClass())->toBe('sm:flex-row')
        ->and(Split::make()->from('lg')->getRowClass())->toBe('lg:flex-row');
});

it('maps justify/align to literal utilities', function () {
    expect(Split::make()->justify('between')->getJustifyClass())->toBe('justify-between')
        ->and(Split::make()->justify('center')->getJustifyClass())->toBe('justify-center')
        ->and(Split::make()->align('center')->getAlignClass())->toBe('items-center')
        ->and(Split::make()->align('baseline')->getAlignClass())->toBe('items-baseline')
        // Unknown values yield no class rather than an invalid one.
        ->and(Split::make()->justify('bogus')->getJustifyClass())->toBe('');
});

it('clamps the gap to the Tailwind scale', function () {
    expect(Split::make()->gap(8)->getGapClass())->toBe('gap-8')
        ->and(Split::make()->gap(0)->getGapClass())->toBe('gap-0')
        ->and(Split::make()->gap(99)->getGapClass())->toBe('gap-12');
});

it('toggles wrap and grow', function () {
    expect(Split::make()->wrap()->isWrap())->toBeTrue()
        ->and(Split::make()->grow(false)->isGrow())->toBeFalse();
});
