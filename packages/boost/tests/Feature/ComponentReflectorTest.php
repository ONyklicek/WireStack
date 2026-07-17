<?php

declare(strict_types=1);

use NyonCode\WireBoost\Exceptions\ComponentBuildFailedException;
use NyonCode\WireBoost\Exceptions\UnresolvableComponentException;
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

it('fails on a missing component class', function () {
    $this->reflector->describeTable('No\\Such\\Component');
})->throws(UnresolvableComponentException::class, 'does not exist');

it('fails on a component without the builder method', function () {
    $this->reflector->describeForm(PlainComponent::class);
})->throws(UnresolvableComponentException::class, 'no table(), form() or infolist()');

it('wraps an exception thrown while building, keeping the original', function () {
    // The application's own failure is the `previous`, not a flattened string:
    // whoever handles this needs its type and trace.
    try {
        $this->reflector->describeTable(ThrowingTable::class);
        $this->fail('Expected the build to fail.');
    } catch (ComponentBuildFailedException $e) {
        expect($e->getMessage())->toContain('boom')
            ->and($e->component)->toBe(ThrowingTable::class)
            ->and($e->builderMethod)->toBe('table')
            ->and($e->getPrevious()?->getMessage())->toBe('boom');
    }
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
