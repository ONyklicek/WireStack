<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Contracts\Enum\HasIcon;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Support\IconResolver;

enum IrPlainStatus: string
{
    case Active = 'active';
}

enum IrRichStatus: string implements HasIcon
{
    case Open = 'open';
    case Closed = 'closed';

    public function getIcon(): string|Icon|null
    {
        return match ($this) {
            self::Open => 'check',
            self::Closed => Icon::pen,
        };
    }
}

beforeEach(function () {
    $this->resolver = new IconResolver;
});

test('the callback wins over every other rung', function () {
    expect($this->resolver->resolve('active', ['active' => 'clock'], fn () => 'star'))
        ->toBe('star');
});

test('a callback returning an Icon enum is unwrapped to its name', function () {
    expect($this->resolver->resolve('active', null, fn () => Icon::pen))
        ->toBe(Icon::pen->value());
});

test('a callback returning null falls through to the map', function () {
    expect($this->resolver->resolve('active', ['active' => 'clock'], fn () => null))
        ->toBe('clock');
});

test('the map is indexed by the scalar value of an enum state', function () {
    expect($this->resolver->resolve(IrPlainStatus::Active, ['active' => 'clock']))
        ->toBe('clock');
});

test('a map value may be an Icon enum', function () {
    expect($this->resolver->resolve('active', ['active' => Icon::pen]))
        ->toBe(Icon::pen->value());
});

test('an enum state carrying the HasIcon contract resolves without a map', function () {
    expect($this->resolver->resolve(IrRichStatus::Open))->toBe('check')
        ->and($this->resolver->resolve(IrRichStatus::Closed))->toBe(Icon::pen->value());
});

test('an explicit map beats the enum contract', function () {
    expect($this->resolver->resolve(IrRichStatus::Open, ['open' => 'clock']))->toBe('clock');
});

test('the default answers when no rung matches', function () {
    expect($this->resolver->resolve('unknown', ['active' => 'clock'], null, 'star'))->toBe('star')
        ->and($this->resolver->resolve('unknown'))->toBeNull();
});

/*
 * The reason this resolver exists. Every copy of the ladder indexed the map with
 * an unguarded isset(), which throws rather than answering false — so a column
 * over a JSON-cast attribute fatalled out of renderCell() with nothing
 * configured. Both shapes are pinned.
 */
test('a non-scalar state cannot index the map and falls through to the default', function () {
    expect($this->resolver->resolve(['an', 'array'], ['active' => 'clock'], null, 'star'))->toBe('star')
        ->and($this->resolver->resolve(new stdClass, ['active' => 'clock'], null, 'star'))->toBe('star');
});

test('a non-scalar state is safe even with no map configured', function () {
    expect($this->resolver->resolve(['an', 'array']))->toBeNull();
});
