<?php

declare(strict_types=1);

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use NyonCode\WireCore\Exceptions\RouteRegistrationException;
use NyonCode\WireCore\Foundation\Routing\WireRoutes;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\RoutesFile;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WireModuleTenants\Install\RouteTenantScreens;
use NyonCode\WireModuleTenants\Routing\TenantRoutes;
use NyonCode\WireModuleTenants\Support\Registration;

/*
 * The two screens outside any company are the `tenants` route group, placed by
 * the application — `Route::wire('tenants')` in routes/web.php or an entry of
 * `wire-core.routes.groups` — never by the module's provider (ADR 0041). The
 * group around them is the application's: prefix, domain, middleware, `can:`.
 * The route names are not, because an e-mail links to them.
 */

/** @param  array<int, string>  $said */
function rtConsole(array &$said = []): SetupConsole
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

function rtRoutesFile(?string $contents): RoutesFile
{
    $path = sys_get_temp_dir().'/wire-tenant-routes-'.getmypid().'-'.uniqid().'.php';

    if ($contents !== null) {
        file_put_contents($path, $contents);
        register_shutdown_function(static fn () => @unlink($path));
    }

    return new RoutesFile($path);
}

it('registers both screens inside the application s group, and hands them back by key', function () {
    // Placed once more, somewhere else — so without the TestCase's own placing.
    Route::setRoutes(new RouteCollection);
    $routes = [];

    Route::middleware(['web', 'auth', 'verified'])->prefix('companies')->group(function () use (&$routes): void {
        $routes = Route::wire('tenants');
        // One screen's own permission, on top of the group.
        $routes['register']->middleware('can:tenants.create');
    });

    expect(array_keys($routes))->toBe(['register', 'accept'])
        ->and($routes['register']->uri())->toBe('companies/register')
        ->and($routes['register']->getName())->toBe(TenantRoutes::REGISTER)
        ->and($routes['register']->middleware())->toBe(['web', 'auth', 'verified', 'can:tenants.create'])
        ->and($routes['accept']->uri())->toBe('companies/invitations/{invitation}')
        ->and($routes['accept']->getName())->toBe(TenantRoutes::ACCEPT)
        ->and($routes['accept']->middleware())->toBe(['web', 'auth', 'verified', 'signed']);
});

it('refuses a group that would rename what the invitation e-mail links to', function () {
    Route::name('app.')->group(fn () => Route::wire('tenants'));
})->throws(RouteRegistrationException::class, "Route::wire('tenants')");

it('places the same group from config, with a per-route permission', function () {
    Route::setRoutes(new RouteCollection);
    config()->set('wire-core.routes.groups', [
        'tenants' => ['prefix' => 'firmy', 'routes' => ['register' => ['can' => 'tenants.create']]],
    ]);

    app(WireRoutes::class)->registerConfigured();
    Route::getRoutes()->refreshNameLookups();

    $register = Route::getRoutes()->getByName(TenantRoutes::REGISTER);

    // The group's own defaults — `web` and `auth` — under what the entry said.
    expect($register->uri())->toBe('firmy/register')
        ->and($register->middleware())->toBe(['web', 'auth', 'can:tenants.create']);
});

it('routes its pages only in a zone with a company in it', function () {
    Route::setRoutes(new RouteCollection);

    Route::prefix('admin')->group(fn () => Route::wire('panel'));
    Route::prefix('app/{tenant}')->group(fn () => Route::wire('panel', zone: 'app'));
    Route::getRoutes()->refreshNameLookups();

    expect(Route::has('wire.company.index'))->toBeFalse()
        ->and(Route::has('wire.members.index'))->toBeFalse()
        ->and(Route::has('app.wire.company.index'))->toBeTrue()
        ->and(Route::has('app.wire.members.index'))->toBeTrue();
});

it('offers no registration link where the application routes no registration', function () {
    Route::setRoutes(new RouteCollection);
    Route::getRoutes()->refreshNameLookups();
    $this->actingAs($this->person('Ada'));

    expect(Registration::urlFor(auth()->user()))->toBeNull();
});

it('is an installer step of this module', function () {
    expect(SetupRegistry::instance()->all())->toContain(RouteTenantScreens::class);

    $step = new RouteTenantScreens;

    expect($step->label())->toBe('Company routes')
        ->and($step->package())->toBe('nyoncode/wire-module-tenants')
        ->and($step->sort())->toBe(210);
});

it('writes the call once, into the application s route file', function () {
    $file = rtRoutesFile("<?php\n");
    $step = new RouteTenantScreens($file);

    expect($step->state())->toBe(SetupState::Pending)
        ->and($step->summary())->toContain("add Route::wire('tenants')")
        ->and($step->apply(rtConsole()))->toBe(SetupOutcome::Applied)
        ->and($file->wires('tenants'))->toBeTrue()
        ->and($step->state())->toBe(SetupState::Done)
        ->and($step->summary())->toContain('already placed');
});

it('has nowhere to write without a route file, and says what to add instead', function () {
    $step = new RouteTenantScreens(rtRoutesFile(null));
    $said = [];

    expect($step->state())->toBe(SetupState::Blocked)
        ->and($step->summary())->toBe('no routes/web.php to add them to')
        ->and($step->apply(rtConsole($said)))->toBe(SetupOutcome::Failed)
        ->and(implode("\n", $said))->toContain("Route::wire('tenants');");
});

it('offers nothing to an application without tenancy', function () {
    config()->set('wire-core.tenancy.enabled', false);
    $step = new RouteTenantScreens(rtRoutesFile("<?php\n"));

    expect($step->state())->toBe(SetupState::Blocked)
        ->and($step->summary())->toContain('tenancy is off');
});

it('is done when config places the group instead', function () {
    config()->set('wire-core.routes.groups', ['tenants' => []]);

    expect((new RouteTenantScreens(rtRoutesFile("<?php\n")))->state())->toBe(SetupState::Done);
});
