<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Contracts\HasStateAccessors;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

// Anonymous test class that uses the trait
$makeTestClass = function () {
    return new class
    {
        use EvaluatesClosures;

        public function testEvaluate(mixed $value, array $namedArgs = []): mixed
        {
            return $this->evaluate($value, $namedArgs);
        }
    };
};

it('returns scalar values unchanged', function () use ($makeTestClass) {
    $obj = $makeTestClass();

    expect($obj->testEvaluate('hello'))->toBe('hello')
        ->and($obj->testEvaluate(42))->toBe(42)
        ->and($obj->testEvaluate(true))->toBe(true)
        ->and($obj->testEvaluate(null))->toBeNull();
});

it('evaluates closures', function () use ($makeTestClass) {
    $obj = $makeTestClass();

    expect($obj->testEvaluate(fn () => 'resolved'))->toBe('resolved');
});

it('passes component as argument to closure', function () use ($makeTestClass) {
    $obj = $makeTestClass();

    $result = $obj->testEvaluate(fn ($component) => $component::class);

    expect($result)->toBe($obj::class);
});

it('injects state accessors when the component exposes them', function () {
    $obj = new class implements HasStateAccessors
    {
        use EvaluatesClosures;

        public function testEvaluate(mixed $value): mixed
        {
            return $this->evaluate($value);
        }

        public function getStateAccessors(): array
        {
            return [
                'get' => fn (string $path) => "value-of-{$path}",
                'set' => fn (string $path, mixed $value) => $value,
                'state' => 'own-state',
            ];
        }
    };

    expect($obj->testEvaluate(fn ($get) => $get('type')))->toBe('value-of-type')
        ->and($obj->testEvaluate(fn ($state) => $state))->toBe('own-state')
        ->and($obj->testEvaluate(fn ($set) => $set('x', 99)))->toBe(99);
});

it('lets explicit named args override injected state accessors', function () {
    $obj = new class implements HasStateAccessors
    {
        use EvaluatesClosures;

        public function testEvaluate(mixed $value, array $namedArgs = []): mixed
        {
            return $this->evaluate($value, $namedArgs);
        }

        public function getStateAccessors(): array
        {
            return ['state' => 'default'];
        }
    };

    expect($obj->testEvaluate(fn ($state) => $state, ['state' => 'override']))
        ->toBe('override');
});
