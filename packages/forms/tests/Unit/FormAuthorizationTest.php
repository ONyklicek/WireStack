<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use NyonCode\WireForms\Forms\Form;

class FormAuthorizationRecord extends Model
{
    protected $guarded = [];
}

function actAsFormAuthorizationUser(): void
{
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    auth()->guard('web')->setUser($user);
}

// ─── authorize ────────────────────────────────────────────────────────────

it('does not use policy by default', function () {
    $form = Form::make();

    expect($form->isReadOnly())->toBeFalse();
});

it('canSave returns true without policy', function () {
    $form = Form::make();

    expect($form->canSave())->toBeTrue();
});

it('canSave returns true with policy but no model', function () {
    $form = Form::make()->authorize();

    expect($form->canSave())->toBeTrue();
});

it('isReadOnly returns false without policy', function () {
    $form = Form::make();

    expect($form->isReadOnly())->toBeFalse();
});

it('uses create policy for model classes', function () {
    actAsFormAuthorizationUser();
    Gate::define('create', fn ($user, string $modelClass): bool => $modelClass === FormAuthorizationRecord::class);

    $form = Form::make()
        ->model(FormAuthorizationRecord::class)
        ->authorize();

    expect($form->canSave())->toBeTrue()
        ->and($form->isReadOnly())->toBeFalse();
});

it('becomes read only when create policy denies model classes', function () {
    actAsFormAuthorizationUser();
    Gate::define('create', fn (): bool => false);

    $form = Form::make()
        ->model(FormAuthorizationRecord::class)
        ->authorize();

    expect($form->canSave())->toBeFalse()
        ->and($form->isReadOnly())->toBeTrue();
});

it('uses update policy for existing model instances', function () {
    actAsFormAuthorizationUser();

    $record = new FormAuthorizationRecord;
    $record->exists = true;

    Gate::define('update', fn ($user, FormAuthorizationRecord $model): bool => $model === $record);

    $form = Form::make()
        ->model($record)
        ->authorize();

    expect($form->canSave())->toBeTrue()
        ->and($form->isReadOnly())->toBeFalse();
});

it('authorize false bypasses denied policies', function () {
    actAsFormAuthorizationUser();
    Gate::define('create', fn (): bool => false);

    $form = Form::make()
        ->model(FormAuthorizationRecord::class)
        ->authorize()
        ->authorize(false);

    expect($form->canSave())->toBeTrue()
        ->and($form->isReadOnly())->toBeFalse();
});

// ─── authorizeUsing ───────────────────────────────────────────────────────

it('authorizeUsing callback can deny save', function () {
    actAsFormAuthorizationUser();

    $form = Form::make()
        ->authorizeUsing(fn ($user) => false);

    expect($form->canSave())->toBeFalse()
        ->and($form->isReadOnly())->toBeTrue();
});

it('authorizeUsing callback can allow save', function () {
    actAsFormAuthorizationUser();

    $form = Form::make()
        ->authorizeUsing(fn ($user) => true);

    expect($form->canSave())->toBeTrue()
        ->and($form->isReadOnly())->toBeFalse();
});

it('authorizeUsing takes priority over policy', function () {
    actAsFormAuthorizationUser();
    Gate::define('create', fn (): bool => false);

    $form = Form::make()
        ->model(FormAuthorizationRecord::class)
        ->authorize()
        ->authorizeUsing(fn ($user) => true);

    expect($form->canSave())->toBeTrue()
        ->and($form->isReadOnly())->toBeFalse();
});

it('authorizeUsing(null) clears the callback', function () {
    actAsFormAuthorizationUser();

    $form = Form::make()
        ->authorizeUsing(fn ($user) => false)
        ->authorizeUsing(null);

    expect($form->canSave())->toBeTrue()
        ->and($form->isReadOnly())->toBeFalse();
});

// ─── save() authorization enforcement ────────────────────────

it('save throws AuthorizationException when authorizeUsing denies', function () {
    actAsFormAuthorizationUser();

    $form = Form::make()
        ->schema([])
        ->authorizeUsing(fn ($user) => false)
        ->using(fn (array $data) => $data);

    expect(fn () => $form->save())
        ->toThrow(AuthorizationException::class);
});

it('save throws AuthorizationException when policy denies', function () {
    actAsFormAuthorizationUser();
    Gate::define('create', fn (): bool => false);

    $form = Form::make()
        ->schema([])
        ->model(FormAuthorizationRecord::class)
        ->authorize()
        ->using(fn (array $data) => $data);

    expect(fn () => $form->save())
        ->toThrow(AuthorizationException::class);
});

it('save proceeds when canSave returns true', function () {
    actAsFormAuthorizationUser();

    $called = false;

    $form = Form::make()
        ->schema([])
        ->authorizeUsing(fn ($user) => true)
        ->using(function (array $data) use (&$called) {
            $called = true;

            return $data;
        });

    $form->save();

    expect($called)->toBeTrue();
});
