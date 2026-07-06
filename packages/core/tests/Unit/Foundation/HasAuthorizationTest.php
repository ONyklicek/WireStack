<?php

declare(strict_types=1);

use Illuminate\Auth\Access\Gate as GateImplementation;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Foundation\Auth\User as Authenticatable;
use NyonCode\WireCore\Foundation\Concerns\HasAuthorization;

function actAsAuthUser(Authenticatable $user): void
{
    app('config')->set('auth.defaults.guard', 'web');
    app('config')->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
    app('config')->set('auth.providers.users', ['driver' => 'eloquent', 'model' => Authenticatable::class]);

    auth()->guard('web')->setUser($user);
}

function ensureGateForAuth(?object $user = null): GateImplementation
{
    if (! app()->bound(Gate::class)) {
        app()->singleton(Gate::class, fn ($app) => new GateImplementation($app, fn () => $user));
    }

    return app(Gate::class);
}

function makeAuthorizableObject(): object
{
    return new class
    {
        use HasAuthorization;
    };
}

// ─── Defaults ──────────────────────────────────────────────────────────────

it('is authorized by default when nothing configured', function () {
    $obj = makeAuthorizableObject();

    expect($obj->isAuthorized())->toBeTrue();
});

it('has no permission by default', function () {
    expect(makeAuthorizableObject()->getPermission())->toBeNull();
});

// ─── permission() ──────────────────────────────────────────────────────────

it('can set permission string', function () {
    $obj = makeAuthorizableObject()->permission('manage-users');

    expect($obj->getPermission())->toBe('manage-users');
});

it('denies when permission set but no user authenticated', function () {
    $obj = makeAuthorizableObject()->permission('manage-users');

    expect($obj->isAuthorized())->toBeFalse();
});

it('checks permission via Gate::allows()', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForAuth($user);
    $gate->define('manage-users', fn () => true);

    actAsAuthUser($user);

    $obj = makeAuthorizableObject()->permission('manage-users');

    expect($obj->isAuthorized())->toBeTrue();
});

it('denies permission when Gate denies', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForAuth($user);
    $gate->define('manage-users', fn () => false);

    actAsAuthUser($user);

    $obj = makeAuthorizableObject()->permission('manage-users');

    expect($obj->isAuthorized())->toBeFalse();
});

it('permission(null) clears the permission', function () {
    $obj = makeAuthorizableObject()->permission('manage-users')->permission(null);

    expect($obj->isAuthorized())->toBeTrue();
});

// ─── authorize() ───────────────────────────────────────────────────────────

it('can set gate ability via authorize()', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForAuth($user);
    $gate->define('viewSalary', fn () => false);

    actAsAuthUser($user);

    $obj = makeAuthorizableObject()->authorize('viewSalary');

    expect($obj->isAuthorized())->toBeFalse();
});

it('authorize allows when gate returns true', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForAuth($user);
    $gate->define('viewSalary', fn () => true);

    actAsAuthUser($user);

    $obj = makeAuthorizableObject()->authorize('viewSalary');

    expect($obj->isAuthorized())->toBeTrue();
});

it('authorize(null) clears the gate ability', function () {
    $obj = makeAuthorizableObject()->authorize('viewSalary')->authorize(null);

    expect($obj->isAuthorized())->toBeTrue();
});

it('authorize passes context to gate', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForAuth($user);
    $gate->define('view', fn ($user, $record) => $record === 'allowed');

    actAsAuthUser($user);

    $obj = makeAuthorizableObject()->authorize('view');

    expect($obj->isAuthorized('allowed'))->toBeTrue()
        ->and($obj->isAuthorized('denied'))->toBeFalse();
});

// ─── authorizeUsing() ──────────────────────────────────────────────────────

it('can set custom authorization callback that denies', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    actAsAuthUser($user);

    $obj = makeAuthorizableObject()->authorizeUsing(fn ($u) => false);

    expect($obj->isAuthorized())->toBeFalse();
});

it('can set custom authorization callback that allows', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    actAsAuthUser($user);

    $obj = makeAuthorizableObject()->authorizeUsing(fn ($u) => true);

    expect($obj->isAuthorized())->toBeTrue();
});

it('authorizeUsing(null) clears the callback', function () {
    $obj = makeAuthorizableObject()
        ->authorizeUsing(fn ($user) => false)
        ->authorizeUsing(null);

    expect($obj->isAuthorized())->toBeTrue();
});

it('forwards the context record to the callback for per-record authorization', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };
    $user->id = 7;

    actAsAuthUser($user);

    $obj = makeAuthorizableObject()
        ->authorizeUsing(fn ($u, $record) => $record !== null && $record->owner_id === $u->id);

    $ownRecord = (object) ['owner_id' => 7];
    $othersRecord = (object) ['owner_id' => 99];

    expect($obj->isAuthorized($ownRecord))->toBeTrue()
        ->and($obj->isAuthorized($othersRecord))->toBeFalse()
        ->and($obj->isAuthorized())->toBeFalse(); // no record → denied by this callback
});

it('still supports a single-argument callback (backward compatible)', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    actAsAuthUser($user);

    // Legacy one-arg closure must keep working even though a context is passed.
    $obj = makeAuthorizableObject()->authorizeUsing(fn ($u) => true);

    expect($obj->isAuthorized((object) ['anything' => 1]))->toBeTrue();
});

// ─── Priority ──────────────────────────────────────────────────────────────

it('custom callback takes priority over gate ability', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForAuth($user);
    $gate->define('viewSalary', fn () => true);

    actAsAuthUser($user);

    // Callback denies, gate allows — callback wins
    $obj = makeAuthorizableObject()
        ->authorize('viewSalary')
        ->authorizeUsing(fn ($u) => false);

    expect($obj->isAuthorized())->toBeFalse();
});

it('denies when any auth is set but no user authenticated', function () {
    $obj = makeAuthorizableObject()->authorize('viewSalary');

    expect($obj->isAuthorized())->toBeFalse();
});
