<?php

declare(strict_types=1);

use NyonCode\WireBoost\Support\TypeCatalog;
use NyonCode\WireCore\Foundation\Schema\Callout;
use NyonCode\WireCore\Foundation\Schema\EmptyState;
use NyonCode\WireCore\Panels\Components\ToggleEntry;
use NyonCode\WireForms\Components\Display\Html;
use NyonCode\WireForms\Components\Display\Placeholder;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;

beforeEach(function () {
    $this->catalog = new TypeCatalog;
});

it('lists the known categories', function () {
    expect($this->catalog->categories())
        ->toContain('columns', 'filters', 'fields', 'displays', 'actions', 'infolist-entries', 'panel-entries', 'widgets', 'modals', 'layouts');
});

it('catalogs editable panel entries', function () {
    // These extend the infolist Entry but live in a sibling directory, so the
    // infolist-entries scan cannot see them.
    expect(array_column($this->catalog->types('panel-entries'), 'name'))
        ->toBe(['checkbox-entry', 'select-entry', 'text-input-entry', 'toggle-entry']);
});

it('resolves a panel entry by its short name', function () {
    expect($this->catalog->resolve('toggle-entry'))
        ->toBe(ToggleEntry::class);
});

it('discovers layout components from the sibling Schema directory', function () {
    $names = array_column($this->catalog->types('layouts'), 'name');

    // LayoutComponent lives in Foundation/Components but its concrete types are
    // shipped in Foundation/Schema — the catalog scans there for this category.
    expect($names)->toContain('grid', 'section', 'flex', 'fieldset', 'tabs', 'wizard', 'callout', 'empty-state')
        ->and($this->catalog->resolve('callout'))->toBe(Callout::class)
        ->and($this->catalog->resolve('empty-state'))->toBe(EmptyState::class);
});

it('discovers display components and resolves them by short name', function () {
    $names = array_column($this->catalog->types('displays'), 'name');

    expect($names)->toContain('html', 'placeholder', 'alert', 'view-field')
        ->and($this->catalog->resolve('html'))->toBe(Html::class)
        ->and($this->catalog->resolve('placeholder'))->toBe(Placeholder::class);
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
