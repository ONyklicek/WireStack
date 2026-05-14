<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Metadata\ColumnMetadata;

// ─── Factory methods ────────────────────────────────────────────────────────

it('creates database column metadata', function () {
    $meta = ColumnMetadata::forDatabaseColumn('email', 'varchar', true);

    expect($meta->name)->toBe('email')
        ->and($meta->dbColumn)->toBe('email')
        ->and($meta->dbType)->toBe('varchar')
        ->and($meta->isAccessor)->toBeFalse()
        ->and($meta->isComputed)->toBeFalse()
        ->and($meta->sqlExpression)->toBeNull()
        ->and($meta->nullable)->toBeTrue();
});

it('creates accessor metadata', function () {
    $meta = ColumnMetadata::forAccessor('full_name');

    expect($meta->name)->toBe('full_name')
        ->and($meta->dbColumn)->toBeNull()
        ->and($meta->isAccessor)->toBeTrue()
        ->and($meta->isComputed)->toBeFalse()
        ->and($meta->sqlExpression)->toBeNull();
});

it('creates accessor with sql expression', function () {
    $meta = ColumnMetadata::forAccessor('full_name', "CONCAT(first_name, ' ', last_name)");

    expect($meta->isAccessor)->toBeTrue()
        ->and($meta->isComputed)->toBeTrue()
        ->and($meta->sqlExpression)->toBe("CONCAT(first_name, ' ', last_name)");
});

it('creates computed column metadata', function () {
    $meta = ColumnMetadata::forComputed('total', 'price * quantity');

    expect($meta->name)->toBe('total')
        ->and($meta->isComputed)->toBeTrue()
        ->and($meta->sqlExpression)->toBe('price * quantity');
});

// ─── Capabilities ───────────────────────────────────────────────────────────

it('detects sql compatibility for db column', function () {
    expect(ColumnMetadata::forDatabaseColumn('email')->isSqlCompatible())->toBeTrue();
});

it('detects sql compatibility for computed', function () {
    expect(ColumnMetadata::forComputed('total', 'price * qty')->isSqlCompatible())->toBeTrue();
});

it('detects runtime-only accessor', function () {
    $meta = ColumnMetadata::forAccessor('display_name');

    expect($meta->isRuntimeOnly())->toBeTrue()
        ->and($meta->isSqlCompatible())->toBeFalse();
});

it('detects sql-compatible accessor', function () {
    $meta = ColumnMetadata::forAccessor('full_name', "CONCAT(first_name, ' ', last_name)");

    expect($meta->isRuntimeOnly())->toBeFalse()
        ->and($meta->isSqlCompatible())->toBeTrue();
});

it('returns sql reference for db column', function () {
    expect(ColumnMetadata::forDatabaseColumn('email')->getSqlReference())->toBe('email');
});

it('returns sql reference for computed', function () {
    expect(ColumnMetadata::forComputed('total', 'price * qty')->getSqlReference())->toBe('price * qty');
});

it('returns null sql reference for runtime accessor', function () {
    expect(ColumnMetadata::forAccessor('display')->getSqlReference())->toBeNull();
});
