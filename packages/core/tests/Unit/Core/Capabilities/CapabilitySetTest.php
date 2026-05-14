<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Capabilities\CapabilitySet;

it('creates empty set', function () {
    $set = new CapabilitySet;

    expect($set->isEmpty())->toBeTrue()
        ->and($set->count())->toBe(0);
});

it('creates set with capabilities', function () {
    $set = new CapabilitySet(Capability::Searchable, Capability::Sortable);

    expect($set->has(Capability::Searchable))->toBeTrue()
        ->and($set->has(Capability::Sortable))->toBeTrue()
        ->and($set->has(Capability::Filterable))->toBeFalse()
        ->and($set->count())->toBe(2);
});

it('deduplicates capabilities', function () {
    $set = new CapabilitySet(Capability::Searchable, Capability::Searchable);

    expect($set->count())->toBe(1);
});

it('adds capabilities immutably', function () {
    $original = new CapabilitySet(Capability::Searchable);
    $new = $original->add(Capability::Sortable);

    expect($original->count())->toBe(1)
        ->and($new->count())->toBe(2)
        ->and($new->has(Capability::Sortable))->toBeTrue();
});

it('removes capabilities immutably', function () {
    $original = new CapabilitySet(Capability::Searchable, Capability::Sortable);
    $new = $original->remove(Capability::Searchable);

    expect($original->count())->toBe(2)
        ->and($new->count())->toBe(1)
        ->and($new->has(Capability::Searchable))->toBeFalse();
});

it('provides convenience methods', function () {
    $set = new CapabilitySet(
        Capability::Searchable,
        Capability::Sortable,
        Capability::Filterable,
        Capability::Editable,
        Capability::RuntimeOnly,
        Capability::SqlExpression,
    );

    expect($set->isSearchable())->toBeTrue()
        ->and($set->isSortable())->toBeTrue()
        ->and($set->isFilterable())->toBeTrue()
        ->and($set->isEditable())->toBeTrue()
        ->and($set->isRuntimeOnly())->toBeTrue()
        ->and($set->hasSqlExpression())->toBeTrue();
});

it('returns all capabilities', function () {
    $set = new CapabilitySet(Capability::Searchable, Capability::Sortable);

    expect($set->all())->toHaveCount(2)
        ->and($set->all())->each->toBeInstanceOf(Capability::class);
});
