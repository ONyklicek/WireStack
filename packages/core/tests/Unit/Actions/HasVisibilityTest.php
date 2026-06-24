<?php

declare(strict_types=1);

use Illuminate\Auth\Access\Gate as GateImplementation;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Foundation\Auth\User as Authenticatable;
use NyonCode\WireCore\Actions\Action;

function actAsUser(Authenticatable $user): void
{
    app('config')->set('auth.defaults.guard', 'web');
    app('config')->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
    app('config')->set('auth.providers.users', ['driver' => 'eloquent', 'model' => Authenticatable::class]);

    auth()->guard('web')->setUser($user);
}

function ensureGateForVisibility(?object $user = null): GateImplementation
{
    if (! app()->bound(Gate::class)) {
        app()->singleton(Gate::class, fn ($app) => new GateImplementation($app, fn () => $user));
    }

    return app(Gate::class);
}

// ─── Hidden ────────────────────────────────────────────────────────────────

it('is visible by default', function () {
    $action = Action::make('test');

    expect($action->isHidden())->toBeFalse();
});

it('can be hidden', function () {
    $action = Action::make('test')->hidden();

    expect($action->isHidden())->toBeTrue();
});

it('can be explicitly set visible', function () {
    $action = Action::make('test')->hidden()->visible();

    expect($action->isHidden())->toBeFalse();
});

it('supports dynamic hidden via closure', function () {
    $action = Action::make('test')
        ->hidden(fn ($record) => $record->is_locked);

    $locked = (object) ['is_locked' => true];
    $unlocked = (object) ['is_locked' => false];

    expect($action->isHidden($locked))->toBeTrue()
        ->and($action->isHidden($unlocked))->toBeFalse();
});

it('supports dynamic visible via closure (regression: closure was discarded)', function () {
    $action = Action::make('test')
        ->visible(fn ($record) => $record->can_edit);

    $editable = (object) ['can_edit' => true];
    $locked = (object) ['can_edit' => false];

    // visible(closure) must invert the closure, not coerce it to a truthy bool.
    expect($action->isHidden($editable))->toBeFalse()
        ->and($action->isHidden($locked))->toBeTrue();
});

it('hides the action when a visible closure returns false without context', function () {
    $action = Action::make('test')->visible(fn () => false);

    expect($action->isHidden())->toBeTrue()
        ->and($action->canExecute())->toBeFalse();
});

it('shows the action when a visible closure returns true without context', function () {
    $action = Action::make('test')->visible(fn () => true);

    expect($action->isHidden())->toBeFalse();
});

// ─── Disabled ──────────────────────────────────────────────────────────────

it('is not disabled by default', function () {
    expect(Action::make('test')->isDisabled())->toBeFalse();
});

it('can be disabled', function () {
    expect(Action::make('test')->disabled()->isDisabled())->toBeTrue();
});

it('supports dynamic disabled via closure', function () {
    $action = Action::make('test')
        ->disabled(fn ($record) => $record->status === 'archived');

    $archived = (object) ['status' => 'archived'];
    $active = (object) ['status' => 'active'];

    expect($action->isDisabled($archived))->toBeTrue()
        ->and($action->isDisabled($active))->toBeFalse();
});

it('calls disabled closure even without context', function () {
    $action = Action::make('test')->disabled(fn () => true);

    expect($action->isDisabled())->toBeTrue();
});

it('disabled closure without context returns false when callback returns false', function () {
    $action = Action::make('test')->disabled(fn () => false);

    expect($action->isDisabled())->toBeFalse();
});

// ─── Permission ────────────────────────────────────────────────────────────

it('has no permission by default', function () {
    expect(Action::make('test')->getPermission())->toBeNull();
});

it('can set permission', function () {
    $action = Action::make('delete')->permission('delete-users');

    expect($action->getPermission())->toBe('delete-users');
});

// ─── canExecute ────────────────────────────────────────────────────────────

it('can execute when visible and not disabled', function () {
    $action = Action::make('test');

    expect($action->canExecute())->toBeTrue();
});

it('cannot execute when hidden', function () {
    $action = Action::make('test')->hidden();

    expect($action->canExecute())->toBeFalse();
});

it('can still execute when disabled (disabled is visual only)', function () {
    $action = Action::make('test')->disabled();

    expect($action->canExecute())->toBeTrue();
});

// ─── authorize ────────────────────────────────────────────────────────────

it('can set gate ability via authorize()', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForVisibility($user);
    $gate->define('viewSalary', fn () => false);

    actAsUser($user);

    $action = Action::make('test')->authorize('viewSalary');

    expect($action->canExecute())->toBeFalse();
});

it('authorize allows when gate returns true', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForVisibility($user);
    $gate->define('viewSalary', fn () => true);

    actAsUser($user);

    $action = Action::make('test')->authorize('viewSalary');

    expect($action->canExecute())->toBeTrue();
});

it('authorize(null) clears the gate ability', function () {
    $action = Action::make('test')->authorize('viewSalary')->authorize(null);

    expect($action->canExecute())->toBeTrue();
});

// ─── authorizeUsing ───────────────────────────────────────────────────────

it('can set custom authorization callback that denies', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    actAsUser($user);

    $action = Action::make('test')->authorizeUsing(fn ($u) => false);

    expect($action->canExecute())->toBeFalse();
});

it('can set custom authorization callback that allows', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    actAsUser($user);

    $action = Action::make('test')->authorizeUsing(fn ($u) => true);

    expect($action->canExecute())->toBeTrue();
});

it('authorizeUsing(null) clears the callback', function () {
    $action = Action::make('test')
        ->authorizeUsing(fn ($user) => false)
        ->authorizeUsing(null);

    expect($action->canExecute())->toBeTrue();
});

// ─── canExecute priority ──────────────────────────────────────────────────

it('hidden takes priority over authorize', function () {
    $action = Action::make('test')->hidden()->authorize('something');

    expect($action->canExecute())->toBeFalse();
});

it('returns true when no authorization is set', function () {
    $action = Action::make('test');

    expect($action->canExecute())->toBeTrue();
});

it('denies when authorize set but no user authenticated', function () {
    $action = Action::make('test')->authorize('viewSalary');

    expect($action->canExecute())->toBeFalse();
});

it('denies when authorizeUsing set but no user authenticated', function () {
    $action = Action::make('test')->authorizeUsing(fn ($u) => true);

    expect($action->canExecute())->toBeFalse();
});
