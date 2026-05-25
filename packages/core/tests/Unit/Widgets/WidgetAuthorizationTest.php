<?php

declare(strict_types=1);

use Illuminate\Auth\Access\Gate as GateImplementation;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Foundation\Auth\User as Authenticatable;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;

function ensureGateForWidget(?object $user = null): GateImplementation
{
    if (! app()->bound(Gate::class)) {
        app()->singleton(Gate::class, fn ($app) => new GateImplementation($app, fn () => $user));
    }

    return app(Gate::class);
}

function actAsUserForWidget(Authenticatable $user): void
{
    auth()->guard('web')->setUser($user);
}

// ─── Defaults ──────────────────────────────────────────────────────────────

it('is visible by default', function () {
    $widget = StatsOverviewWidget::make();

    expect($widget->isVisible())->toBeTrue();
});

// ─── permission ───────────────────────────────────────────────────────────

it('hides widget when permission set and Gate denies', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForWidget($user);
    $gate->define('view-dashboard-stats', fn () => false);

    actAsUserForWidget($user);

    $widget = StatsOverviewWidget::make()->permission('view-dashboard-stats');

    expect($widget->isVisible())->toBeFalse();
});

it('shows widget when permission set and Gate allows', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForWidget($user);
    $gate->define('view-dashboard-stats', fn () => true);

    actAsUserForWidget($user);

    $widget = StatsOverviewWidget::make()->permission('view-dashboard-stats');

    expect($widget->isVisible())->toBeTrue();
});

// ─── authorize ────────────────────────────────────────────────────────────

it('hides widget when authorize set and gate denies', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForWidget($user);
    $gate->define('view-revenue', fn () => false);

    actAsUserForWidget($user);

    $widget = StatsOverviewWidget::make()->authorize('view-revenue');

    expect($widget->isVisible())->toBeFalse();
});

it('shows widget when authorize set and gate allows', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForWidget($user);
    $gate->define('view-revenue', fn () => true);

    actAsUserForWidget($user);

    $widget = StatsOverviewWidget::make()->authorize('view-revenue');

    expect($widget->isVisible())->toBeTrue();
});

// ─── authorizeUsing ───────────────────────────────────────────────────────

it('hides widget when authorizeUsing callback returns false', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    actAsUserForWidget($user);

    $widget = StatsOverviewWidget::make()->authorizeUsing(fn ($u) => false);

    expect($widget->isVisible())->toBeFalse();
});

it('shows widget when authorizeUsing callback returns true', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    actAsUserForWidget($user);

    $widget = StatsOverviewWidget::make()->authorizeUsing(fn ($u) => true);

    expect($widget->isVisible())->toBeTrue();
});
