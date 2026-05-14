<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Capabilities\CapabilityResolver;
use NyonCode\WireCore\Core\Metadata\AccessorMetadata;
use NyonCode\WireCore\Core\Metadata\ColumnMetadata;

beforeEach(function () {
    $this->resolver = new CapabilityResolver;
});

it('resolves capabilities for database column', function () {
    $column = ColumnMetadata::forDatabaseColumn('email');
    $set = $this->resolver->resolve(columnMetadata: $column);

    expect($set->isSearchable())->toBeTrue()
        ->and($set->isSortable())->toBeTrue()
        ->and($set->isFilterable())->toBeTrue()
        ->and($set->has(Capability::Dehydrated))->toBeTrue()
        ->and($set->has(Capability::Hydrated))->toBeTrue();
});

it('resolves capabilities for computed column', function () {
    $column = ColumnMetadata::forComputed('total', 'price * qty');
    $set = $this->resolver->resolve(columnMetadata: $column);

    expect($set->hasSqlExpression())->toBeTrue()
        ->and($set->isSearchable())->toBeTrue()
        ->and($set->isSortable())->toBeTrue();
});

it('resolves runtime-only accessor', function () {
    $column = ColumnMetadata::forAccessor('display_name');
    $set = $this->resolver->resolve(columnMetadata: $column);

    expect($set->isRuntimeOnly())->toBeTrue()
        ->and($set->isSearchable())->toBeFalse()
        ->and($set->isSortable())->toBeFalse();
});

it('resolves accessor with sql expression', function () {
    $accessor = AccessorMetadata::withSqlExpression('full_name', "CONCAT(first, ' ', last)");
    $set = $this->resolver->resolve(accessorMetadata: $accessor);

    expect($set->hasSqlExpression())->toBeTrue()
        ->and($set->isSearchable())->toBeTrue()
        ->and($set->isSortable())->toBeTrue()
        ->and($set->isRuntimeOnly())->toBeFalse();
});

it('resolves runtime-only accessor metadata', function () {
    $accessor = AccessorMetadata::runtimeOnly('display');
    $set = $this->resolver->resolve(accessorMetadata: $accessor);

    expect($set->isRuntimeOnly())->toBeTrue();
});

it('merges explicit capabilities with resolved ones', function () {
    $column = ColumnMetadata::forDatabaseColumn('email');
    $set = $this->resolver->resolve(
        columnMetadata: $column,
        explicit: [Capability::Editable],
    );

    expect($set->isEditable())->toBeTrue()
        ->and($set->isSearchable())->toBeTrue();
});
