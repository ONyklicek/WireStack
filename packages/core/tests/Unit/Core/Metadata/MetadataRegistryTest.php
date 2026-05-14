<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Metadata\AccessorMetadata;
use NyonCode\WireCore\Core\Metadata\ColumnMetadata;
use NyonCode\WireCore\Core\Metadata\MetadataRegistry;
use NyonCode\WireCore\Core\Metadata\ModelMetadata;

beforeEach(function () {
    $this->registry = new MetadataRegistry;
});

// ─── Model registration ─────────────────────────────────────────────────────

it('registers model metadata directly', function () {
    $meta = new ModelMetadata(
        modelClass: 'App\\Models\\User',
        table: 'users',
        primaryKey: 'id',
        primaryKeyType: 'int',
        incrementing: true,
        usesTimestamps: true,
        usesSoftDeletes: false,
        casts: ['email_verified_at' => 'datetime'],
        fillable: ['name', 'email'],
        guarded: ['*'],
        relations: ['posts'],
        appends: [],
    );

    $this->registry->registerModelMetadata('App\\Models\\User', $meta);

    expect($this->registry->hasModel('App\\Models\\User'))->toBeTrue()
        ->and($this->registry->getModelMetadata('App\\Models\\User'))->toBe($meta);
});

it('throws for unregistered model', function () {
    $this->registry->getModelMetadata('App\\Models\\Missing');
})->throws(InvalidArgumentException::class);

// ─── Column registration ────────────────────────────────────────────────────

it('registers and retrieves columns', function () {
    $column = ColumnMetadata::forDatabaseColumn('email', 'varchar');
    $this->registry->registerColumn('App\\Models\\User', $column);

    expect($this->registry->getColumn('App\\Models\\User', 'email'))->toBe($column)
        ->and($this->registry->getColumn('App\\Models\\User', 'missing'))->toBeNull();
});

it('retrieves all columns for a model', function () {
    $this->registry->registerColumn('App\\Models\\User', ColumnMetadata::forDatabaseColumn('name'));
    $this->registry->registerColumn('App\\Models\\User', ColumnMetadata::forDatabaseColumn('email'));

    $columns = $this->registry->getColumns('App\\Models\\User');

    expect($columns)->toHaveCount(2)
        ->and(array_keys($columns))->toBe(['name', 'email']);
});

// ─── Accessor registration ──────────────────────────────────────────────────

it('registers and retrieves accessors', function () {
    $accessor = AccessorMetadata::runtimeOnly('full_name');
    $this->registry->registerAccessor('App\\Models\\User', $accessor);

    expect($this->registry->getAccessor('App\\Models\\User', 'full_name'))->toBe($accessor)
        ->and($this->registry->getAccessor('App\\Models\\User', 'missing'))->toBeNull();
});

// ─── Flush ──────────────────────────────────────────────────────────────────

it('flushes all registered metadata', function () {
    $meta = new ModelMetadata(
        modelClass: 'App\\Models\\User',
        table: 'users',
        primaryKey: 'id',
        primaryKeyType: 'int',
        incrementing: true,
        usesTimestamps: true,
        usesSoftDeletes: false,
        casts: [],
        fillable: [],
        guarded: ['*'],
        relations: [],
        appends: [],
    );

    $this->registry->registerModelMetadata('App\\Models\\User', $meta);
    $this->registry->registerColumn('App\\Models\\User', ColumnMetadata::forDatabaseColumn('email'));

    $this->registry->flush();

    expect($this->registry->hasModel('App\\Models\\User'))->toBeFalse()
        ->and($this->registry->getColumns('App\\Models\\User'))->toBe([]);
});
