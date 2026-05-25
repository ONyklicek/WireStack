<?php

declare(strict_types=1);

use Illuminate\Auth\Access\Gate as GateImplementation;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Foundation\Auth\User as Authenticatable;
use NyonCode\WireTable\Columns\TextColumn;

function ensureGateForColumn(?object $user = null): GateImplementation
{
    if (! app()->bound(Gate::class)) {
        app()->singleton(Gate::class, fn ($app) => new GateImplementation($app, fn () => $user));
    }

    return app(Gate::class);
}

function actAsUserForColumn(Authenticatable $user): void
{
    auth()->guard('web')->setUser($user);
}

// ─── authorize ────────────────────────────────────────────────────────────

it('can view by default', function () {
    $column = TextColumn::make('name');

    expect($column->canView())->toBeTrue();
});

it('denies view when authorize set and gate denies', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForColumn($user);
    $gate->define('view-salary', fn () => false);

    actAsUserForColumn($user);

    $column = TextColumn::make('salary')->authorize('view-salary');

    expect($column->canView())->toBeFalse();
});

it('allows view when authorize set and gate allows', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForColumn($user);
    $gate->define('view-salary', fn () => true);

    actAsUserForColumn($user);

    $column = TextColumn::make('salary')->authorize('view-salary');

    expect($column->canView())->toBeTrue();
});

it('authorize(null) clears the gate ability', function () {
    $column = TextColumn::make('salary')->authorize('view-salary')->authorize(null);

    expect($column->canView())->toBeTrue();
});

// ─── authorizeUsing ───────────────────────────────────────────────────────

it('denies view when authorizeUsing callback returns false', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    actAsUserForColumn($user);

    $column = TextColumn::make('salary')->authorizeUsing(fn ($u) => false);

    expect($column->canView())->toBeFalse();
});

it('allows view when authorizeUsing callback returns true', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    actAsUserForColumn($user);

    $column = TextColumn::make('salary')->authorizeUsing(fn ($u) => true);

    expect($column->canView())->toBeTrue();
});

it('authorizeUsing(null) clears the callback', function () {
    $column = TextColumn::make('salary')
        ->authorizeUsing(fn ($user) => false)
        ->authorizeUsing(null);

    expect($column->canView())->toBeTrue();
});

// ─── authorizeInline ──────────────────────────────────────────────────────

it('can inline edit by default', function () {
    $column = TextColumn::make('name');

    expect($column->canInlineEdit())->toBeTrue();
});

it('denies inline edit when gate denies', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForColumn($user);
    $gate->define('edit-salary', fn () => false);

    actAsUserForColumn($user);

    $column = TextColumn::make('salary')->authorizeInline('edit-salary');

    expect($column->canInlineEdit())->toBeFalse();
});

it('allows inline edit when gate allows', function () {
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $gate = ensureGateForColumn($user);
    $gate->define('edit-salary', fn () => true);

    actAsUserForColumn($user);

    $column = TextColumn::make('salary')->authorizeInline('edit-salary');

    expect($column->canInlineEdit())->toBeTrue();
});

it('authorizeInline(null) clears the inline gate ability', function () {
    $column = TextColumn::make('salary')
        ->authorizeInline('edit-salary')
        ->authorizeInline(null);

    expect($column->canInlineEdit())->toBeTrue();
});

it('getInlineEditAbility returns the set ability', function () {
    $column = TextColumn::make('salary')->authorizeInline('edit-salary');

    expect($column->getInlineEditAbility())->toBe('edit-salary');
});
