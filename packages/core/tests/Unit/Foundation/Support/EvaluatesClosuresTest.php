<?php

declare(strict_types=1);

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
