<?php

declare(strict_types=1);

use NyonCode\WireBoost\Support\ComponentReflector;
use NyonCode\WireBoost\Tests\Fixtures\ApiSurface;
use NyonCode\WireTable\Columns\BadgeColumn;
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

it('lists the values an enum-typed parameter accepts', function () {
    // `color(string $color)` names the type but not the vocabulary, which is
    // the part an agent would otherwise have to guess.
    $color = collect($this->reflector->describeType(BadgeColumn::class)['methods'])
        ->firstWhere('name', 'color');

    expect($color['accepts']['color'])
        ->toContain('primary', 'success', 'danger', 'rose', 'fuchsia');
});

it('lists accepted values for every enum in a union type', function () {
    $size = collect($this->reflector->describeType(BadgeColumn::class)['methods'])
        ->firstWhere('name', 'size');

    expect($size['accepts']['size'])->toBe(['xs', 'sm', 'md', 'lg', 'xl']);
});

it('lists accepted values per parameter, not per method', function () {
    $icon = collect($this->reflector->describeType(BadgeColumn::class)['methods'])
        ->firstWhere('name', 'icon');

    // $icon takes an open-ended name; only $position is a closed vocabulary.
    expect($icon['accepts'])->toBe(['position' => ['before', 'after']]);
});

it('omits accepts for methods with no closed vocabulary', function () {
    $label = collect($this->reflector->describeType(TextColumn::class)['methods'])
        ->firstWhere('name', 'label');

    expect($label)->not->toHaveKey('accepts');
});

it('renders default values into the signature', function () {
    $sortable = collect($this->reflector->describeType(TextColumn::class)['methods'])
        ->firstWhere('name', 'sortable');

    expect($sortable['signature'])->toContain('$sortable = true');
});

it('summarises a method from its doc-block', function () {
    $sortable = collect($this->reflector->describeType(TextColumn::class)['methods'])
        ->firstWhere('name', 'sortable');

    expect($sortable['summary'])->toBe('Set whether the column is sortable.');
});

it('summarises a class from its doc-block', function () {
    expect($this->reflector->describeType(ApiSurface::class)['summary'])
        ->toBe('Every shape of parameter default the reflector has to render.');
});

it('omits the summary of an undocumented method', function () {
    $method = collect($this->reflector->describeType(ApiSurface::class)['methods'])
        ->firstWhere('name', 'undocumented');

    expect($method)->not->toHaveKey('summary');
});

it('renders every kind of parameter default as it would be written in php', function () {
    $signature = collect($this->reflector->describeType(ApiSurface::class)['methods'])
        ->firstWhere('name', 'defaults')['signature'];

    expect($signature)
        ->toContain('$nothing = null')
        ->toContain('$yes = true')
        ->toContain('$no = false')
        ->toContain("\$text = 'hello'")
        ->toContain('$number = 5')
        ->toContain('$ratio = 1.5')
        ->toContain('$empty = []')
        ->toContain('$filled = [...]')
        ->toContain('$size = Size::Md');
});

it('renders a required parameter with no default', function () {
    $signature = collect($this->reflector->describeType(ApiSurface::class)['methods'])
        ->firstWhere('name', 'required')['signature'];

    expect($signature)->toBe('required(string $name)');
});

it('renders a variadic parameter', function () {
    $signature = collect($this->reflector->describeType(ApiSurface::class)['methods'])
        ->firstWhere('name', 'variadic')['signature'];

    expect($signature)->toBe('variadic(string ...$names)');
});

it('renders an untyped parameter', function () {
    $signature = collect($this->reflector->describeType(ApiSurface::class)['methods'])
        ->firstWhere('name', 'untyped')['signature'];

    expect($signature)->toBe("untyped(\$whatever = 'x')");
});

it('marks a method that does not return the component as not fluent', function () {
    $method = collect($this->reflector->describeType(ApiSurface::class)['methods'])
        ->firstWhere('name', 'notFluent');

    expect($method['fluent'])->toBeFalse();
});
