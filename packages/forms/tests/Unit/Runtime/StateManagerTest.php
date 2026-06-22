<?php

declare(strict_types=1);

use Livewire\Component;
use NyonCode\WireCore\Core\State\StateContainer;
use NyonCode\WireForms\Forms\Runtime\StateManager;

enum SmStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

enum SmPriority
{
    case Low;
    case High;
}

test('initial state is empty array', function () {
    $manager = new StateManager;

    expect($manager->getState())->toBe([]);
});

test('fill sets state', function () {
    $manager = new StateManager;
    $manager->fill(['name' => 'John', 'email' => 'john@test.com']);

    expect($manager->getState())->toBe(['name' => 'John', 'email' => 'john@test.com']);
});

test('fill reduces enum-cast values to their scalar form', function () {
    $manager = new StateManager;
    $manager->fill([
        'status' => SmStatus::Active,
        'priority' => SmPriority::High,
        'nested' => ['phase' => SmStatus::Inactive],
        'plain' => 'x',
    ]);

    expect($manager->getState())->toBe([
        'status' => 'active',
        'priority' => 'High',
        'nested' => ['phase' => 'inactive'],
        'plain' => 'x',
    ]);
});

test('fill overwrites previous state', function () {
    $manager = new StateManager;
    $manager->fill(['a' => 1]);
    $manager->fill(['b' => 2]);

    expect($manager->getState())->toBe(['b' => 2]);
});

test('setState sets state', function () {
    $manager = new StateManager;
    $manager->setState(['key' => 'value']);

    expect($manager->getState())->toBe(['key' => 'value']);
});

test('setStatePath stores path', function () {
    $manager = new StateManager;
    $manager->setStatePath('data');

    expect($manager->getStatePath())->toBe('data');
});

test('getStatePath returns null by default', function () {
    $manager = new StateManager;

    expect($manager->getStatePath())->toBeNull();
});

test('hasLivewire returns false without livewire', function () {
    $manager = new StateManager;

    expect($manager->hasLivewire())->toBeFalse();
});

test('getLivewire returns null without livewire', function () {
    $manager = new StateManager;

    expect($manager->getLivewire())->toBeNull();
});

test('setLivewire stores component', function () {
    $manager = new StateManager;
    $mock = Mockery::mock(Component::class);

    $manager->setLivewire($mock);

    expect($manager->hasLivewire())->toBeTrue()
        ->and($manager->getLivewire())->toBe($mock);
});

test('setLivewire with null clears livewire', function () {
    $manager = new StateManager;
    $mock = Mockery::mock(Component::class);

    $manager->setLivewire($mock);
    $manager->setLivewire(null);

    expect($manager->hasLivewire())->toBeFalse();
});

test('fill syncs to livewire when bound', function () {
    $manager = new StateManager;
    $component = new class extends Component
    {
        public array $data = [];

        public function render()
        {
            return '';
        }
    };

    $manager->setLivewire($component);
    $manager->setStatePath('data');
    $manager->fill(['name' => 'John']);

    expect($component->data)->toBe(['name' => 'John']);
});

test('getState reads from livewire when bound', function () {
    $manager = new StateManager;
    $component = new class extends Component
    {
        public array $data = ['name' => 'FromLivewire'];

        public function render()
        {
            return '';
        }
    };

    $manager->setLivewire($component);
    $manager->setStatePath('data');

    expect($manager->getState())->toBe(['name' => 'FromLivewire']);
});

test('setState syncs to livewire when bound', function () {
    $manager = new StateManager;
    $component = new class extends Component
    {
        public array $data = [];

        public function render()
        {
            return '';
        }
    };

    $manager->setLivewire($component);
    $manager->setStatePath('data');
    $manager->setState(['email' => 'test@test.com']);

    expect($component->data)->toBe(['email' => 'test@test.com']);
});

test('getState uses local state without livewire even with statePath', function () {
    $manager = new StateManager;
    $manager->setStatePath('data');
    $manager->fill(['name' => 'Local']);

    expect($manager->getState())->toBe(['name' => 'Local']);
});

test('getState uses local state without statePath even with livewire', function () {
    $manager = new StateManager;
    $mock = Mockery::mock(Component::class);

    $manager->setLivewire($mock);
    // No statePath set
    $manager->fill(['name' => 'Local']);

    expect($manager->getState())->toBe(['name' => 'Local']);
});

// ─── Core StateContainer Integration (Phase 4) ─────────────────────────────

test('getContainer returns StateContainer instance', function () {
    $manager = new StateManager;

    $container = $manager->getContainer();
    expect($container)->toBeInstanceOf(StateContainer::class);
});

test('get reads dot-notation paths from state', function () {
    $manager = new StateManager;
    $manager->fill(['user' => ['name' => 'Alice', 'email' => 'alice@test.com']]);

    expect($manager->get('user.name'))->toBe('Alice')
        ->and($manager->get('user.email'))->toBe('alice@test.com')
        ->and($manager->get('user.missing', 'default'))->toBe('default');
});

test('set writes dot-notation paths to state', function () {
    $manager = new StateManager;
    $manager->fill(['user' => ['name' => 'Alice']]);

    $manager->set('user.name', 'Bob');

    expect($manager->getState()['user']['name'])->toBe('Bob');
});

test('isDirty returns false on fresh state', function () {
    $manager = new StateManager;
    $manager->fill(['name' => 'Alice']);

    expect($manager->isDirty())->toBeFalse();
});

test('isDirty returns true after set()', function () {
    $manager = new StateManager;
    $manager->fill(['name' => 'Alice']);

    $manager->set('name', 'Bob');

    expect($manager->isDirty())->toBeTrue()
        ->and($manager->getDirtyPaths())->toContain('name');
});

test('set syncs to livewire when bound', function () {
    $manager = new StateManager;
    $component = new class extends Component
    {
        public array $data = ['name' => 'Old'];

        public function render()
        {
            return '';
        }
    };

    $manager->setLivewire($component);
    $manager->setStatePath('data');
    $manager->set('name', 'New');

    expect($component->data['name'])->toBe('New');
});
