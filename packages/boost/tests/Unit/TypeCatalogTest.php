<?php

declare(strict_types=1);

use NyonCode\WireBoost\Support\TypeCatalog;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;

beforeEach(function () {
    $this->catalog = new TypeCatalog;
});

it('lists the known categories', function () {
    expect($this->catalog->categories())
        ->toContain('columns', 'filters', 'fields', 'actions', 'infolist-entries', 'widgets', 'modals');
});

it('reports whether a category is known', function () {
    expect($this->catalog->has('columns'))->toBeTrue()
        ->and($this->catalog->has('bogus'))->toBeFalse();
});

it('discovers concrete column types with name, class and summary', function () {
    $types = $this->catalog->types('columns');
    $names = array_column($types, 'name');

    expect($names)->toContain('text-column', 'badge-column');

    $text = collect($types)->firstWhere('name', 'text-column');

    expect($text['class'])->toBe(TextColumn::class)
        ->and($text['summary'])->toBeString()->not->toBeEmpty();
});

it('returns an empty list for an unknown category', function () {
    expect($this->catalog->types('bogus'))->toBe([]);
});

it('resolves a type from a fully-qualified class name', function () {
    expect($this->catalog->resolve(BadgeColumn::class))->toBe(BadgeColumn::class);
});

it('resolves a type from a built-in short name', function () {
    expect($this->catalog->resolve('badge-column'))->toBe(BadgeColumn::class)
        ->and($this->catalog->resolve('BadgeColumn'))->toBe(BadgeColumn::class);
});

it('returns null for an unresolvable type', function () {
    expect($this->catalog->resolve('does-not-exist'))->toBeNull();
});
