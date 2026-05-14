<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Metadata\MetadataRegistry;
use NyonCode\WireCore\Core\Metadata\RelationMetadata;
use NyonCode\WireCore\Core\Query\Strategies\MorphRelationStrategy;
use NyonCode\WireCore\Core\Relations\RelationPath;

beforeEach(function () {
    $this->registry = new MetadataRegistry;

    // Register a morph relation
    $this->registry->registerRelation('App\\Models\\Comment', new RelationMetadata(
        name: 'commentable',
        type: 'MorphTo',
        parentModel: 'App\\Models\\Comment',
        relatedModel: null,
        foreignKey: 'commentable_id',
        localKey: null,
        morphType: 'commentable_type',
        pivotTable: null,
        isMorph: true,
        isToMany: false,
    ));

    // Register a non-morph relation
    $this->registry->registerRelation('App\\Models\\User', new RelationMetadata(
        name: 'company',
        type: 'BelongsTo',
        parentModel: 'App\\Models\\User',
        relatedModel: 'App\\Models\\Company',
        foreignKey: 'company_id',
        localKey: 'id',
        morphType: null,
        pivotTable: null,
        isMorph: false,
        isToMany: false,
    ));

    // Register morph many relation
    $this->registry->registerRelation('App\\Models\\Post', new RelationMetadata(
        name: 'comments',
        type: 'MorphMany',
        parentModel: 'App\\Models\\Post',
        relatedModel: 'App\\Models\\Comment',
        foreignKey: 'commentable_id',
        localKey: 'id',
        morphType: 'commentable_type',
        pivotTable: null,
        isMorph: true,
        isToMany: true,
    ));

    $this->strategy = new MorphRelationStrategy($this->registry);
});

it('detects morph path', function () {
    $path = RelationPath::parse('commentable.title');

    expect($this->strategy->isMorphPath($path, 'App\\Models\\Comment'))->toBeTrue();
});

it('does not flag non-morph path', function () {
    $path = RelationPath::parse('company.name');

    expect($this->strategy->isMorphPath($path, 'App\\Models\\User'))->toBeFalse();
});

it('returns eager load paths for morph relation', function () {
    $path = RelationPath::parse('comments.body');

    $eagerLoads = $this->strategy->getEagerLoadPaths($path);

    expect($eagerLoads)->toBe(['comments']);
});

it('plans morph aggregate with subquery strategy', function () {
    $clause = $this->strategy->planAggregate('comments', 'count');

    expect($clause->relation)->toBe('comments')
        ->and($clause->function)->toBe('count')
        ->and($clause->strategy)->toBe('subquery');
});

it('plans morph exists aggregate with exists strategy', function () {
    $clause = $this->strategy->planAggregate('comments', 'exists');

    expect($clause->strategy)->toBe('exists');
});

it('checks morph handling requirement', function () {
    expect($this->strategy->requiresMorphHandling('App\\Models\\Post', 'comments'))->toBeTrue()
        ->and($this->strategy->requiresMorphHandling('App\\Models\\User', 'company'))->toBeFalse();
});

it('gets morph type column', function () {
    expect($this->strategy->getMorphType('App\\Models\\Post', 'comments'))->toBe('commentable_type')
        ->and($this->strategy->getMorphType('App\\Models\\User', 'company'))->toBeNull();
});
