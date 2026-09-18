<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NyonCode\Wire\Install\Steps\SendSignInToPanel;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WireCore\Foundation\Setup\SetupState;

/*
 * Where Fortify sends somebody who has just signed in.
 *
 * `fortify:install` publishes `'home' => '/home'`, and a clean application
 * routes nothing there: the first sign-in after `wire:install` was a 404. These
 * tests write the two files the step reads — the published config and the
 * route group the installer appends — into the skeleton, and put both back.
 */

/** A console that records what it was told and answers nothing. */
function sspConsole(array &$said): SetupConsole
{
    return new class($said) implements SetupConsole
    {
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

/** The line `fortify:install` publishes, inside enough of the file to be one. */
function sspFortifyConfig(string $home = "'/home'"): void
{
    file_put_contents(config_path('fortify.php'), <<<PHP
        <?php

        return [
            'guard' => 'web',
            'home' => {$home},
            'prefix' => '',
        ];
        PHP);
}

/** What `RegisterResourceRoutes` appends to routes/web.php. */
function sspInstallerGroup(string $prefixCall = "->prefix('admin')"): void
{
    file_put_contents(base_path('routes/web.php'), <<<PHP
        <?php

        use Illuminate\Support\Facades\Route;


        // Added by php artisan wire:install. Every resource and dashboard that
        // declares pages is routed here — see Route::wireResource() to name one.
        Route::middleware(['web', 'auth']){$prefixCall}->group(function () {
            Route::wireResources();
        });
        PHP, FILE_APPEND);
}

beforeEach(function () {
    $this->configExisted = is_file(config_path('fortify.php'));
    $this->configBefore = $this->configExisted ? file_get_contents(config_path('fortify.php')) : null;
    $this->routesBefore = is_file(base_path('routes/web.php')) ? file_get_contents(base_path('routes/web.php')) : null;

    // Start from an empty routes file, so what the step finds is what the test put there.
    @mkdir(base_path('routes'), 0755, true);
    file_put_contents(base_path('routes/web.php'), '');

    $this->step = app(SendSignInToPanel::class);
});

afterEach(function () {
    $this->configExisted
        ? file_put_contents(config_path('fortify.php'), $this->configBefore)
        : @unlink(config_path('fortify.php'));

    $this->routesBefore === null
        ? @unlink(base_path('routes/web.php'))
        : file_put_contents(base_path('routes/web.php'), $this->routesBefore);
});

it('is one of the steps the installer runs, after Fortify and the routes', function () {
    expect(SetupRegistry::instance()->all())->toContain(SendSignInToPanel::class)
        ->and($this->step->sort())->toBeGreaterThan(200)
        ->and($this->step->package())->toBe('nyoncode/wire-suite')
        ->and($this->step->label())->toBe('Sign-in destination');
});

it('waits for Fortify, and says so', function () {
    @unlink(config_path('fortify.php'));
    sspInstallerGroup();

    expect($this->step->state())->toBe(SetupState::Blocked)
        ->and($this->step->summary())->toContain('Fortify');
});

it('waits for the admin to be routed, and says so', function () {
    sspFortifyConfig();

    expect($this->step->state())->toBe(SetupState::Blocked)
        ->and($this->step->summary())->toContain('routed');
});

it('points Fortify s home at the admin the installer just routed', function () {
    sspFortifyConfig();
    sspInstallerGroup();

    expect($this->step->state())->toBe(SetupState::Pending)
        ->and($this->step->summary())->toContain("instead of Fortify's /home");

    $said = [];
    expect($this->step->apply(sspConsole($said)))->toBe(SetupOutcome::Applied);

    // The file is still a config file that returns an array, with the new value.
    $written = include config_path('fortify.php');

    expect($written['home'])->toBe('/admin')
        ->and($written['guard'])->toBe('web')
        ->and(config('fortify.home'))->toBe('/admin')
        ->and(implode("\n", $said))->toContain('/admin')
        ->and($this->step->state())->toBe(SetupState::Done);
});

it('points it at the application root when the admin has no prefix', function () {
    sspFortifyConfig();
    sspInstallerGroup('');

    $said = [];
    $this->step->apply(sspConsole($said));

    expect((include config_path('fortify.php'))['home'])->toBe('/');
});

it('reads the panel s root from the router when it is already routed', function () {
    // An application that routed the panel its own way, or a second run.
    sspFortifyConfig();
    Route::middleware('web')->prefix('backoffice')->group(fn () => Route::wireResources());
    app('router')->getRoutes()->refreshNameLookups();

    $said = [];
    $this->step->apply(sspConsole($said));

    expect((include config_path('fortify.php'))['home'])->toBe('/backoffice');
});

it('takes the prefix from wire-panels.routes when the panel is routed from config', function () {
    sspFortifyConfig();
    config()->set('wire-panels.routes.enabled', true);
    config()->set('wire-panels.routes.prefix', 'staff/');

    $said = [];
    $this->step->apply(sspConsole($said));

    expect((include config_path('fortify.php'))['home'])->toBe('/staff');
});

it('leaves a home the application chose alone', function () {
    sspFortifyConfig("'/dashboard'");
    sspInstallerGroup();

    expect($this->step->state())->toBe(SetupState::Done)
        ->and($this->step->summary())->toContain('choosing');

    $said = [];
    expect($this->step->apply(sspConsole($said)))->toBe(SetupOutcome::Skipped)
        ->and((include config_path('fortify.php'))['home'])->toBe('/dashboard');
});

it('recognises the default however it was quoted', function () {
    sspFortifyConfig('"/home"');
    sspInstallerGroup();

    expect($this->step->state())->toBe(SetupState::Pending);

    $said = [];
    $this->step->apply(sspConsole($said));

    expect((include config_path('fortify.php'))['home'])->toBe('/admin');
});

it('reports a config it cannot write instead of throwing', function () {
    sspFortifyConfig();
    sspInstallerGroup();
    chmod(config_path('fortify.php'), 0444);

    try {
        $said = [];
        expect($this->step->apply(sspConsole($said)))->toBe(SetupOutcome::Failed)
            ->and(implode("\n", $said))->toContain("'home' => '/admin'");
    } finally {
        chmod(config_path('fortify.php'), 0644);
    }
});

it('skips rather than guessing when it is applied with nothing to go on', function () {
    sspFortifyConfig();

    $said = [];
    expect($this->step->apply(sspConsole($said)))->toBe(SetupOutcome::Skipped);
});
