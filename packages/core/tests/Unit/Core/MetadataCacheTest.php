<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use NyonCode\WireCore\Core\Metadata\MetadataCache;
use NyonCode\WireCore\Core\Metadata\ModelMetadata;
use NyonCode\WireCore\Core\Metadata\RelationMetadata;

function metadataCache(): MetadataCache
{
    return new MetadataCache(new Repository(new ArrayStore));
}

function sampleModelMetadata(): ModelMetadata
{
    return new ModelMetadata(
        modelClass: 'App\\Post',
        table: 'posts',
        primaryKey: 'id',
        primaryKeyType: 'int',
        incrementing: true,
        usesTimestamps: true,
        usesSoftDeletes: false,
        casts: [],
        fillable: ['title'],
        guarded: [],
        relations: [],
        appends: [],
    );
}

function sampleRelationMetadata(): RelationMetadata
{
    return new RelationMetadata(
        name: 'author',
        type: 'BelongsTo',
        parentModel: 'App\\Post',
        relatedModel: 'App\\User',
        foreignKey: 'user_id',
        localKey: 'id',
        morphType: null,
        pivotTable: null,
        isMorph: false,
        isToMany: false,
    );
}

it('returns null for missing model metadata', function () {
    expect(metadataCache()->getModelMetadata('App\\Post'))->toBeNull();
});

it('stores and reads model metadata', function () {
    $cache = metadataCache();
    $meta = sampleModelMetadata();

    $cache->putModelMetadata('App\\Post', $meta);

    expect($cache->getModelMetadata('App\\Post'))->toBe($meta);
});

it('stores and reads relation metadata', function () {
    $cache = metadataCache();
    $relations = ['author' => sampleRelationMetadata()];

    expect($cache->getRelations('App\\Post'))->toBeNull();

    $cache->putRelations('App\\Post', $relations);

    expect($cache->getRelations('App\\Post'))->toBe($relations);
});

it('forgets both model and relation metadata', function () {
    $cache = metadataCache();
    $cache->putModelMetadata('App\\Post', sampleModelMetadata());
    $cache->putRelations('App\\Post', ['author' => sampleRelationMetadata()]);

    $cache->forget('App\\Post');

    expect($cache->getModelMetadata('App\\Post'))->toBeNull()
        ->and($cache->getRelations('App\\Post'))->toBeNull();
});

it('flush is a safe no-op', function () {
    $cache = metadataCache();
    $cache->flush();

    expect(true)->toBeTrue();
});
