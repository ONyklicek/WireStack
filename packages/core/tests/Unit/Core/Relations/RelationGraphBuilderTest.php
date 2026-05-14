<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Relations\RelationGraphBuilder;
use NyonCode\WireCore\Core\Relations\RelationPath;

beforeEach(function () {
    $this->builder = new RelationGraphBuilder;
});

it('builds empty graph', function () {
    $graph = $this->builder->build();

    expect($graph->isEmpty())->toBeTrue()
        ->and($graph->nodeCount())->toBe(0);
});

it('ignores simple paths (no relation)', function () {
    $this->builder->addPath(RelationPath::parse('email'));
    $graph = $this->builder->build();

    expect($graph->isEmpty())->toBeTrue();
});

it('builds graph from single relation', function () {
    $this->builder->addPath(RelationPath::parse('user.email'));
    $graph = $this->builder->build();

    expect($graph->isEmpty())->toBeFalse()
        ->and($graph->hasRelation('user'))->toBeTrue()
        ->and($graph->getNode('user')->columns)->toBe(['email']);
});

it('merges multiple columns on same relation', function () {
    $this->builder->addPath(RelationPath::parse('user.email'));
    $this->builder->addPath(RelationPath::parse('user.name'));
    $graph = $this->builder->build();

    expect($graph->nodeCount())->toBe(1)
        ->and($graph->getNode('user')->columns)->toBe(['email', 'name']);
});

it('builds nested relation graph', function () {
    $this->builder->addPath(RelationPath::parse('user.company.name'));
    $graph = $this->builder->build();

    expect($graph->hasRelation('user'))->toBeTrue()
        ->and($graph->getNode('user')->hasChildren())->toBeTrue()
        ->and($graph->getNode('user')->getChild('company'))->not->toBeNull()
        ->and($graph->getNode('user')->getChild('company')->columns)->toBe(['name']);
});

it('merges sibling relations', function () {
    $this->builder->addPath(RelationPath::parse('user.email'));
    $this->builder->addPath(RelationPath::parse('category.name'));
    $graph = $this->builder->build();

    expect($graph->hasRelation('user'))->toBeTrue()
        ->and($graph->hasRelation('category'))->toBeTrue()
        ->and($graph->nodeCount())->toBe(2);
});

it('merges shared prefix paths', function () {
    $this->builder->addPath(RelationPath::parse('user.company.name'));
    $this->builder->addPath(RelationPath::parse('user.company.address'));
    $this->builder->addPath(RelationPath::parse('user.profile.avatar'));
    $graph = $this->builder->build();

    $userNode = $graph->getNode('user');
    expect($userNode->hasChildren())->toBeTrue()
        ->and($userNode->getChild('company')->columns)->toBe(['name', 'address'])
        ->and($userNode->getChild('profile')->columns)->toBe(['avatar']);
});

it('returns all paths', function () {
    $this->builder->addPath(RelationPath::parse('user.email'));
    $this->builder->addPath(RelationPath::parse('user.company.name'));
    $graph = $this->builder->build();

    $paths = $graph->getAllPaths();
    expect($paths)->toContain('user')
        ->and($paths)->toContain('user.company');
});

it('resets builder', function () {
    $this->builder->addPath(RelationPath::parse('user.email'));
    $this->builder->reset();
    $graph = $this->builder->build();

    expect($graph->isEmpty())->toBeTrue();
});
