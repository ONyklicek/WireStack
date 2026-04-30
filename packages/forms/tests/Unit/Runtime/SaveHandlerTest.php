<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Config\FormConfig;
use NyonCode\WireForms\Forms\Runtime\FormRuntime;
use NyonCode\WireForms\Forms\Runtime\SaveHandler;
use NyonCode\WireForms\Forms\Runtime\StateManager;
use NyonCode\WireForms\Tests\TestCase;

uses(TestCase::class);

function createRuntimeWithState(FormConfig $config, array $state = []): FormRuntime
{
    $stateManager = new StateManager;
    $stateManager->fill($state);

    return new FormRuntime($config, $stateManager);
}

// ─── Validation step ──────────────────────────────────────────

test('save validates data first', function () {
    $config = new FormConfig(
        schema: [
            TextInput::make('name')->required(),
        ],
        using: fn (array $data) => $data,
    );

    $runtime = createRuntimeWithState($config, []);

    $handler = new SaveHandler($config, $runtime);

    expect(fn () => $handler->save())
        ->toThrow(ValidationException::class);
});

// ─── Mutation step ────────────────────────────────────────────

test('mutateDataBeforeSave transforms data', function () {
    $config = new FormConfig(
        schema: [
            TextInput::make('name'),
        ],
        mutateDataBeforeSave: fn (array $data) => [...$data, 'extra' => 'added'],
        using: fn (array $data) => $data,
    );

    $runtime = createRuntimeWithState($config, ['name' => 'John']);

    $handler = new SaveHandler($config, $runtime);
    $result = $handler->save();

    expect($result)->toBe(['name' => 'John', 'extra' => 'added']);
});

test('mutateDataBeforeSave returning null cancels save', function () {
    $config = new FormConfig(
        schema: [
            TextInput::make('name'),
        ],
        mutateDataBeforeSave: fn (array $data) => null,
        using: fn (array $data) => $data,
    );

    $runtime = createRuntimeWithState($config, ['name' => 'John']);

    $handler = new SaveHandler($config, $runtime);
    $result = $handler->save();

    expect($result)->toBeNull();
});

// ─── beforeSave hook ──────────────────────────────────────────

test('beforeSave hook is called with data', function () {
    $hookData = null;

    $config = new FormConfig(
        schema: [
            TextInput::make('name'),
        ],
        beforeSave: function (array $data) use (&$hookData) {
            $hookData = $data;
        },
        using: fn (array $data) => $data,
    );

    $runtime = createRuntimeWithState($config, ['name' => 'John']);

    $handler = new SaveHandler($config, $runtime);
    $handler->save();

    expect($hookData)->toBe(['name' => 'John']);
});

test('beforeSave return value is ignored', function () {
    $config = new FormConfig(
        schema: [
            TextInput::make('name'),
        ],
        beforeSave: fn (array $data) => 'should be ignored',
        using: fn (array $data) => $data,
    );

    $runtime = createRuntimeWithState($config, ['name' => 'John']);

    $handler = new SaveHandler($config, $runtime);
    $result = $handler->save();

    expect($result)->toBe(['name' => 'John']);
});

// ─── Persistence step ─────────────────────────────────────────

test('using closure overrides default persistence', function () {
    $config = new FormConfig(
        schema: [
            TextInput::make('name'),
        ],
        using: fn (array $data) => ['custom' => true, ...$data],
    );

    $runtime = createRuntimeWithState($config, ['name' => 'John']);

    $handler = new SaveHandler($config, $runtime);
    $result = $handler->save();

    expect($result)->toBe(['custom' => true, 'name' => 'John']);
});

test('save throws when no model or using configured', function () {
    $config = new FormConfig(
        schema: [
            TextInput::make('name'),
        ],
    );

    $runtime = createRuntimeWithState($config, ['name' => 'John']);

    $handler = new SaveHandler($config, $runtime);

    expect(fn () => $handler->save())
        ->toThrow(InvalidArgumentException::class, 'Form has no model configured');
});

// ─── afterSave hook ───────────────────────────────────────────

test('afterSave hook is called with record', function () {
    $hookRecord = null;

    $config = new FormConfig(
        schema: [
            TextInput::make('name'),
        ],
        afterSave: function (mixed $record) use (&$hookRecord) {
            $hookRecord = $record;
        },
        using: fn (array $data) => ['saved' => true, ...$data],
    );

    $runtime = createRuntimeWithState($config, ['name' => 'John']);

    $handler = new SaveHandler($config, $runtime);
    $handler->save();

    expect($hookRecord)->toBe(['saved' => true, 'name' => 'John']);
});

// ─── Hook order ───────────────────────────────────────────────

test('hooks execute in correct order: mutate → beforeSave → persist → afterSave', function () {
    $order = [];

    $config = new FormConfig(
        schema: [
            TextInput::make('name'),
        ],
        mutateDataBeforeSave: function (array $data) use (&$order) {
            $order[] = 'mutate';

            return $data;
        },
        beforeSave: function (array $data) use (&$order) {
            $order[] = 'beforeSave';
        },
        afterSave: function (mixed $record) use (&$order) {
            $order[] = 'afterSave';
        },
        using: function (array $data) use (&$order) {
            $order[] = 'persist';

            return $data;
        },
    );

    $runtime = createRuntimeWithState($config, ['name' => 'John']);

    $handler = new SaveHandler($config, $runtime);
    $handler->save();

    expect($order)->toBe(['mutate', 'beforeSave', 'persist', 'afterSave']);
});

// ─── Notification step ────────────────────────────────────────

test('save does not notify when successMessage is null', function () {
    $config = new FormConfig(
        schema: [
            TextInput::make('name'),
        ],
        using: fn (array $data) => $data,
        successMessage: null,
    );

    $runtime = createRuntimeWithState($config, ['name' => 'John']);

    $handler = new SaveHandler($config, $runtime);
    // Should not throw even without NotificationManager bound
    $result = $handler->save();

    expect($result)->toBe(['name' => 'John']);
});

test('save does not fail when NotificationManager not bound', function () {
    $config = new FormConfig(
        schema: [
            TextInput::make('name'),
        ],
        using: fn (array $data) => $data,
        successMessage: 'Saved!',
    );

    $runtime = createRuntimeWithState($config, ['name' => 'John']);

    $handler = new SaveHandler($config, $runtime);
    // Should silently skip notification when manager not bound
    $result = $handler->save();

    expect($result)->toBe(['name' => 'John']);
});

// ─── Closure successMessage ───────────────────────────────────

test('save with closure successMessage resolves it', function () {
    $config = new FormConfig(
        schema: [
            TextInput::make('name'),
        ],
        using: fn (array $data) => $data,
        successMessage: fn (mixed $record) => 'Custom: '.$record['name'],
    );

    $runtime = createRuntimeWithState($config, ['name' => 'John']);

    $handler = new SaveHandler($config, $runtime);
    // Should not throw
    $result = $handler->save();

    expect($result)->toBe(['name' => 'John']);
});

// ─── Complete lifecycle ───────────────────────────────────────

test('full save lifecycle with all hooks', function () {
    $log = [];

    $config = new FormConfig(
        schema: [
            TextInput::make('name')->required(),
        ],
        mutateDataBeforeSave: function (array $data) use (&$log) {
            $log[] = 'mutated';

            return [...$data, 'mutated' => true];
        },
        beforeSave: function (array $data) use (&$log) {
            $log[] = 'before:'.$data['name'];
        },
        afterSave: function (mixed $record) use (&$log) {
            $log[] = 'after:'.($record['persisted'] ? 'yes' : 'no');
        },
        using: function (array $data) use (&$log) {
            $log[] = 'persisted';

            return [...$data, 'persisted' => true];
        },
        successMessage: null,
    );

    $runtime = createRuntimeWithState($config, ['name' => 'Jane']);

    $handler = new SaveHandler($config, $runtime);
    $result = $handler->save();

    expect($log)->toBe(['mutated', 'before:Jane', 'persisted', 'after:yes'])
        ->and($result['name'])->toBe('Jane')
        ->and($result['mutated'])->toBeTrue()
        ->and($result['persisted'])->toBeTrue();
});
