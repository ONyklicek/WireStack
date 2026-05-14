<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;

// --- Plugin Registration ---

it('registers a plugin', function () {
    $manager = new PluginManager;
    $plugin = createTestPlugin('test-plugin');

    $manager->register($plugin);

    expect($manager->has('test-plugin'))->toBeTrue()
        ->and($manager->get('test-plugin'))->toBe($plugin);
});

it('prevents duplicate plugin registration', function () {
    $manager = new PluginManager;
    $manager->register(createTestPlugin('duplicate'));

    $manager->register(createTestPlugin('duplicate'));
})->throws(RuntimeException::class, "Plugin 'duplicate' is already registered.");

it('returns null for unregistered plugin', function () {
    $manager = new PluginManager;

    expect($manager->has('missing'))->toBeFalse()
        ->and($manager->get('missing'))->toBeNull();
});

it('returns all registered plugins', function () {
    $manager = new PluginManager;
    $manager->register(createTestPlugin('a'));
    $manager->register(createTestPlugin('b'));

    expect($manager->all())->toHaveCount(2)
        ->and(array_keys($manager->all()))->toBe(['a', 'b']);
});

// --- Lifecycle ---

it('calls register on plugin during registration', function () {
    $manager = new PluginManager;
    $registered = false;

    $plugin = createTestPlugin('lifecycle', register: function () use (&$registered) {
        $registered = true;
    });

    $manager->register($plugin);

    expect($registered)->toBeTrue();
});

it('calls boot on all plugins', function () {
    $manager = new PluginManager;
    $booted = [];

    $manager->register(createTestPlugin('a', boot: function () use (&$booted) {
        $booted[] = 'a';
    }));
    $manager->register(createTestPlugin('b', boot: function () use (&$booted) {
        $booted[] = 'b';
    }));

    $manager->boot();

    expect($booted)->toBe(['a', 'b']);
});

it('boots only once', function () {
    $manager = new PluginManager;
    $count = 0;

    $manager->register(createTestPlugin('once', boot: function () use (&$count) {
        $count++;
    }));

    $manager->boot();
    $manager->boot();

    expect($count)->toBe(1);
});

// --- Query Pipe Extension ---

it('registers and retrieves query pipes', function () {
    $manager = new PluginManager;
    $pipe = Mockery::mock(QueryPipe::class);

    $manager->addQueryPipe('audit', $pipe);

    expect($manager->getQueryPipes())->toHaveCount(1)
        ->and($manager->getQueryPipes()['audit'])->toBe($pipe);
});

// --- Column/Filter Type Extension ---

it('registers custom column types', function () {
    $manager = new PluginManager;
    $manager->addColumnType('chart', 'App\\Columns\\ChartColumn');

    expect($manager->getColumnTypes())->toBe(['chart' => 'App\\Columns\\ChartColumn']);
});

it('registers custom filter types', function () {
    $manager = new PluginManager;
    $manager->addFilterType('ai', 'App\\Filters\\AiFilter');

    expect($manager->getFilterTypes())->toBe(['ai' => 'App\\Filters\\AiFilter']);
});

// --- Hook System ---

it('runs hooks in order', function () {
    $manager = new PluginManager;
    $order = [];

    $manager->hook('table.querying', function (array $payload) use (&$order) {
        $order[] = 'first';

        return $payload;
    });

    $manager->hook('table.querying', function (array $payload) use (&$order) {
        $order[] = 'second';

        return $payload;
    });

    $manager->runHook('table.querying');

    expect($order)->toBe(['first', 'second']);
});

it('passes and modifies payload through hooks', function () {
    $manager = new PluginManager;

    $manager->hook('table.querying', function (array $payload) {
        $payload['modified'] = true;

        return $payload;
    });

    $result = $manager->runHook('table.querying', ['modified' => false]);

    expect($result['modified'])->toBeTrue();
});

it('returns unmodified payload when hook returns non-array', function () {
    $manager = new PluginManager;

    $manager->hook('table.querying', function (array $payload) {
        // Returns nothing (void)
    });

    $result = $manager->runHook('table.querying', ['key' => 'value']);

    expect($result)->toBe(['key' => 'value']);
});

it('reports hook existence', function () {
    $manager = new PluginManager;

    expect($manager->hasHook('table.querying'))->toBeFalse();

    $manager->hook('table.querying', fn () => null);

    expect($manager->hasHook('table.querying'))->toBeTrue();
});

it('handles hooks with no registered callbacks', function () {
    $manager = new PluginManager;

    $result = $manager->runHook('nonexistent', ['data' => true]);

    expect($result)->toBe(['data' => true]);
});

// --- Helpers ---

function createTestPlugin(
    string $id,
    ?Closure $register = null,
    ?Closure $boot = null,
): Plugin {
    return new class($id, $register, $boot) implements Plugin
    {
        public function __construct(
            private readonly string $id,
            private readonly ?Closure $registerFn = null,
            private readonly ?Closure $bootFn = null,
        ) {}

        public function getId(): string
        {
            return $this->id;
        }

        public function register(PluginManager $manager): void
        {
            if ($this->registerFn) {
                ($this->registerFn)($manager);
            }
        }

        public function boot(PluginManager $manager): void
        {
            if ($this->bootFn) {
                ($this->bootFn)($manager);
            }
        }
    };
}
