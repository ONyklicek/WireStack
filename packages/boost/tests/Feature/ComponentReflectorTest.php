<?php

declare(strict_types=1);

use NyonCode\WireBoost\Support\ComponentReflector;
use NyonCode\WireBoost\Tests\Fixtures\BadForm;
use NyonCode\WireBoost\Tests\Fixtures\BadTable;
use NyonCode\WireBoost\Tests\Fixtures\DemoForm;
use NyonCode\WireBoost\Tests\Fixtures\DemoInfolist;
use NyonCode\WireBoost\Tests\Fixtures\DemoTable;
use NyonCode\WireBoost\Tests\Fixtures\PlainComponent;
use NyonCode\WireBoost\Tests\Fixtures\ThrowingTable;

beforeEach(function () {
    $this->reflector = new ComponentReflector;
});

it('resolves the schema of a table component', function () {
    $described = $this->reflector->describeTable(DemoTable::class);

    expect($described['component'])->toBe(DemoTable::class)
        ->and($described['searchable'])->toBeTrue()
        ->and($described['defaultSort'])->toBe('name')
        ->and(array_column($described['columns'], 'name'))->toBe(['name', 'status'])
        ->and(array_column($described['filters'], 'name'))->toBe(['status'])
        ->and(array_column($described['headerActions'], 'name'))->toBe(['create'])
        ->and(array_column($described['actions'], 'name'))->toBe(['edit', 'delete'])
        ->and(array_column($described['bulkActions'], 'name'))->toBe(['delete']);

    $name = collect($described['columns'])->firstWhere('name', 'name');
    expect($name['sortable'])->toBeTrue()
        ->and($name['searchable'])->toBeTrue()
        ->and($name['type'])->toBe('TextColumn');
});

it('resolves the flattened field schema of a form component', function () {
    $described = $this->reflector->describeForm(DemoForm::class);
    $fields = collect($described['fields']);

    expect($fields->pluck('name')->all())->toBe(['name', 'role', 'active']);

    expect($fields->firstWhere('name', 'name')['container'])->toBe('Section')
        ->and($fields->firstWhere('name', 'active'))->not->toHaveKey('container');
});

it('resolves the entry schema of an infolist component', function () {
    $described = $this->reflector->describeInfolist(DemoInfolist::class);

    expect(array_column($described['entries'], 'name'))->toBe(['name', 'status'])
        ->and(array_column($described['entries'], 'type'))->toBe(['TextEntry', 'IconEntry']);
});

it('reports a missing component class', function () {
    expect($this->reflector->describeTable('No\\Such\\Component'))
        ->toHaveKey('error');
});

it('reports a component without the builder method', function () {
    $described = $this->reflector->describeForm(PlainComponent::class);

    expect($described['error'])->toContain('form()');
});

it('captures exceptions thrown while building', function () {
    $described = $this->reflector->describeTable(ThrowingTable::class);

    expect($described['error'])->toBe('boom');
});

it('tolerates malformed columns when mapping components', function () {
    $described = $this->reflector->describeTable(BadTable::class);

    // The bare string is skipped; the two objects survive with empty names
    // (one lacks getName, the other throws while reading it).
    expect($described)->not->toHaveKey('error')
        ->and($described['columns'])->toHaveCount(2)
        ->and(array_column($described['columns'], 'name'))->toBe(['', '']);
});

it('skips non-component values when flattening a schema', function () {
    $described = $this->reflector->describeForm(BadForm::class);

    expect($described)->not->toHaveKey('error')
        ->and($described['fields'])->toBe([]);
});
