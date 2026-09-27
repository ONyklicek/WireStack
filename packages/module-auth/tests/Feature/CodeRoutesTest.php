<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NyonCode\WireCore\Exceptions\RouteRegistrationException;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\RoutesFile;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WireModuleAuth\Install\RouteOneTimeCodes;

/*
 * The code flows are routed by the application — `Route::wire('auth-codes')` in
 * routes/web.php — never by the provider. Only the flows that are on, the
 * application's group around them, and the names fixed, since the screens and
 * the mails link to them.
 */

/** @param  array<int, string>  $said */
function acConsole(array &$said = []): SetupConsole
{
    return new class($said) implements SetupConsole
    {
        /** @param  array<int, string>  $said */
        public function __construct(private array &$said) {}

        public function ask(string $question, ?string $default = null): string
        {
            return (string) $default;
        }

        public function secret(string $question): string
        {
            return '';
        }

        public function confirm(string $question, bool $default = true): bool
        {
            return $default;
        }

        public function choose(string $question, array $options, ?string $default = null): string
        {
            return (string) $default;
        }

        public function select(string $question, array $options, array $default = []): array
        {
            return $default;
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
            return false;
        }
    };
}

function acRoutesFile(?string $contents): RoutesFile
{
    $path = sys_get_temp_dir().'/wire-code-routes-'.getmypid().'-'.uniqid().'.php';

    if ($contents !== null) {
        file_put_contents($path, $contents);
        register_shutdown_function(static fn () => @unlink($path));
    }

    return new RoutesFile($path);
}

it('routes nothing while every flow is off', function () {
    expect(Route::wire('auth-codes'))->toBe([]);
});

it('routes the flows that are on, keyed, inside the application s group', function () {
    config()->set('wire-module-auth.codes.login', true);
    $routes = [];

    Route::domain('auth.example.test')->group(function () use (&$routes): void {
        $routes = Route::wire('auth-codes');
        $routes['login-code']->middleware('can:sign-in-by-code');
    });

    expect(array_keys($routes))->toBe(['login-code', 'login-code.store', 'login-code.challenge', 'login-code.challenge.store', 'login-code.send'])
        ->and($routes['login-code']->getName())->toBe('wire-auth.login-code')
        ->and($routes['login-code']->getDomain())->toBe('auth.example.test')
        ->and($routes['login-code']->middleware())->toContain('can:sign-in-by-code');
});

it('refuses a group that would rename what the screens link to', function () {
    Route::name('app.')->group(fn () => Route::wire('auth-codes'));
})->throws(RouteRegistrationException::class, "Route::wire('auth-codes')");

it('is an installer step of this module', function () {
    expect(SetupRegistry::instance()->all())->toContain(RouteOneTimeCodes::class);

    $step = new RouteOneTimeCodes;

    expect($step->label())->toBe('One-time code routes')
        ->and($step->package())->toBe('nyoncode/wire-module-auth')
        ->and($step->sort())->toBe(220);
});

it('writes the call once, into the application s route file', function () {
    $file = acRoutesFile("<?php\n");
    $step = new RouteOneTimeCodes($file);

    expect($step->state())->toBe(SetupState::Pending)
        ->and($step->summary())->toContain("add Route::wire('auth-codes')")
        ->and($step->apply(acConsole()))->toBe(SetupOutcome::Applied)
        ->and($file->wires('auth-codes'))->toBeTrue()
        ->and($step->state())->toBe(SetupState::Done)
        ->and($step->summary())->toContain('already placed');
});

it('has nowhere to write without a route file, and says what to add instead', function () {
    $step = new RouteOneTimeCodes(acRoutesFile(null));
    $said = [];

    expect($step->state())->toBe(SetupState::Blocked)
        ->and($step->summary())->toBe('no routes/web.php to add them to')
        ->and($step->apply(acConsole($said)))->toBe(SetupOutcome::Failed)
        ->and(implode("\n", $said))->toContain("Route::wire('auth-codes');");
});

it('is done when config places the group instead', function () {
    config()->set('wire-core.routes.groups', ['auth-codes' => []]);

    expect((new RouteOneTimeCodes(acRoutesFile("<?php\n")))->state())->toBe(SetupState::Done);
});
