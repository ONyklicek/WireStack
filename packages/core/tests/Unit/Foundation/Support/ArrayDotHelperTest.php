<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Support\ArrayDotHelper;

it('gets nested values with dot notation', function () {
    $data = ['user' => ['name' => 'John', 'address' => ['city' => 'Prague']]];

    expect(ArrayDotHelper::get($data, 'user.name'))->toBe('John')
        ->and(ArrayDotHelper::get($data, 'user.address.city'))->toBe('Prague')
        ->and(ArrayDotHelper::get($data, 'missing', 'default'))->toBe('default');
});

it('sets nested values with dot notation', function () {
    $data = [];

    ArrayDotHelper::set($data, 'user.name', 'Jane');

    expect($data)->toBe(['user' => ['name' => 'Jane']]);
});

it('checks key existence', function () {
    $data = ['user' => ['name' => 'John']];

    expect(ArrayDotHelper::has($data, 'user.name'))->toBeTrue()
        ->and(ArrayDotHelper::has($data, 'user.email'))->toBeFalse();
});

it('forgets nested keys', function () {
    $data = ['user' => ['name' => 'John', 'email' => 'john@example.com']];

    ArrayDotHelper::forget($data, 'user.email');

    expect($data)->toBe(['user' => ['name' => 'John']]);
});

it('prefixes paths correctly', function () {
    expect(ArrayDotHelper::prefix('data', 'name'))->toBe('data.name')
        ->and(ArrayDotHelper::prefix('', 'name'))->toBe('name');
});
