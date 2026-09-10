<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Forms;

use Closure;
use NyonCode\WireForms\Components\Checkbox;
use NyonCode\WireForms\Components\Hidden;
use NyonCode\WireForms\Components\OtpInput;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;

/**
 * The fields of every signed-out screen, declared in PHP.
 *
 * **This is what changing a sign-in form costs.** Before it, adding a field to
 * one of these screens meant `vendor:publish`, a copy of the markup, and a file
 * that stopped tracking the package the moment it landed — a fix released
 * upstream reaching the package's view and not yours, with nothing on the page
 * to say so. Now it is a closure in a provider:
 *
 *     app(AuthForms::class)->extend(
 *         AuthForm::Register,
 *         fn (array $fields): array => [...$fields, TextInput::make('company')->required()],
 *     );
 *
 * The fields are `wire-forms`' own, rendered in native-submit mode (ADR 0036):
 * the same schema, chrome and Alpine as the panel's forms behind the door, and
 * the browser still does the posting. Which is also the one rule an extension
 * has to keep — a field that cannot submit natively is refused at render with
 * `FormConfigurationException`, by name, rather than rendering an input that
 * posts nothing.
 *
 * **The `<form>` element is nobody's business here.** Its action, its `@csrf`
 * and its submit button stay in the view, because where a screen posts is
 * Fortify's answer and not this class's. So is what the request does with the
 * fields: a field added here is a field Fortify's action has to be told about —
 * `Fortify::createUsersUsing()` and its siblings are where a new column is read,
 * and this class only puts the input on the page.
 *
 * A container singleton rather than static state, the way `PageChrome` is one:
 * callbacks registered in a provider survive exactly as long as the application
 * they were registered in, and a test that boots a second one starts empty.
 */
final class AuthForms
{
    /** @var array<string, array<int, Closure(array<int, mixed>, AuthForm): array<int, mixed>>> */
    private array $extensions = [];

    /**
     * Adjust one screen's fields before it renders.
     *
     * The callback takes the fields as declared and returns what should render:
     * append to them, replace one, drop one, or return an array of your own.
     * Callbacks run in registration order, each on what the last returned.
     *
     * Registered in a provider's `boot()`, where an application's own runs after
     * every package's — so this composes with a module that adds a field of its
     * own rather than racing it.
     *
     * @param  Closure(array<int, mixed>, AuthForm): array<int, mixed>  $callback
     */
    public function extend(AuthForm $screen, Closure $callback): void
    {
        $this->extensions[$screen->value][] = $callback;
    }

    /**
     * The credentials Fortify's login route reads.
     *
     * The names are Fortify's request keys and are not free to rename: the
     * address field is whatever `Fortify::username()` resolves to, and a failed
     * sign-in is reported against it whichever half was wrong.
     */
    public function login(): Form
    {
        return $this->build(AuthForm::Login, [
            $this->username()->autofocus(),

            $this->password()->autocomplete('current-password'),

            // Not required, and posted only when it is ticked: "off" is the
            // absence of the key, which is the boolean a browser can express.
            Checkbox::make('remember')
                ->label(__('wire-module-auth::messages.remember_me'))
                ->inline(),
        ]);
    }

    /** A new account: whatever `Fortify::createUsersUsing()` is going to read. */
    public function register(): Form
    {
        return $this->build(AuthForm::Register, [
            TextInput::make('name')
                ->label(__('wire-module-auth::messages.name'))
                ->required()
                ->autocomplete('name')
                ->autofocus(),

            $this->username(),

            $this->password()
                ->label(__('wire-module-auth::messages.password'))
                ->autocomplete('new-password'),

            $this->passwordConfirmation(),
        ]);
    }

    /** The address a reset link is sent to — and the same reply either way. */
    public function forgotPassword(): Form
    {
        return $this->build(AuthForm::ForgotPassword, [
            $this->username()->autofocus(),
        ]);
    }

    /**
     * The token from the mailed link, the new password, and the address the
     * link was issued for.
     *
     * The token rides in a `Hidden` field, which renders here and would render
     * nothing in a Livewire form — outside Livewire there is no state for a
     * value to live in, so the input is the only way to carry it. It is a field
     * rather than markup so that every input on these screens is in one place:
     * a screen whose token was written into the view is a screen where "the
     * fields are the schema" has an exception nobody remembers.
     *
     * The address arrives in the query string of the link Fortify mailed and is
     * carried back so the broker can match it against the token. It renders as
     * an ordinary field rather than a hidden one on purpose: a person who
     * changed mail clients and lost the query parameter can still finish, and
     * nothing here is trusted for having been in the form — Fortify validates
     * the pair against the broker.
     */
    public function resetPassword(?string $token = null, ?string $email = null): Form
    {
        return $this->build(AuthForm::ResetPassword, [
            Hidden::make('token')->default($token),

            $this->username()->default($email),

            $this->password()
                ->label(__('wire-module-auth::messages.new_password'))
                ->autocomplete('new-password')
                ->autofocus(),

            $this->passwordConfirmation(),
        ]);
    }

    /** The password of someone already signed in, asked for again. */
    public function confirmPassword(): Form
    {
        return $this->build(AuthForm::ConfirmPassword, [
            $this->password()
                ->autocomplete('current-password')
                ->autofocus(),
        ]);
    }

    /**
     * The six digits from an authenticator app.
     *
     * Six boxes rather than one input, because that is what the code looks like
     * everywhere else a person meets it, and because the boxes take a pasted
     * code apart themselves. The field underneath is still one control with one
     * name: `OtpInput` renders a real input beside the boxes in native-submit
     * mode and lets Alpine hide it, so the challenge is answerable with
     * JavaScript off (ADR 0036 §4).
     *
     * Never `required()`, and the reason is the screen rather than the field:
     * this input and the recovery one are two halves of one form, and the half
     * that is hidden is still in the document. A browser asked to validate a
     * required control it cannot focus refuses the submit and reports it
     * nowhere — a sign-in button that does nothing.
     */
    public function twoFactorCode(): Form
    {
        return $this->build(AuthForm::TwoFactorCode, [
            OtpInput::make('code')
                ->label(__('wire-module-auth::messages.code'))
                ->length(6)
                ->numericOnly()
                ->autofocus(),
        ]);
    }

    /** One of the codes saved when two-factor was switched on. */
    public function twoFactorRecovery(): Form
    {
        return $this->build(AuthForm::TwoFactorRecovery, [
            TextInput::make('recovery_code')
                ->label(__('wire-module-auth::messages.recovery_code'))
                ->autocomplete('one-time-code'),
        ]);
    }

    /** The address a sign-in code is mailed to. */
    public function loginCode(): Form
    {
        return $this->build(AuthForm::LoginCode, [
            $this->username()->autofocus(),
        ]);
    }

    /**
     * The digits from the mail.
     *
     * The same field as the authenticator challenge, and the same reasons: boxes
     * because that is what a code looks like everywhere else, one named input
     * behind them so the form still posts with JavaScript off, and never
     * `required()` — a browser asked to validate a control Alpine has hidden
     * refuses the submit and reports it nowhere.
     *
     * The length is read from config rather than fixed at six, because it is the
     * length the store mints and the two have to agree: boxes for six digits in
     * front of an eight-digit code is a form that cannot be completed.
     */
    public function code(): Form
    {
        return $this->build(AuthForm::Code, [
            OtpInput::make('code')
                ->label(__('wire-module-auth::messages.code'))
                ->length(max(4, (int) config('wire-module-auth.codes.length', 6)))
                ->numericOnly()
                ->autofocus(),
        ]);
    }

    /**
     * A new password, from a code rather than a link.
     *
     * No token field: the token is what the code's row carries, and a screen
     * that showed it would be a screen where the code was decoration. The
     * address is filled in from the request that asked for the code and stays
     * editable — somebody who asked in one browser and finished in another can
     * still complete it.
     */
    public function resetPasswordCode(?string $email = null): Form
    {
        return $this->build(AuthForm::ResetPasswordCode, [
            $this->username()->default($email),

            OtpInput::make('code')
                ->label(__('wire-module-auth::messages.code'))
                ->length(max(4, (int) config('wire-module-auth.codes.length', 6)))
                ->numericOnly(),

            $this->password()
                ->label(__('wire-module-auth::messages.new_password'))
                ->autocomplete('new-password'),

            $this->passwordConfirmation(),
        ]);
    }

    /**
     * The fields as declared, after everything registered has had its say.
     *
     * @param  array<int, mixed>  $fields
     */
    private function build(AuthForm $screen, array $fields): Form
    {
        foreach ($this->extensions[$screen->value] ?? [] as $callback) {
            $fields = $callback($fields, $screen);
        }

        return Form::make()
            ->schema($fields)
            ->nativeSubmit();
    }

    /**
     * The field a person is identified by, whatever the application calls it.
     *
     * `fortify.username` is a column name and the request key that carries it,
     * so the input has to be named after it. The label stays "E-mail" until an
     * application says otherwise, because the default is one and the wording of
     * these screens is a translation file away.
     */
    private function username(): TextInput
    {
        $name = (string) config('fortify.username', 'email');

        $field = TextInput::make($name)
            ->label(__('wire-module-auth::messages.email'))
            ->required()
            ->autocomplete('username');

        // `type=email` is a format rule the browser enforces before anything is
        // posted, so it belongs only on a field that really is an address. An
        // application signing people in by a staff number sets
        // `fortify.username` and would otherwise get an input that rejects
        // every value it was configured for — and translates the label with the
        // `email` key, which is a translation file rather than a fork.
        return $name === 'email' ? $field->email() : $field;
    }

    /** A password input, revealable — the toggle is Alpine, already in the document. */
    private function password(): TextInput
    {
        return TextInput::make('password')
            ->label(__('wire-module-auth::messages.password'))
            ->password()
            ->required()
            ->revealable();
    }

    /** The second one, which Laravel's `confirmed` rule compares against. */
    private function passwordConfirmation(): TextInput
    {
        return TextInput::make('password_confirmation')
            ->label(__('wire-module-auth::messages.confirm_password'))
            ->password()
            ->required()
            ->revealable()
            ->autocomplete('new-password');
    }
}
