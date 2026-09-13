<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\EnvFile;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WireModuleSettings\Install\CacheSettingsInMemory;

/*
 * Somewhere to cache settings that is not the table they came from.
 *
 * Settings are read on nearly every request, so caching them in the database
 * means the cache lookup costs the query it was meant to save. It works — it is
 * simply the one configuration where the cache is not one.
 */

/**
 * A console that answers from a script and records what it was told.
 *
 * @param  array<int, string>  $answers
 * @param  array<int, string>  $said
 */
function csConsole(array $answers = [], array &$said = [], bool $interactive = true): SetupConsole
{
    return new class($answers, $said, $interactive) implements SetupConsole
    {
        /**
         * @param  array<int, string>  $answers
         * @param  array<int, string>  $said
         */
        public function __construct(private array $answers, private array &$said, private bool $interactive) {}

        public function ask(string $question, ?string $default = null): string
        {
            return array_shift($this->answers) ?? (string) $default;
        }

        public function secret(string $question): string
        {
            return array_shift($this->answers) ?? '';
        }

        public function confirm(string $question, bool $default = true): bool
        {
            return $default;
        }

        public function choose(string $question, array $options, ?string $default = null): string
        {
            return array_shift($this->answers) ?? (string) $default;
        }

        public function note(string $message): void
        {
            $this->said[] = $message;
        }

        public function warn(string $message): void
        {
            $this->said[] = $message;
        }

        public function isInteractive(): bool
        {
            return $this->interactive;
        }
    };
}

function csEnv(string $contents = "APP_NAME=Laravel\n"): EnvFile
{
    $path = sys_get_temp_dir().'/wire-settings-env-'.getmypid().'-'.uniqid().'.env';
    file_put_contents($path, $contents);
    register_shutdown_function(static fn () => @unlink($path));

    return new EnvFile($path);
}

beforeEach(function () {
    config()->set('cache.stores', ['file' => [], 'database' => [], 'redis' => [], 'memcached' => []]);
});

it('is contributed by this module', function () {
    expect(SetupRegistry::instance()->all())->toContain(CacheSettingsInMemory::class);
});

it('is done when the cache is already somewhere sensible', function () {
    config()->set('wire-module-settings.cache.store', 'redis');

    $step = new CacheSettingsInMemory(csEnv());

    expect($step->state())->toBe(SetupState::Done)
        ->and($step->summary())->toBe('cached in `redis`')
        ->and($step->label())->toBe('Settings cache')
        ->and($step->sort())->toBe(800);
});

it('is pending when it is on the database and there is somewhere better', function () {
    config()->set('wire-module-settings.cache.store', 'database');

    $step = new CacheSettingsInMemory(csEnv());

    expect($step->state())->toBe(SetupState::Pending)
        ->and($step->summary())->toContain('costs the query it saves');
});

it('falls back to the application cache when this module names none', function () {
    config()->set('wire-module-settings.cache.store', null);
    config()->set('cache.default', 'database');

    expect((new CacheSettingsInMemory(csEnv()))->state())->toBe(SetupState::Pending);
});

it('leaves an application alone when there is nowhere better to go', function () {
    // Not blocked either: this is a working installation, just not a fast one,
    // and the answer is a server rather than a setting.
    config()->set('wire-module-settings.cache.store', 'database');
    config()->set('cache.stores', ['file' => [], 'database' => []]);

    $step = new CacheSettingsInMemory(csEnv());

    expect($step->state())->toBe(SetupState::Done)
        ->and($step->summary())->toContain('the table it was meant to save a query on');
});

it('offers only the stores this application has actually configured', function () {
    // Naming `redis` in an application with no redis connection swaps a slow
    // cache for a broken one.
    config()->set('wire-module-settings.cache.store', 'database');
    config()->set('cache.stores', ['file' => [], 'database' => [], 'memcached' => []]);
    $env = csEnv();

    expect((new CacheSettingsInMemory($env))->apply(csConsole()))->toBe(SetupOutcome::Applied)
        ->and($env->get('WIRE_SETTINGS_CACHE_STORE'))->toBe('memcached');
});

it('writes the store that was picked', function () {
    config()->set('wire-module-settings.cache.store', 'database');
    $env = csEnv();
    $said = [];

    expect((new CacheSettingsInMemory($env))->apply(csConsole(['memcached'], $said)))->toBe(SetupOutcome::Applied)
        ->and($env->get('WIRE_SETTINGS_CACHE_STORE'))->toBe('memcached')
        ->and(implode("\n", $said))->toContain('memcached');
});

it('is blocked when there is no .env to change it in', function () {
    config()->set('wire-module-settings.cache.store', 'database');

    $step = new CacheSettingsInMemory(new EnvFile(sys_get_temp_dir().'/absent-'.uniqid().'.env'));

    expect($step->state())->toBe(SetupState::Blocked)
        ->and($step->summary())->toContain('no .env');
});

it('fails rather than pretending, when .env cannot be written', function () {
    config()->set('wire-module-settings.cache.store', 'database');
    $said = [];
    $step = new CacheSettingsInMemory(new EnvFile(sys_get_temp_dir().'/absent-'.uniqid().'.env'));

    expect($step->apply(csConsole([], $said)))->toBe(SetupOutcome::Failed)
        ->and(implode("\n", $said))->toContain('WIRE_SETTINGS_CACHE_STORE');
});
