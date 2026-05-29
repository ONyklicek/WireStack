<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Plugin\Contracts\HasConfiguration;
use NyonCode\WireCore\Core\Plugin\Contracts\HasDependencies;
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

// --- Action Type Extension ---

it('registers custom action types', function () {
    $manager = new PluginManager;
    $manager->addActionType('workflow', 'App\\Actions\\WorkflowAction');

    expect($manager->getActionTypes())->toBe(['workflow' => 'App\\Actions\\WorkflowAction']);
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

it('runs hooks by ascending priority', function () {
    $manager = new PluginManager;
    $order = [];

    $manager->hook('table.querying', function (array $payload) use (&$order) {
        $order[] = 'audit';

        return $payload;
    }, priority: 100);

    $manager->hook('table.querying', function (array $payload) use (&$order) {
        $order[] = 'scope';

        return $payload;
    }, priority: -100);

    $manager->hook('table.querying', function (array $payload) use (&$order) {
        $order[] = 'default';

        return $payload;
    });

    $manager->runHook('table.querying');

    expect($order)->toBe(['scope', 'default', 'audit']);
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

it('runs typed hooks with object payloads', function () {
    $manager = new PluginManager;
    $payload = (object) ['steps' => []];

    $manager->hook('typed.example', function (object $payload): object {
        $payload->steps[] = 'audit';

        return $payload;
    }, priority: 100);

    $manager->hook('typed.example', function (object $payload): object {
        $payload->steps[] = 'scope';

        return $payload;
    }, priority: -100);

    $result = $manager->runTypedHook('typed.example', $payload);

    expect($result)->toBe($payload)
        ->and($result->steps)->toBe(['scope', 'audit']);
});

it('allows typed hooks to replace the payload object', function () {
    $manager = new PluginManager;
    $payload = (object) ['value' => 'original'];
    $replacement = (object) ['value' => 'replacement'];

    $manager->hook('typed.example', fn (object $payload): object => $replacement);

    expect($manager->runTypedHook('typed.example', $payload))->toBe($replacement);
});

it('keeps typed payload unchanged when callback returns a non-object', function () {
    $manager = new PluginManager;
    $payload = (object) ['value' => 'original'];

    $manager->hook('typed.example', fn (object $payload): string => 'ignored');

    expect($manager->runTypedHook('typed.example', $payload))->toBe($payload);
});

// --- Dependencies ---

it('prevents registering a plugin when a dependency is missing', function () {
    $manager = new PluginManager;

    $manager->register(createDependentPlugin('needs-core', ['core']));
})->throws(RuntimeException::class, "Plugin 'needs-core' requires 'core' which is not registered.");

it('registers a plugin when dependencies are already registered', function () {
    $manager = new PluginManager;

    $manager->register(createTestPlugin('core'));
    $manager->register(createDependentPlugin('needs-core', ['core']));

    expect($manager->has('needs-core'))->toBeTrue();
});

// --- Configuration ---

it('merges plugin default config with user config', function () {
    config()->set('wire-core.plugins.config.export', [
        'format' => 'xlsx',
    ]);

    $manager = new PluginManager;
    $manager->register(createConfigurablePlugin('export', [
        'format' => 'csv',
        'chunk_size' => 500,
    ]));

    expect($manager->getPluginConfig('export'))->toBe([
        'format' => 'xlsx',
        'chunk_size' => 500,
    ]);
});

it('returns empty config for plugins without configuration', function () {
    $manager = new PluginManager;
    $manager->register(createTestPlugin('plain'));

    expect($manager->getPluginConfig('plain'))->toBe([]);
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

/**
 * @param  array<int, string>  $dependencies
 */
function createDependentPlugin(string $id, array $dependencies): Plugin
{
    return new class($id, $dependencies) implements HasDependencies, Plugin
    {
        /**
         * @param  array<int, string>  $dependencies
         */
        public function __construct(
            private readonly string $id,
            private readonly array $dependencies,
        ) {}

        public function getId(): string
        {
            return $this->id;
        }

        /**
         * @return array<int, string>
         */
        public function dependencies(): array
        {
            return $this->dependencies;
        }

        public function register(PluginManager $manager): void
        {
            //
        }

        public function boot(PluginManager $manager): void
        {
            //
        }
    };
}

/**
 * @param  array<string, mixed>  $defaults
 */
function createConfigurablePlugin(string $id, array $defaults): Plugin
{
    return new class($id, $defaults) implements HasConfiguration, Plugin
    {
        /**
         * @param  array<string, mixed>  $defaults
         */
        public function __construct(
            private readonly string $id,
            private readonly array $defaults,
        ) {}

        public function getId(): string
        {
            return $this->id;
        }

        /**
         * @return array<string, mixed>
         */
        public function defaultConfig(): array
        {
            return $this->defaults;
        }

        public function register(PluginManager $manager): void
        {
            //
        }

        public function boot(PluginManager $manager): void
        {
            //
        }
    };
}
