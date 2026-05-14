<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Relations\AggregateSegment;
use NyonCode\WireCore\Core\Relations\ColumnSegment;
use NyonCode\WireCore\Core\Relations\PivotSegment;
use NyonCode\WireCore\Core\Relations\RelationPath;
use NyonCode\WireCore\Core\Relations\RelationSegment;

// ─── Simple paths ───────────────────────────────────────────────────────────

it('parses simple column name', function () {
    $path = RelationPath::parse('email');

    expect($path->isSimple())->toBeTrue()
        ->and($path->hasRelation())->toBeFalse()
        ->and($path->depth())->toBe(1)
        ->and($path->segments)->toHaveCount(1)
        ->and($path->segments[0])->toBeInstanceOf(ColumnSegment::class)
        ->and($path->segments[0]->getName())->toBe('email');
});

// ─── Relation paths ─────────────────────────────────────────────────────────

it('parses single relation path', function () {
    $path = RelationPath::parse('user.email');

    expect($path->hasRelation())->toBeTrue()
        ->and($path->isSimple())->toBeFalse()
        ->and($path->depth())->toBe(2)
        ->and($path->segments[0])->toBeInstanceOf(RelationSegment::class)
        ->and($path->segments[0]->getName())->toBe('user')
        ->and($path->segments[1])->toBeInstanceOf(ColumnSegment::class)
        ->and($path->segments[1]->getName())->toBe('email');
});

it('parses nested relation path', function () {
    $path = RelationPath::parse('posts.comments.author.email');

    expect($path->depth())->toBe(4)
        ->and($path->segments[0])->toBeInstanceOf(RelationSegment::class)
        ->and($path->segments[0]->getName())->toBe('posts')
        ->and($path->segments[1])->toBeInstanceOf(RelationSegment::class)
        ->and($path->segments[1]->getName())->toBe('comments')
        ->and($path->segments[2])->toBeInstanceOf(RelationSegment::class)
        ->and($path->segments[2]->getName())->toBe('author')
        ->and($path->segments[3])->toBeInstanceOf(ColumnSegment::class)
        ->and($path->segments[3]->getName())->toBe('email');
});

// ─── Pivot paths ────────────────────────────────────────────────────────────

it('parses pivot path', function () {
    $path = RelationPath::parse('roles.pivot.created_at');

    expect($path->isPivot())->toBeTrue()
        ->and($path->segments[0])->toBeInstanceOf(RelationSegment::class)
        ->and($path->segments[0]->getName())->toBe('roles')
        ->and($path->segments[1])->toBeInstanceOf(PivotSegment::class)
        ->and($path->segments[1]->attribute)->toBe('created_at');
});

// ─── Aggregate paths ────────────────────────────────────────────────────────

it('parses aggregate count', function () {
    $path = RelationPath::parse('orders->count()');

    expect($path->isAggregate())->toBeTrue()
        ->and($path->segments[0])->toBeInstanceOf(AggregateSegment::class)
        ->and($path->segments[0]->relation)->toBe('orders')
        ->and($path->segments[0]->function)->toBe('count')
        ->and($path->segments[0]->column)->toBeNull();
});

it('parses aggregate sum with column', function () {
    $path = RelationPath::parse('orders->sum(total)');

    expect($path->isAggregate())->toBeTrue()
        ->and($path->segments[0])->toBeInstanceOf(AggregateSegment::class)
        ->and($path->segments[0]->relation)->toBe('orders')
        ->and($path->segments[0]->function)->toBe('sum')
        ->and($path->segments[0]->column)->toBe('total');
});

it('parses nested aggregate', function () {
    $path = RelationPath::parse('user.orders->count()');

    expect($path->isAggregate())->toBeTrue()
        ->and($path->depth())->toBe(2)
        ->and($path->segments[0])->toBeInstanceOf(RelationSegment::class)
        ->and($path->segments[0]->getName())->toBe('user')
        ->and($path->segments[1])->toBeInstanceOf(AggregateSegment::class)
        ->and($path->segments[1]->relation)->toBe('orders');
});

// ─── Helper methods ─────────────────────────────────────────────────────────

it('returns relation path string', function () {
    expect(RelationPath::parse('user.company.name')->getRelationPath())->toBe('user.company')
        ->and(RelationPath::parse('email')->getRelationPath())->toBeNull();
});

it('returns column name', function () {
    expect(RelationPath::parse('user.company.name')->getColumnName())->toBe('name')
        ->and(RelationPath::parse('email')->getColumnName())->toBe('email');
});

it('returns relation segments', function () {
    $path = RelationPath::parse('user.company.name');
    $relations = $path->getRelationSegments();

    expect($relations)->toHaveCount(2)
        ->and($relations[0]->getName())->toBe('user')
        ->and($relations[1]->getName())->toBe('company');
});

it('converts to string', function () {
    expect((string) RelationPath::parse('user.email')->__toString())->toBe('user.email')
        ->and((string) RelationPath::parse('email')->__toString())->toBe('email');
});

// ─── Edge cases ─────────────────────────────────────────────────────────────

it('throws on empty path', function () {
    RelationPath::parse('');
})->throws(InvalidArgumentException::class);

it('throws on invalid aggregate syntax', function () {
    RelationPath::parse('orders->invalid(');
})->throws(InvalidArgumentException::class);
