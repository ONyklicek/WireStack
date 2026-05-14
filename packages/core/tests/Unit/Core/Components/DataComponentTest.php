<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Capabilities\CapabilitySet;
use NyonCode\WireCore\Core\Components\TextComponent;
use NyonCode\WireCore\Core\Metadata\ColumnMetadata;

// Using TextComponent as concrete implementation of DataComponent

// ─── Name & Label ───────────────────────────────────────────────────────────

it('creates component with name', function () {
    $c = TextComponent::make('email');

    expect($c->getName())->toBe('email');
});

it('auto-generates label from name', function () {
    expect(TextComponent::make('email')->getLabel())->toBe('Email')
        ->and(TextComponent::make('first_name')->getLabel())->toBe('First name');
});

it('uses custom label', function () {
    $c = TextComponent::make('email')->label('E-mail');

    expect($c->getLabel())->toBe('E-mail');
});

it('auto-generates label from relation column name', function () {
    $c = TextComponent::make('user.company_name');

    expect($c->getLabel())->toBe('Company name');
});

// ─── Relation Path ──────────────────────────────────────────────────────────

it('has no relation for simple name', function () {
    $c = TextComponent::make('email');

    expect($c->hasRelation())->toBeFalse()
        ->and($c->getRelationPath())->toBeNull()
        ->and($c->getRelationName())->toBeNull()
        ->and($c->getColumnName())->toBe('email');
});

it('parses relation path from dot notation', function () {
    $c = TextComponent::make('user.email');

    expect($c->hasRelation())->toBeTrue()
        ->and($c->getRelationName())->toBe('user')
        ->and($c->getColumnName())->toBe('email');
});

it('parses nested relation path', function () {
    $c = TextComponent::make('user.company.name');

    expect($c->hasRelation())->toBeTrue()
        ->and($c->getRelationName())->toBe('user.company')
        ->and($c->getColumnName())->toBe('name');
});

// ─── Capabilities ───────────────────────────────────────────────────────────

it('starts with empty capabilities', function () {
    $c = TextComponent::make('email');

    expect($c->getCapabilities()->isEmpty())->toBeTrue();
});

it('sets capabilities', function () {
    $set = new CapabilitySet(Capability::Searchable, Capability::Sortable);
    $c = TextComponent::make('email')->capabilities($set);

    expect($c->getCapabilities()->isSearchable())->toBeTrue()
        ->and($c->getCapabilities()->isSortable())->toBeTrue();
});

it('adds capabilities', function () {
    $c = TextComponent::make('email')
        ->addCapability(Capability::Searchable)
        ->addCapability(Capability::Sortable);

    expect($c->hasCapability(Capability::Searchable))->toBeTrue()
        ->and($c->hasCapability(Capability::Sortable))->toBeTrue();
});

it('removes capabilities', function () {
    $c = TextComponent::make('email')
        ->addCapability(Capability::Searchable, Capability::Sortable)
        ->removeCapability(Capability::Searchable);

    expect($c->hasCapability(Capability::Searchable))->toBeFalse()
        ->and($c->hasCapability(Capability::Sortable))->toBeTrue();
});

// ─── Metadata ───────────────────────────────────────────────────────────────

it('sets column metadata', function () {
    $meta = ColumnMetadata::forDatabaseColumn('email');
    $c = TextComponent::make('email')->columnMetadata($meta);

    expect($c->getColumnMetadata())->toBe($meta);
});

// ─── SQL Compatibility ──────────────────────────────────────────────────────

it('detects sql compatibility from metadata', function () {
    $c = TextComponent::make('email')
        ->columnMetadata(ColumnMetadata::forDatabaseColumn('email'));

    expect($c->isSqlCompatible())->toBeTrue();
});

it('detects runtime-only from capabilities', function () {
    $c = TextComponent::make('display')
        ->addCapability(Capability::RuntimeOnly);

    expect($c->isSqlCompatible())->toBeFalse();
});

// ─── TextComponent specific ─────────────────────────────────────────────────

it('sets placeholder', function () {
    $c = TextComponent::make('email')->placeholder('Enter email');

    expect($c->getPlaceholder())->toBe('Enter email');
});

it('sets prefix and suffix', function () {
    $c = TextComponent::make('price')->prefix('$')->suffix('USD');

    expect($c->getPrefix())->toBe('$')
        ->and($c->getSuffix())->toBe('USD');
});

it('sets character limit', function () {
    $c = TextComponent::make('bio')->characterLimit(100);

    expect($c->getCharacterLimit())->toBe(100);
});
