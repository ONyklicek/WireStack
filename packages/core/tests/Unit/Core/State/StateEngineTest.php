<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\State\DirtyStateTracker;
use NyonCode\WireCore\Core\State\StateContainer;
use NyonCode\WireCore\Core\State\StateHydrator;
use NyonCode\WireCore\Core\State\StatePathResolver;
use NyonCode\WireCore\Core\State\StateSerializer;

// ─── StatePathResolver ───────────────────────────────────────────────────────

it('resolves a top-level path', function () {
    $state = ['name' => 'John'];

    expect(StatePathResolver::resolve($state, 'name'))->toBe('John');
});

it('resolves a nested dot-notation path', function () {
    $state = ['user' => ['address' => ['city' => 'Prague']]];

    expect(StatePathResolver::resolve($state, 'user.address.city'))->toBe('Prague');
});

it('returns null for a non-existent path', function () {
    $state = ['foo' => 'bar'];

    expect(StatePathResolver::resolve($state, 'baz'))->toBeNull();
});

it('sets a value at a nested path', function () {
    $state = [];

    StatePathResolver::set($state, 'user.profile.name', 'Alice');

    expect($state)->toBe(['user' => ['profile' => ['name' => 'Alice']]]);
});

it('checks existence with has()', function () {
    $state = ['a' => ['b' => 'c']];

    expect(StatePathResolver::has($state, 'a.b'))->toBeTrue()
        ->and(StatePathResolver::has($state, 'a.x'))->toBeFalse();
});

it('forgets a nested key', function () {
    $state = ['user' => ['name' => 'John', 'age' => 30]];

    StatePathResolver::forget($state, 'user.age');

    expect($state)->toBe(['user' => ['name' => 'John']]);
});

it('splits a path into segments', function () {
    expect(StatePathResolver::segments('user.address.city'))
        ->toBe(['user', 'address', 'city']);
});

it('handles single-segment paths in segments()', function () {
    expect(StatePathResolver::segments('name'))->toBe(['name']);
});

// ─── DirtyStateTracker ───────────────────────────────────────────────────────

it('reports clean state initially', function () {
    $tracker = new DirtyStateTracker;

    expect($tracker->isDirty())->toBeFalse()
        ->and($tracker->getDirtyPaths())->toBe([]);
});

it('marks a path as dirty', function () {
    $tracker = new DirtyStateTracker;
    $tracker->markDirty('user.name');

    expect($tracker->isDirty())->toBeTrue()
        ->and($tracker->isDirty('user.name'))->toBeTrue()
        ->and($tracker->isDirty('user.email'))->toBeFalse();
});

it('returns all dirty paths', function () {
    $tracker = new DirtyStateTracker;
    $tracker->markDirty('a');
    $tracker->markDirty('b');

    expect($tracker->getDirtyPaths())->toContain('a', 'b');
});

it('resets dirty state', function () {
    $tracker = new DirtyStateTracker;
    $tracker->markDirty('field');
    $tracker->reset();

    expect($tracker->isDirty())->toBeFalse()
        ->and($tracker->getDirtyPaths())->toBe([]);
});

it('stores and retrieves original values', function () {
    $tracker = new DirtyStateTracker;
    $tracker->setOriginal('name', 'OldValue');

    expect($tracker->getOriginal('name'))->toBe('OldValue');
});

// ─── StateContainer ──────────────────────────────────────────────────────────

it('initializes with given state', function () {
    $container = new StateContainer(['key' => 'value']);

    expect($container->all())->toBe(['key' => 'value']);
});

it('gets a value with a default', function () {
    $container = new StateContainer([]);

    expect($container->get('missing', 'fallback'))->toBe('fallback');
});

it('sets and retrieves nested values', function () {
    $container = new StateContainer([]);
    $container->set('user.name', 'Bob');

    expect($container->get('user.name'))->toBe('Bob')
        ->and($container->has('user.name'))->toBeTrue();
});

it('forgets a key from the container', function () {
    $container = new StateContainer(['a' => 1, 'b' => 2]);
    $container->forget('a');

    expect($container->has('a'))->toBeFalse()
        ->and($container->all())->toBe(['b' => 2]);
});

it('replaces all state', function () {
    $container = new StateContainer(['old' => 'data']);
    $container->replace(['new' => 'state']);

    expect($container->all())->toBe(['new' => 'state']);
});

it('merges state', function () {
    $container = new StateContainer(['a' => 1]);
    $container->merge(['b' => 2]);

    expect($container->all())->toBe(['a' => 1, 'b' => 2]);
});

it('tracks dirty state when setting values', function () {
    $container = new StateContainer(['name' => 'Original']);
    $container->set('name', 'Changed');

    $tracker = $container->getDirtyTracker();

    expect($tracker->isDirty('name'))->toBeTrue();
});

it('returns falsy values correctly from get() instead of default', function () {
    $container = new StateContainer(['zero' => 0, 'empty' => '', 'false' => false, 'null' => null]);

    expect($container->get('zero', 'default'))->toBe(0)
        ->and($container->get('empty', 'default'))->toBe('')
        ->and($container->get('false', 'default'))->toBe(false)
        ->and($container->get('null', 'default'))->toBe(null)
        ->and($container->get('missing', 'default'))->toBe('default');
});

it('tracks dirty state when replace() changes values', function () {
    $container = new StateContainer(['name' => 'John', 'age' => 30]);
    $container->replace(['name' => 'Jane', 'age' => 30]);

    $tracker = $container->getDirtyTracker();

    expect($tracker->isDirty('name'))->toBeTrue()
        ->and($tracker->isDirty('age'))->toBeFalse()
        ->and($tracker->getOriginal('name'))->toBe('John');
});

it('replaceClean sets state without dirty tracking', function () {
    $container = new StateContainer(['name' => 'John']);
    $container->replaceClean(['name' => 'Jane', 'age' => 25]);

    $tracker = $container->getDirtyTracker();

    expect($container->all())->toBe(['name' => 'Jane', 'age' => 25])
        ->and($tracker->isDirty())->toBeFalse()
        ->and($tracker->getDirtyPaths())->toBe([]);
});

it('replaceClean resets previous dirty state', function () {
    $container = new StateContainer([]);
    $container->set('name', 'Alice'); // marks dirty
    $container->replaceClean(['name' => 'Bob']); // should reset

    expect($container->getDirtyTracker()->isDirty())->toBeFalse();
});

it('tracks dirty state when replace() adds new keys', function () {
    $container = new StateContainer(['a' => 1]);
    $container->replace(['a' => 1, 'b' => 2]);

    $tracker = $container->getDirtyTracker();

    expect($tracker->isDirty('a'))->toBeFalse()
        ->and($tracker->isDirty('b'))->toBeTrue();
});

it('tracks dirty state when replace() removes keys', function () {
    $container = new StateContainer(['a' => 1, 'b' => 2]);
    $container->replace(['a' => 1]);

    $tracker = $container->getDirtyTracker();

    expect($tracker->isDirty('b'))->toBeTrue()
        ->and($tracker->getOriginal('b'))->toBe(2);
});

// ─── StateHydrator ───────────────────────────────────────────────────────────

it('hydrates int values', function () {
    $hydrator = new StateHydrator;

    expect($hydrator->hydrateValue('42', 'int'))->toBe(42)
        ->and($hydrator->hydrateValue('0', 'int'))->toBe(0);
});

it('hydrates float values', function () {
    $hydrator = new StateHydrator;

    expect($hydrator->hydrateValue('3.14', 'float'))->toBe(3.14);
});

it('hydrates bool values', function () {
    $hydrator = new StateHydrator;

    expect($hydrator->hydrateValue('1', 'bool'))->toBeTrue()
        ->and($hydrator->hydrateValue('0', 'bool'))->toBeFalse()
        ->and($hydrator->hydrateValue('', 'bool'))->toBeFalse();
});

it('hydrates string values', function () {
    $hydrator = new StateHydrator;

    expect($hydrator->hydrateValue(123, 'string'))->toBe('123');
});

it('hydrates array values from json string', function () {
    $hydrator = new StateHydrator;

    expect($hydrator->hydrateValue('["a","b"]', 'json'))->toBe(['a', 'b']);
});

it('hydrates date values', function () {
    $hydrator = new StateHydrator;
    $result = $hydrator->hydrateValue('2024-06-15', 'date');

    expect($result)->toBeInstanceOf(DateTimeInterface::class);
});

it('hydrates datetime values', function () {
    $hydrator = new StateHydrator;
    $result = $hydrator->hydrateValue('2024-06-15 10:30:00', 'datetime');

    expect($result)->toBeInstanceOf(DateTimeInterface::class);
});

it('handles null gracefully during hydration', function () {
    $hydrator = new StateHydrator;

    expect($hydrator->hydrateValue(null, 'int'))->toBeNull();
});

it('hydrates full request data with definitions', function () {
    $hydrator = new StateHydrator;
    $result = $hydrator->hydrate(
        ['age' => '25', 'active' => '1'],
        ['age' => 'int', 'active' => 'bool'],
    );

    expect($result['age'])->toBe(25)
        ->and($result['active'])->toBeTrue();
});

// ─── StateSerializer ─────────────────────────────────────────────────────────

it('serializes DateTimeInterface to string', function () {
    $serializer = new StateSerializer;
    $date = new DateTimeImmutable('2024-01-15 12:00:00');

    $result = $serializer->serializeValue($date);

    expect($result)->toBeString()
        ->and($result)->toContain('2024-01-15');
});

it('serializes a backed enum to its value', function () {
    $serializer = new StateSerializer;

    $enum = TestBackedEnum::Active;
    $result = $serializer->serializeValue($enum);

    expect($result)->toBe('active');
});

it('serializes a unit enum to its name', function () {
    $serializer = new StateSerializer;

    $enum = TestUnitEnum::Draft;
    $result = $serializer->serializeValue($enum);

    expect($result)->toBe('Draft');
});

it('recursively serializes arrays', function () {
    $serializer = new StateSerializer;
    $date = new DateTimeImmutable('2024-03-01');

    $result = $serializer->serialize(['created' => $date, 'tags' => ['a', 'b']]);

    expect($result['created'])->toBeString()
        ->and($result['tags'])->toBe(['a', 'b']);
});

it('deserializes a value with a given type', function () {
    $serializer = new StateSerializer;

    expect($serializer->deserializeValue('42', 'int'))->toBe(42)
        ->and($serializer->deserializeValue('true', 'bool'))->toBeTrue();
});

it('serializes and deserializes state roundtrip', function () {
    $serializer = new StateSerializer;
    $original = ['name' => 'Test', 'count' => 5, 'items' => [1, 2, 3]];

    $serialized = $serializer->serialize($original);
    $deserialized = $serializer->deserialize($serialized);

    expect($deserialized)->toBe($original);
});

// ─── Test Helpers (Enums) ────────────────────────────────────────────────────

enum TestBackedEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

enum TestUnitEnum
{
    case Draft;
    case Published;
}
