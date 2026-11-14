<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\View\PageChrome;
use NyonCode\WireModuleUsers\Events\ImpersonationStarted;
use NyonCode\WireModuleUsers\Events\ImpersonationStopped;
use NyonCode\WireModuleUsers\Livewire\ImpersonationBanner;
use NyonCode\WireModuleUsers\Pages\ListUsers;
use NyonCode\WireModuleUsers\Support\Impersonation;
use NyonCode\WireModuleUsers\Tests\Fixtures\User;
use NyonCode\WireModuleUsers\Tests\Support\Tables;
use NyonCode\WireModuleUsers\WireModuleUsersServiceProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * Signing in as somebody else, and back: off by default, only for a holder of
 * the ability, never as yourself, never chained, and the way back is one step.
 */
beforeEach(function () {
    config()->set('wire-module-users.model', User::class);
    config()->set('auth.providers.users.model', User::class);
    config()->set('wire-module-users.impersonation.enabled', true);

    Tables::users();
    Tables::roles();

    Route::middleware('web')->group(fn () => Route::wireResources());

    $this->support = User::query()->create(['name' => 'Support', 'email' => 'support@example.com', 'password' => Hash::make('x')]);
    $this->customer = User::query()->create(['name' => 'Customer', 'email' => 'customer@example.com', 'password' => Hash::make('x')]);

    Gate::before(static fn (?User $user = null): ?bool => null);
    Gate::define('users.viewAny', fn (): bool => true);
    Gate::define('users.impersonate', fn (User $user): bool => $user->email === 'support@example.com');
});

it('signs in as the account and back, and says so each time', function () {
    Event::fake([ImpersonationStarted::class, ImpersonationStopped::class]);
    $this->be($this->support);

    Impersonation::start($this->customer);

    expect(auth()->id())->toBe($this->customer->getKey())
        ->and(Impersonation::active())->toBeTrue()
        ->and(Impersonation::impersonator()?->getKey())->toBe($this->support->getKey());

    Livewire::test(ImpersonationBanner::class)
        ->assertSee('Signed in as Customer')
        ->call('stop')
        ->assertRedirect('/');

    expect(auth()->id())->toBe($this->support->getKey())
        ->and(Impersonation::active())->toBeFalse();

    Event::assertDispatched(ImpersonationStarted::class, fn ($e) => $e->impersonator->getKey() === $this->support->getKey() && $e->impersonated->getKey() === $this->customer->getKey());
    Event::assertDispatched(ImpersonationStopped::class);
});

it('offers Sign in as only to a holder of the ability, and runs it from the list', function () {
    $this->be($this->customer);
    expect(Impersonation::mayImpersonate($this->support))->toBeFalse();

    $this->be($this->support);
    expect(Impersonation::mayImpersonate($this->customer))->toBeTrue()
        // Never as yourself.
        ->and(Impersonation::mayImpersonate($this->support))->toBeFalse();

    Livewire::test(ListUsers::class)
        ->assertSeeHtml('data-testid="action-impersonate"')
        ->call('executeTableAction', (string) $this->customer->getKey(), 'impersonate', true)
        ->assertRedirect('/');

    expect(auth()->id())->toBe($this->customer->getKey());
});

it('refuses however start() is reached: off, no ability, no permission named, chained', function () {
    $this->be($this->customer);
    expect(fn () => Impersonation::start($this->support))->toThrow(HttpException::class);
    expect(auth()->id())->toBe($this->customer->getKey());

    $this->be($this->support);
    config()->set('wire-module-users.impersonation.enabled', false);
    expect(Impersonation::mayImpersonate($this->customer))->toBeFalse();

    config()->set('wire-module-users.impersonation.enabled', true);
    config()->set('wire-module-users.impersonation.permission', null);
    expect(Impersonation::mayImpersonate($this->customer))->toBeFalse()
        ->and(Impersonation::permission())->toBeNull();

    config()->set('wire-module-users.impersonation.permission', 'users.impersonate');
    Impersonation::start($this->customer);

    // Acting as the customer: no second impersonation from here.
    $third = User::query()->create(['name' => 'Third', 'email' => 'third@example.com', 'password' => Hash::make('x')]);
    expect(Impersonation::mayImpersonate($third, $this->support))->toBeFalse();

    Gate::define('users.impersonate', fn () => throw new RuntimeException('no guard'));
    Impersonation::stop();
    expect(Impersonation::mayImpersonate($third))->toBeFalse();
});

it('does nothing on the way back outside an impersonation', function () {
    $this->be($this->customer);

    Impersonation::stop();

    expect(auth()->id())->toBe($this->customer->getKey())
        ->and(Impersonation::impersonator())->toBeNull();

    session()->put(Impersonation::SESSION_KEY, 'garbage');
    expect(Impersonation::impersonator())->toBeNull();
    Impersonation::stop();
    expect(Impersonation::active())->toBeFalse();
});

it('puts the way back in the top bar only where impersonation is on', function () {
    $this->be($this->support);
    Impersonation::start($this->customer);

    expect(view('wire-module-users::impersonation-banner')->render())->toContain('impersonation-banner');

    Impersonation::stop();
    expect(trim(view('wire-module-users::impersonation-banner')->render()))->toBe('');
});

it('puts the way back into the top bar only where impersonation is on', function () {
    $chrome = app(PageChrome::class);
    $boot = new ReflectionMethod(WireModuleUsersServiceProvider::class, 'bootImpersonation');
    $provider = new WireModuleUsersServiceProvider(app());

    $before = count($chrome->views(PageChrome::TOPBAR));
    config()->set('wire-module-users.impersonation.enabled', false);
    $boot->invoke($provider);
    expect(count($chrome->views(PageChrome::TOPBAR)))->toBe($before);

    config()->set('wire-module-users.impersonation.enabled', true);
    $boot->invoke($provider);
    expect($chrome->views(PageChrome::TOPBAR))->toContain('wire-module-users::impersonation-banner');
});
