<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use NyonCode\WireForms\Components\Checkbox;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Exceptions\FormConfigurationException;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleAuth\Forms\AuthForm;
use NyonCode\WireModuleAuth\Forms\AuthForms;
use NyonCode\WireModuleAuth\Support\Frame;
use NyonCode\WireModuleAuth\Tests\Fixtures\AuthFrame;

/*
 * The fields of the signed-out screens, and the one line that changes them.
 *
 * The screens themselves are covered by ScreensTest, through Fortify's routes.
 * This is about what they ask for and what an application can do to it without
 * publishing a single view.
 */

beforeEach(function () {
    View::addNamespace('wire-admin', __DIR__.'/../Fixtures/views');
    Blade::component(Frame::SHELL_LAYOUT, AuthFrame::class);
});

function authForms(): AuthForms
{
    return app(AuthForms::class);
}

/** @return array<int, string> */
function fieldNames(Form $form): array
{
    return array_map(
        static fn (object $field): string => $field->getName(),
        $form->getSchema(),
    );
}

// ─── What each screen asks for ─────────────────────────────────────

it('asks for what the Fortify action behind it reads', function (string $method, array $expected) {
    expect(fieldNames(authForms()->{$method}()))->toBe($expected);
})->with([
    'login' => ['login', ['email', 'password', 'remember']],
    'register' => ['register', ['name', 'email', 'password', 'password_confirmation']],
    'forgot password' => ['forgotPassword', ['email']],
    'reset password' => ['resetPassword', ['token', 'email', 'password', 'password_confirmation']],
    'confirm password' => ['confirmPassword', ['password']],
    'two-factor code' => ['twoFactorCode', ['code']],
    'two-factor recovery' => ['twoFactorRecovery', ['recovery_code']],
    'login code' => ['loginCode', ['email']],
    'code' => ['code', ['code']],
    'reset password code' => ['resetPasswordCode', ['email', 'code', 'password', 'password_confirmation']],
]);

it('renders every one of them for the browser, never for Livewire', function (string $method) {
    // The whole arrangement rests on this: the browser does the posting, so a
    // field bound by `wire:model` would carry no name and post nothing.
    expect(authForms()->{$method}()->submitsNatively())->toBeTrue();
})->with([
    'login', 'register', 'forgotPassword', 'resetPassword', 'confirmPassword',
    'twoFactorCode', 'twoFactorRecovery',
    // The code flows were missing here, which mattered more than it looked: the
    // reset-password-code screen had no assertion anywhere that its fields carry
    // names, so losing native mode there would have posted nothing, quietly.
    'loginCode', 'code', 'resetPasswordCode',
]);

it('names the identity field whatever Fortify was told to read', function () {
    // `fortify.username` is a column and the request key that carries it, so an
    // application signing people in by their staff number gets an input named
    // after it rather than one named `email` that the action never reads.
    config()->set('fortify.username', 'username');

    expect(fieldNames(authForms()->login()))->toBe(['username', 'password', 'remember']);
});

it('does not make a staff number look like an address', function () {
    // `type=email` is a format rule the browser enforces before the form is
    // posted: on a field that is not an address it rejects every value the
    // application configured it for, and says so in the browser's own words.
    config()->set('fortify.username', 'username');

    expect(authForms()->login()->getSchema()[0]->getInputType())->toBe('text')
        ->and(authForms()->login()->getSchema()[0]->getAutocomplete())->toBe('username');
});

it('keeps the address field an address where it is one', function () {
    expect(authForms()->login()->getSchema()[0]->getInputType())->toBe('email');
});

it('carries the token and the address the reset link was issued for', function () {
    // Both come off the link Fortify mailed, and both go back with the form:
    // the broker matches the pair, so a screen that lost either is a screen
    // that cannot finish.
    $schema = authForms()->resetPassword('the-token', 'someone@example.com')->getSchema();

    expect($schema[0]->getDefault())->toBe('the-token')
        ->and($schema[1]->getDefault())->toBe('someone@example.com');
});

it('renders the token as an input, which is the only place a native form can keep it', function () {
    // A `Hidden` renders nothing in a Livewire form — the value lives in state.
    // There is no state here, so the input is the value.
    $this->get('/reset-password/the-token?email=someone@example.com')
        ->assertOk()
        ->assertSee('type="hidden"', false)
        ->assertSee('name="token" value="the-token"', false);
});

it('leaves both two-factor inputs optional', function () {
    // One form, two halves, one of them hidden — and a browser asked to validate
    // a required control it cannot focus refuses the submit and says so nowhere.
    expect(authForms()->twoFactorCode()->getSchema()[0]->isRequired())->toBeFalse()
        ->and(authForms()->twoFactorRecovery()->getSchema()[0]->isRequired())->toBeFalse();
});

// ─── Changing one, without owning a view ───────────────────────────

it('lets an application add a field to a screen it does not own', function () {
    authForms()->extend(
        AuthForm::Login,
        fn (array $fields): array => [...$fields, TextInput::make('tenant')->label('Tenant')],
    );

    $this->get('/login')
        ->assertOk()
        ->assertSee('name="tenant"', false)
        // Still the fields it shipped with: this appends, it does not replace.
        ->assertSee('name="email"', false);
});

it('lets one replace the fields outright', function () {
    authForms()->extend(
        AuthForm::ForgotPassword,
        fn (): array => [TextInput::make('staff_number')->required()],
    );

    $this->get('/forgot-password')
        ->assertOk()
        ->assertSee('name="staff_number"', false)
        ->assertDontSee('name="email"', false);
});

it('runs the callbacks in the order they were registered, each on the last answer', function () {
    authForms()->extend(AuthForm::Register, fn (array $fields): array => [...$fields, Checkbox::make('terms')]);
    authForms()->extend(AuthForm::Register, fn (array $fields): array => array_slice($fields, 1));

    expect(fieldNames(authForms()->register()))
        ->toBe(['email', 'password', 'password_confirmation', 'terms']);
});

it('tells a callback which screen it is being asked about', function () {
    $seen = null;

    authForms()->extend(AuthForm::ConfirmPassword, function (array $fields, AuthForm $screen) use (&$seen): array {
        $seen = $screen;

        return $fields;
    });

    authForms()->confirmPassword();

    expect($seen)->toBe(AuthForm::ConfirmPassword);
});

it('leaves the other screens alone', function () {
    authForms()->extend(AuthForm::Login, fn (array $fields): array => [...$fields, TextInput::make('tenant')]);

    expect(fieldNames(authForms()->register()))->not->toContain('tenant');
});

it('refuses a field the browser could not post, rather than rendering one that posts nothing', function () {
    // The guard is the feature (ADR 0036 §2). A `Select` searching on the server
    // binds through Livewire alone, so on this side of the door it would render
    // an input with no name — a form that submits an empty value, silently.
    authForms()->extend(
        AuthForm::Login,
        fn (array $fields): array => [...$fields, Select::make('country')->options(['cz' => 'Czechia'])],
    );

    expect(fn () => authForms()->login()->toHtml())
        ->toThrow(FormConfigurationException::class, 'Field [country]');
});

it('hands every provider the same registry', function () {
    // A second instance would collect callbacks nothing renders — registered in
    // a provider's boot, read on a request, and silently missing in between.
    expect(app(AuthForms::class))->toBe(app(AuthForms::class));
});
