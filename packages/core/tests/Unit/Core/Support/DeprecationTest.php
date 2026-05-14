<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Support\Deprecation;

beforeEach(function () {
    Deprecation::enable();
    Deprecation::reset();
});

it('triggers a deprecation warning for method rename', function () {
    $warnings = [];
    set_error_handler(function (int $errno, string $errstr) use (&$warnings) {
        $warnings[] = $errstr;

        return true;
    }, E_USER_DEPRECATED);

    Deprecation::method('oldMethod', 'newMethod');

    restore_error_handler();

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0])->toContain('oldMethod()')
        ->and($warnings[0])->toContain('newMethod()')
        ->and($warnings[0])->toContain('v2.0');
});

it('triggers a deprecation warning for class rename', function () {
    $warnings = [];
    set_error_handler(function (int $errno, string $errstr) use (&$warnings) {
        $warnings[] = $errstr;

        return true;
    }, E_USER_DEPRECATED);

    Deprecation::classRenamed('OldClass', 'NewClass', '3.0');

    restore_error_handler();

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0])->toContain('OldClass')
        ->and($warnings[0])->toContain('NewClass')
        ->and($warnings[0])->toContain('v3.0');
});

it('deduplicates warnings by key', function () {
    $count = 0;
    set_error_handler(function () use (&$count) {
        $count++;

        return true;
    }, E_USER_DEPRECATED);

    Deprecation::method('foo', 'bar');
    Deprecation::method('foo', 'bar');
    Deprecation::method('foo', 'bar');

    restore_error_handler();

    expect($count)->toBe(1);
});

it('can be disabled', function () {
    Deprecation::disable();

    $count = 0;
    set_error_handler(function () use (&$count) {
        $count++;

        return true;
    }, E_USER_DEPRECATED);

    Deprecation::method('foo', 'bar');

    restore_error_handler();

    expect($count)->toBe(0);
});

it('reset clears warned keys', function () {
    $count = 0;
    set_error_handler(function () use (&$count) {
        $count++;

        return true;
    }, E_USER_DEPRECATED);

    Deprecation::method('foo', 'bar');
    Deprecation::reset();
    Deprecation::method('foo', 'bar');

    restore_error_handler();

    expect($count)->toBe(2);
});

it('triggers property deprecation', function () {
    $warnings = [];
    set_error_handler(function (int $errno, string $errstr) use (&$warnings) {
        $warnings[] = $errstr;

        return true;
    }, E_USER_DEPRECATED);

    Deprecation::property('Table', 'oldProp', 'newMethod()');

    restore_error_handler();

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0])->toContain('Table::$oldProp')
        ->and($warnings[0])->toContain('newMethod()');
});
