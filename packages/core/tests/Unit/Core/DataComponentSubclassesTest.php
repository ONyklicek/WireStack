<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Components\BooleanComponent;
use NyonCode\WireCore\Core\Components\DateComponent;
use NyonCode\WireCore\Core\Components\RelationComponent;
use NyonCode\WireCore\Core\Components\SelectComponent;

// ─── BooleanComponent ──────────────────────────────────────────

it('boolean component exposes true/false labels', function () {
    $component = BooleanComponent::make('active');

    expect($component->getTrueLabel())->toBeNull()
        ->and($component->getFalseLabel())->toBeNull()
        ->and($component->trueLabel('Yes')->getTrueLabel())->toBe('Yes')
        ->and($component->falseLabel('No')->getFalseLabel())->toBe('No');
});

// ─── DateComponent ─────────────────────────────────────────────

it('date component exposes format and timezone', function () {
    $component = DateComponent::make('created_at');

    expect($component->getFormat())->toBeNull()
        ->and($component->getTimezone())->toBeNull()
        ->and($component->format('d.m.Y')->getFormat())->toBe('d.m.Y')
        ->and($component->timezone('Europe/Prague')->getTimezone())->toBe('Europe/Prague');
});

// ─── RelationComponent ─────────────────────────────────────────

it('relation component exposes display/value columns and depth', function () {
    $component = RelationComponent::make('author');

    expect($component->getDisplayColumn())->toBeNull()
        ->and($component->getValueColumn())->toBeNull()
        ->and($component->displayColumn('name')->getDisplayColumn())->toBe('name')
        ->and($component->valueColumn('id')->getValueColumn())->toBe('id')
        ->and($component->getRelationDepth())->toBe(0);
});

it('relation component reports nested relation depth', function () {
    expect(RelationComponent::make('author.company.name')->getRelationDepth())
        ->toBeGreaterThan(0);
});

// ─── SelectComponent ───────────────────────────────────────────

it('select component exposes options, multiple and placeholder', function () {
    $component = SelectComponent::make('status')
        ->options(['a' => 'A', 'b' => 'B'])
        ->multiple()
        ->placeholder('Pick one');

    expect($component->getOptions())->toBe(['a' => 'A', 'b' => 'B'])
        ->and($component->isMultiple())->toBeTrue()
        ->and($component->getPlaceholder())->toBe('Pick one');
});

it('select component evaluates closure options and placeholder', function () {
    $component = SelectComponent::make('status')
        ->options(fn () => ['x' => 'X'])
        ->placeholder(fn () => 'Choose');

    expect($component->getOptions())->toBe(['x' => 'X'])
        ->and($component->isMultiple())->toBeFalse()
        ->and($component->getPlaceholder())->toBe('Choose');
});
