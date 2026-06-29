<?php

declare(strict_types=1);

use NyonCode\WireBoost\Support\ComponentReflector;
use NyonCode\WireTable\Columns\TextColumn;

beforeEach(function () {
    $this->reflector = new ComponentReflector;
});

it('describes the fluent API of a component type', function () {
    $described = $this->reflector->describeType(TextColumn::class);

    expect($described['exists'])->toBeTrue()
        ->and($described['name'])->toBe('text-column')
        ->and($described['class'])->toBe(TextColumn::class);

    $methods = collect($described['methods']);

    $sortable = $methods->firstWhere('name', 'sortable');
    expect($sortable)->not->toBeNull()
        ->and($sortable['fluent'])->toBeTrue()
        ->and($sortable['signature'])->toStartWith('sortable(');

    // getLabel returns a string, so it is not chainable.
    $label = $methods->firstWhere('name', 'getLabel');
    expect($label['fluent'])->toBeFalse();
});

it('renders parameter signatures including nullable union types', function () {
    $described = $this->reflector->describeType(TextColumn::class);
    $label = collect($described['methods'])->firstWhere('name', 'label');

    expect($label['signature'])->toContain('$label');
});

it('reports a non-existent type as not existing', function () {
    $described = $this->reflector->describeType('No\\Such\\Type');

    expect($described['exists'])->toBeFalse()
        ->and($described['methods'])->toBe([]);
});
