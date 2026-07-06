<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Enums\Breakpoint;

it('lists all breakpoint values in min-width order', function () {
    expect(Breakpoint::values())->toBe(['sm', 'md', 'lg', 'xl', '2xl']);
});

it('resolves a raw token to the matching case', function () {
    expect(Breakpoint::resolve('sm'))->toBe(Breakpoint::Sm)
        ->and(Breakpoint::resolve('md'))->toBe(Breakpoint::Md)
        ->and(Breakpoint::resolve('lg'))->toBe(Breakpoint::Lg)
        ->and(Breakpoint::resolve('xl'))->toBe(Breakpoint::Xl)
        ->and(Breakpoint::resolve('2xl'))->toBe(Breakpoint::TwoXl);
});

it('passes an already-resolved enum through resolve unchanged', function () {
    expect(Breakpoint::resolve(Breakpoint::Lg))->toBe(Breakpoint::Lg);
});

it('falls back to the default for unknown tokens', function () {
    expect(Breakpoint::resolve('nope'))->toBe(Breakpoint::Md)
        ->and(Breakpoint::resolve('nope', Breakpoint::Lg))->toBe(Breakpoint::Lg);
});

it('tries a token, returning null when unknown', function () {
    expect(Breakpoint::tryFromToken('xl'))->toBe(Breakpoint::Xl)
        ->and(Breakpoint::tryFromToken('default'))->toBeNull()
        ->and(Breakpoint::tryFromToken(''))->toBeNull();
});

it('builds the Tailwind variant prefix', function () {
    expect(Breakpoint::Sm->prefix())->toBe('sm:')
        ->and(Breakpoint::TwoXl->prefix())->toBe('2xl:');
});

it('maps every case to its table-cell class', function () {
    expect(Breakpoint::Sm->tableCellClass())->toBe('sm:table-cell')
        ->and(Breakpoint::Md->tableCellClass())->toBe('md:table-cell')
        ->and(Breakpoint::Lg->tableCellClass())->toBe('lg:table-cell')
        ->and(Breakpoint::Xl->tableCellClass())->toBe('xl:table-cell')
        ->and(Breakpoint::TwoXl->tableCellClass())->toBe('2xl:table-cell');
});

it('maps every case to its hidden-at class', function () {
    expect(Breakpoint::Sm->hiddenAtClass())->toBe('sm:hidden')
        ->and(Breakpoint::Md->hiddenAtClass())->toBe('md:hidden')
        ->and(Breakpoint::Lg->hiddenAtClass())->toBe('lg:hidden')
        ->and(Breakpoint::Xl->hiddenAtClass())->toBe('xl:hidden')
        ->and(Breakpoint::TwoXl->hiddenAtClass())->toBe('2xl:hidden');
});

it('maps every case to its block-from class', function () {
    expect(Breakpoint::Sm->blockFromClass())->toBe('hidden sm:block')
        ->and(Breakpoint::Md->blockFromClass())->toBe('hidden md:block')
        ->and(Breakpoint::Lg->blockFromClass())->toBe('hidden lg:block')
        ->and(Breakpoint::Xl->blockFromClass())->toBe('hidden xl:block')
        ->and(Breakpoint::TwoXl->blockFromClass())->toBe('hidden 2xl:block');
});

it('maps every case to its inline-from class', function () {
    expect(Breakpoint::Sm->inlineFromClass())->toBe('hidden sm:inline')
        ->and(Breakpoint::Md->inlineFromClass())->toBe('hidden md:inline')
        ->and(Breakpoint::Lg->inlineFromClass())->toBe('hidden lg:inline')
        ->and(Breakpoint::Xl->inlineFromClass())->toBe('hidden xl:inline')
        ->and(Breakpoint::TwoXl->inlineFromClass())->toBe('hidden 2xl:inline');
});
