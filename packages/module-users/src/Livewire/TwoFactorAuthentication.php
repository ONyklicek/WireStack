<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use NyonCode\WireCore\Core\Plugin\Contracts\IdentifiesHookTarget;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithPasswordConfirmation;
use NyonCode\WireCore\Notifications\NotificationManager;
use NyonCode\WireForms\Components\OtpInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleUsers\Support\TwoFactor;
use Throwable;

/**
 * The two-factor card: a screen over Fortify, never a second implementation.
 *
 * Every button here resolves one of Fortify's own actions out of the container
 * and calls it. Nothing in this class generates a secret, formats an
 * `otpauth://` URI, checks a code against a time window or writes a recovery
 * code — all of that is Fortify's, along with the responsibility for getting it
 * right. What was missing was somewhere to press the buttons, which is what an
 * admin panel is for.
 *
 * The three states this card can be in are worth naming, because they are not
 * two:
 *
 *   - **off** — no secret. One button: turn it on.
 *   - **pending** — a secret exists and has not been confirmed. This is where a
 *     QR code and a code box belong, and it is also the state a user is left in
 *     by opening the panel and closing the tab. It must be possible to leave it
 *     in both directions.
 *   - **on** — confirmed. Recovery codes, and a way off.
 *
 * A panel that modelled this as a boolean would strand every user in the middle
 * one, protected by nothing while the badge says they are safe.
 */
class TwoFactorAuthentication extends Component implements IdentifiesHookTarget
{
    /**
     * Every state change and every reveal on this card sits behind a recently
     * confirmed password, because Fortify's own routes for these actions do.
     * Driving its actions out of the container reaches them around that guard,
     * so the guard is re-stated here rather than lost. See the trait.
     */
    use InteractsWithPasswordConfirmation;

    /** The code typed in to finish the setup. */
    public string $code = '';

    /** Whether the recovery codes are on screen. Never on by default. */
    public bool $showingRecoveryCodes = false;

    /**
     * The box the setup code is typed into — the same field as the challenge.
     *
     * It used to be a hand-written `<input autocomplete="one-time-code">`, three
     * clicks away from the screen on the way in that draws six boxes for the
     * same six digits (ADR 0037 §6). One field, one behaviour: the boxes advance
     * themselves, a pasted code fills the row, and the value still arrives in
     * `$code` because with no state path the binding is this component's own
     * property.
     */
    public function codeField(): Form
    {
        return Form::make()->schema([
            OtpInput::make('code')
                ->label(__('wire-module-users::messages.two_factor_code'))
                ->length(6)
                ->numericOnly(),
        ]);
    }

    public function enable(): void
    {
        if (! $this->ensurePasswordConfirmed()) {
            return;
        }

        if (! $this->run(TwoFactor::ENABLE_ACTION)) {
            return;
        }

        $this->showingRecoveryCodes = false;

        // No success notification: the card has just grown a QR code and a code
        // box, and "Two-factor enabled" over the top of a setup that is not
        // finished is exactly the lie the pending state exists to prevent.
    }

    public function confirm(): void
    {
        if (! $this->ensurePasswordConfirmed()) {
            return;
        }

        $user = $this->user();

        if ($user === null) {
            return;
        }

        try {
            /** @var object{__invoke: callable} $action */
            $action = app(TwoFactor::CONFIRM_ACTION);

            $action($user, $this->code);
        } catch (Throwable $e) {
            // Fortify throws a ValidationException for a wrong code, which
            // Livewire turns into an error bag on `code` — that is the message
            // the user should read, so it is re-thrown rather than swallowed.
            $this->code = '';

            throw $e;
        }

        $this->code = '';
        $this->showingRecoveryCodes = true;

        NotificationManager::success(__('wire-module-users::messages.two_factor_enabled'));
    }

    public function disable(): void
    {
        if (! $this->ensurePasswordConfirmed()) {
            return;
        }

        if (! $this->run(TwoFactor::DISABLE_ACTION)) {
            return;
        }

        $this->code = '';
        $this->showingRecoveryCodes = false;

        NotificationManager::success(__('wire-module-users::messages.two_factor_disabled'));
    }

    public function regenerateRecoveryCodes(): void
    {
        if (! $this->ensurePasswordConfirmed()) {
            return;
        }

        if (! $this->run(TwoFactor::GENERATE_CODES_ACTION)) {
            return;
        }

        $this->showingRecoveryCodes = true;

        NotificationManager::success(__('wire-module-users::messages.recovery_codes_regenerated'));
    }

    public function toggleRecoveryCodes(): void
    {
        if (! $this->ensurePasswordConfirmed()) {
            return;
        }

        $this->showingRecoveryCodes = ! $this->showingRecoveryCodes;
    }

    /** The QR code Fortify renders, as inline SVG, or null when there is nothing to scan. */
    public function qrCodeSvg(): ?string
    {
        $user = $this->user();

        if ($user === null || ! method_exists($user, 'twoFactorQrCodeSvg') || ! TwoFactor::pending($user)) {
            return null;
        }

        try {
            return (string) $user->twoFactorQrCodeSvg();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The secret in the form somebody types into an authenticator by hand.
     *
     * Offered beside the QR code rather than instead of it: a phone that cannot
     * scan — a desktop authenticator, a locked-down camera — is common enough
     * that a setup screen with only a picture on it is a setup screen some people
     * cannot finish.
     */
    public function setupKey(): ?string
    {
        $user = $this->user();

        // Read on every render, so it asks rather than redirects: a stale window
        // hides the secret and the card says why, instead of bouncing somebody
        // off a page they only opened to look at.
        if ($user === null || ! TwoFactor::pending($user) || ! $this->hasConfirmedPasswordRecently()) {
            return null;
        }

        try {
            // The column, not a method: Fortify exposes the secret as an
            // encrypted attribute and offers no accessor for the plain one.
            return (string) decrypt((string) $user->getAttribute('two_factor_secret'));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The recovery codes, or an empty list when there are none to show.
     *
     * @return array<int, string>
     */
    public function recoveryCodes(): array
    {
        $user = $this->user();

        if ($user === null || ! $this->showingRecoveryCodes || ! $this->hasConfirmedPasswordRecently()) {
            return [];
        }

        if ($user->getAttribute('two_factor_recovery_codes') === null) {
            return [];
        }

        try {
            $codes = json_decode(decrypt((string) $user->getAttribute('two_factor_recovery_codes')), true);

            return is_array($codes) ? array_values(array_filter($codes, 'is_string')) : [];
        } catch (Throwable) {
            return [];
        }
    }

    public function hookKey(): ?string
    {
        return 'users.two-factor';
    }

    public function render(): View
    {
        $user = $this->user();

        return view('wire-module-users::livewire.two-factor', [
            'confirmed' => TwoFactor::confirmed($user),
            'pending' => TwoFactor::pending($user) && ! TwoFactor::confirmed($user),
            'qrCode' => $this->qrCodeSvg(),
            'setupKey' => $this->setupKey(),
            'codes' => $this->recoveryCodes(),
            // The card explains a stale window rather than silently missing a QR.
            'needsPasswordConfirmation' => ! $this->hasConfirmedPasswordRecently(),
            'passwordConfirmationUrl' => $this->passwordConfirmationUrl(),
        ]);
    }

    /**
     * Resolve one of Fortify's actions and run it against the signed-in user.
     *
     * False means there was nobody to run it for, or no Fortify to run — both of
     * which are states this card can be rendered in and neither of which is an
     * error worth an exception on a profile page.
     */
    protected function run(string $action): bool
    {
        $user = $this->user();

        if ($user === null || ! class_exists($action)) {
            return false;
        }

        /** @var object{__invoke: callable} $resolved */
        $resolved = app($action);

        $resolved($user);

        // The action wrote columns straight to the database; this instance is
        // the one the view is about to read.
        $user->refresh();

        return true;
    }

    protected function user(): ?Model
    {
        $user = Auth::user();

        return $user instanceof Model ? $user : null;
    }
}
