<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Livewire;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use NyonCode\WireCore\Core\Plugin\Contracts\IdentifiesHookTarget;
use NyonCode\WireCore\Notifications\NotificationManager;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;
use NyonCode\WireModuleUsers\Services\BrowserSessionStore;

/**
 * Where this account is signed in, and a way to end every session but this one.
 *
 * **Two mechanisms, because either alone leaves a door open.** Deleting the
 * other rows from the `sessions` table ends them on the database driver — and
 * on no other, since nothing else can say which sessions are whose. Laravel's
 * `logoutOtherDevices()` rehashes the password, which ends every other session
 * on any driver, but only where `AuthenticateSession` is there to compare the
 * hash. Doing both is what makes the button mean what it says.
 *
 * The rehash is also why the password is asked for: `logoutOtherDevices()`
 * needs the plain text to hash again, and a person holding a borrowed session
 * should not be able to lock the owner out of their own others.
 */
class BrowserSessionManagement extends Component implements IdentifiesHookTarget
{
    use WithForms;

    /** Whether the confirmation dialog is open. Public: it survives the round trip. */
    public bool $confirming = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->data = $this->form->getInitialState();
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                TextInput::make('password')
                    ->label(__('wire-module-users::messages.password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->rules(['current_password']),
            ]);
    }

    public function confirm(): void
    {
        $this->data = $this->form->getInitialState();
        $this->form->fill($this->data);

        $this->confirming = true;
    }

    public function cancel(): void
    {
        $this->confirming = false;

        // Cleared on the way out: a password must not wait in a closed dialog.
        $this->data = $this->form->getInitialState();
        $this->form->fill($this->data);
    }

    /** End every other session of this account, once the password is right. */
    public function logoutOtherSessions(): void
    {
        $user = Auth::user();

        if (! $user instanceof Authenticatable) {
            return;
        }

        $this->form->validate();

        $guard = Auth::guard();

        // A token or custom guard has no other devices to sign out; the rows
        // below are still worth deleting.
        if (method_exists($guard, 'logoutOtherDevices')) {
            $guard->logoutOtherDevices((string) ($this->data['password'] ?? ''));
        }

        $this->sessions()->forgetOthers($user, session()->getId());

        // The rehash changed the hash `AuthenticateSession` compares this
        // session against. Without moving the copy along, the button would sign
        // out the one session it promised to keep.
        session()->put('password_hash_'.Auth::getDefaultDriver(), $user->getAuthPassword());

        $this->cancel();

        NotificationManager::success(__('wire-module-users::messages.browser_sessions_logged_out'));
    }

    public function hookKey(): ?string
    {
        return 'users.browser-sessions';
    }

    public function render(): View
    {
        $user = Auth::user();
        $sessions = $this->sessions();

        return view('wire-module-users::livewire.browser-sessions', [
            'sessions' => $user instanceof Authenticatable
                ? $sessions->forUser($user, session()->getId())
                : collect(),
            'listable' => $sessions->listable(),
        ]);
    }

    protected function sessions(): BrowserSessionStore
    {
        return app(BrowserSessionStore::class);
    }
}
