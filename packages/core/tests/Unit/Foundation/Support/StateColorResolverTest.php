<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasColor;
use NyonCode\WireCore\Foundation\Support\StateColorResolver;

enum ScrPlainStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

enum ScrRichStatus: string implements HasColor
{
    case Open = 'open';
    case Closed = 'closed';

    public function getColor(): string|Color|null
    {
        return match ($this) {
            self::Open => 'success',
            self::Closed => Color::Danger,
        };
    }
}

beforeEach(function () {
    $this->resolver = new StateColorResolver;
});

test('the callback wins over every other rung', function () {
    expect($this->resolver->resolve('active', ['active' => 'danger'], fn () => 'success'))
        ->toBe('success');
});

test('a callback returning a Color enum is unwrapped to its value', function () {
    // PollColumn returned the enum itself here and tripped its own string return type.
    expect($this->resolver->resolve('active', null, fn () => Color::Success))
        ->toBe('success');
});

test('a callback returning null falls through to the map', function () {
    expect($this->resolver->resolve('active', ['active' => 'success'], fn () => null))
        ->toBe('success');
});

test('the map is indexed by the scalar value of an enum state', function () {
    // Indexing with the enum itself is a TypeError: "Cannot access offset of type X".
    expect($this->resolver->resolve(ScrPlainStatus::Active, ['active' => 'success']))
        ->toBe('success');
});

test('a map value may be a Color enum', function () {
    expect($this->resolver->resolve('active', ['active' => Color::Success]))
        ->toBe('success');
});

test('an enum state carrying the HasColor contract resolves without a map', function () {
    expect($this->resolver->resolve(ScrRichStatus::Open))->toBe('success')
        ->and($this->resolver->resolve(ScrRichStatus::Closed))->toBe('danger');
});

test('an explicit map beats the enum contract', function () {
    expect($this->resolver->resolve(ScrRichStatus::Open, ['open' => 'warning']))
        ->toBe('warning');
});

test('the default answers when no rung matches', function () {
    expect($this->resolver->resolve('unknown', ['active' => 'success'], null, 'gray'))
        ->toBe('gray')
        ->and($this->resolver->resolve('unknown'))->toBeNull();
});

test('a non-scalar state cannot index the map and falls through to the default', function () {
    expect($this->resolver->resolve(['an', 'array'], ['active' => 'success'], null, 'gray'))
        ->toBe('gray');
});
