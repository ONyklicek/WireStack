<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Integration\ActionMacros;

beforeEach(function () {
    ActionMacros::register();
});

// ─── ActionMacros::register() ───────────────────────────────────────────────

it('does not throw when registering macros', function () {
    ActionMacros::register();

    expect(true)->toBeTrue();
});

// ─── Core HasModal form integration ─────────────────────────────────────────

it('sets form on action via form() with component array', function () {
    $action = Action::make('create')->form([
        TextInput::make('name'),
        TextInput::make('email'),
    ]);

    expect($action->hasFormModal())->toBeTrue()
        ->and($action->hasFormInstance())->toBeTrue();
});

it('sets form via form() with Form instance', function () {
    $action = Action::make('create')->form(
        Form::make()->schema([
            TextInput::make('name')->required(),
            TextInput::make('email')->email(),
        ])
    );

    expect($action->hasFormModal())->toBeTrue()
        ->and($action->hasFormInstance())->toBeTrue();
});

it('sets form as closure for dynamic schemas', function () {
    $action = Action::make('edit')
        ->form(fn ($record) => [
            TextInput::make('name'),
        ]);

    expect($action->hasFormModal())->toBeTrue()
        ->and($action->hasFormInstance())->toBeTrue();
});

it('works on BulkAction', function () {
    $action = BulkAction::make('bulk-edit')
        ->form([
            TextInput::make('status'),
        ]);

    expect($action->hasFormModal())->toBeTrue()
        ->and($action->hasFormInstance())->toBeTrue();
});

// ─── formValidation ─────────────────────────────────────────────────────────

it('sets form validation rules', function () {
    $action = Action::make('test')
        ->formValidation(['name' => 'required', 'email' => 'required|email']);

    $validation = $action->getFormValidation();

    expect($validation)->toBeArray()
        ->and($validation)->toHaveKey('actionModalFormData.name')
        ->and($validation)->toHaveKey('actionModalFormData.email');
});

it('sets validation messages', function () {
    $action = Action::make('test')
        ->validationMessages(['name.required' => 'Jméno je povinné']);

    expect($action)->toBeInstanceOf(Action::class);
});

it('sets validation attributes', function () {
    $action = Action::make('test')
        ->validationAttributes(['name' => 'Jméno']);

    expect($action)->toBeInstanceOf(Action::class);
});

// ─── fillFormUsing ──────────────────────────────────────────────────────────

it('sets fill form callback', function () {
    $callback = fn ($record) => ['name' => $record->name];
    $action = Action::make('edit')
        ->fillFormUsing($callback);

    expect($action->getFillFormCallback())->toBe($callback);
});

it('returns null fill callback when not set', function () {
    $action = Action::make('test');

    expect($action->getFillFormCallback())->toBeNull();
});

// ─── No form state ──────────────────────────────────────────────────────────

it('reports no form modal when form is not set', function () {
    $action = Action::make('simple');

    expect($action->hasFormModal())->toBeFalse();
});

it('returns null form instance when not configured', function () {
    $action = Action::make('simple');

    expect($action->getFormInstance())->toBeNull();
});

// ─── Chaining ───────────────────────────────────────────────────────────────

it('chains form methods with native action methods', function () {
    $action = Action::make('create-user')
        ->form([
            TextInput::make('name'),
        ])
        ->formValidation(['name' => 'required'])
        ->label('Vytvořit uživatele')
        ->color('primary');

    expect($action->hasFormModal())->toBeTrue()
        ->and($action->getLabel())->toBe('Vytvořit uživatele')
        ->and($action->hasFormInstance())->toBeTrue();
});

// ─── Confirmation vs form modal ─────────────────────────────────────────────

it('distinguishes confirmation modal from form modal', function () {
    $confirmation = Action::make('delete')->requiresConfirmation();
    $formAction = Action::make('edit')->form([TextInput::make('name')]);

    expect($confirmation->doesRequireConfirmation())->toBeTrue()
        ->and($confirmation->hasFormModal())->toBeFalse()
        ->and($formAction->doesRequireConfirmation())->toBeFalse()
        ->and($formAction->hasFormModal())->toBeTrue();
});
