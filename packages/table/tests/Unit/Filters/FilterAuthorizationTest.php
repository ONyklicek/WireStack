<?php

declare(strict_types=1);

use Illuminate\Auth\Access\Gate as GateImplementation;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Foundation\Auth\User as Authenticatable;
use NyonCode\WireTable\Filters\Filter;

function ensureGateForFilter(?object $user = null): GateImplementation
{
    if (! app()->bound(Gate::class)) {
        app()->singleton(Gate::class, fn ($app) => new GateImplementation($app, fn () => $user));
    }

    return app(Gate::class);
}

function actAsUserForFilter(Authenticatable $user): void
{
    auth()->guard('web')->setUser($user);
}

// ─── canView defaults ─────────────────────────────────────────────────────

it('can view by default', function () {
    $filter = Filter::make('status');

    expect($filter->canView())->toBeTrue();
});

it('cannot view when hidden', function () {
    $filter = Filter::make('status')->hidden();

    expect($filter->canView())->toBeFalse();
});

// ─── permission ───────────────────────────────────────────────────────────

it('denies view when permission set and Gate denies', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForFilter($user);
    $gate->define('view-advanced-filters', fn () => false);

    actAsUserForFilter($user);

    $filter = Filter::make('status')->permission('view-advanced-filters');

    expect($filter->canView())->toBeFalse();
});

it('allows view when permission set and Gate allows', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForFilter($user);
    $gate->define('view-advanced-filters', fn () => true);

    actAsUserForFilter($user);

    $filter = Filter::make('status')->permission('view-advanced-filters');

    expect($filter->canView())->toBeTrue();
});

// ─── authorize ────────────────────────────────────────────────────────────

it('denies view when authorize set and gate denies', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForFilter($user);
    $gate->define('manage-filters', fn () => false);

    actAsUserForFilter($user);

    $filter = Filter::make('status')->authorize('manage-filters');

    expect($filter->canView())->toBeFalse();
});

it('allows view when authorize set and gate allows', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForFilter($user);
    $gate->define('manage-filters', fn () => true);

    actAsUserForFilter($user);

    $filter = Filter::make('status')->authorize('manage-filters');

    expect($filter->canView())->toBeTrue();
});

// ─── authorizeUsing ───────────────────────────────────────────────────────

it('denies view when authorizeUsing callback returns false', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    actAsUserForFilter($user);

    $filter = Filter::make('status')->authorizeUsing(fn ($u) => false);

    expect($filter->canView())->toBeFalse();
});

it('allows view when authorizeUsing callback returns true', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    actAsUserForFilter($user);

    $filter = Filter::make('status')->authorizeUsing(fn ($u) => true);

    expect($filter->canView())->toBeTrue();
});

// ─── hidden takes priority ────────────────────────────────────────────────

it('hidden takes priority over authorization', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    actAsUserForFilter($user);

    $filter = Filter::make('status')
        ->hidden()
        ->authorizeUsing(fn ($u) => true);

    expect($filter->canView())->toBeFalse();
});
