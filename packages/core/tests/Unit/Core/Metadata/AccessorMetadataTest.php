<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Metadata\AccessorMetadata;

it('creates runtime-only accessor', function () {
    $meta = AccessorMetadata::runtimeOnly('display_name');

    expect($meta->name)->toBe('display_name')
        ->and($meta->sqlExpression)->toBeNull()
        ->and($meta->runtimeOnly)->toBeTrue()
        ->and($meta->isSqlCompatible())->toBeFalse();
});

it('creates accessor with sql expression', function () {
    $meta = AccessorMetadata::withSqlExpression('full_name', "CONCAT(first_name, ' ', last_name)");

    expect($meta->name)->toBe('full_name')
        ->and($meta->sqlExpression)->toBe("CONCAT(first_name, ' ', last_name)")
        ->and($meta->runtimeOnly)->toBeFalse()
        ->and($meta->isSqlCompatible())->toBeTrue();
});
