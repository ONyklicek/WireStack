<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use NyonCode\WireCore\Core\Plugin\Contracts\IdentifiesHookTarget;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * Closing your own account.
 *
 * Off by default (`wire-module-users.profile.delete_account`), and that is a
 * decision rather than caution: in an admin panel the person reading this page
 * is usually staff, and an administrator who can remove themselves in two
 * clicks is a support ticket. Where accounts *are* self-service, turning it on
 * is one line.
 *
 * Two gates, because one is not enough for something with no undo: a modal that
 * has to be opened deliberately, and the account's own password typed into it.
 * `current_password` is Laravel's rule against the guard's hash — the same check
 * the password card makes, for the same reason.
 */
class DeleteAccount extends Component implements IdentifiesHookTarget
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

        // Cleared on the way out, not on the way back in: a dialog reopened by
        // a mis-click should not already hold a password.
        $this->data = $this->form->getInitialState();
        $this->form->fill($this->data);
    }

    /**
     * Validate the password, then leave — in that order.
     *
     * The sign-out and the session invalidation are not politeness. The record
     * behind this session is about to stop existing, and a session left pointing
     * at it is a request away from an error page that says so.
     */
    public function delete(): ?RedirectResponse
    {
        $user = Auth::user();

        // Before the validation, not after: `current_password` is a rule about
        // the signed-in user, and with nobody signed in there is nothing to
        // check it against and nothing to delete.
        if (! $user instanceof Model) {
            return null;
        }

        $this->form->validate();

        Auth::logout();

        session()->invalidate();
        session()->regenerateToken();

        // What "delete" means is the model's: a soft-deleting `users` table soft
        // deletes, and an application that anonymises instead overrides
        // `deleting()` rather than configuring something here. The avatar file is
        // deliberately left alone — the same column may hold a URL this
        // application does not own, and a package that guessed would be deleting
        // somebody else's file.
        $user->delete();

        return redirect()->to(url('/'));
    }

    public function hookKey(): ?string
    {
        return 'users.delete-account';
    }

    public function render(): View
    {
        return view('wire-module-users::livewire.delete-account');
    }
}
