<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use NyonCode\WireCore\Core\Plugin\Contracts\IdentifiesHookTarget;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;
use NyonCode\WireModuleUsers\Resources\UserResource;

/**
 * Changing your own password: its own card, and its own component.
 *
 * Separate from the profile form rather than a fourth field in it, and the
 * reason is not layout. A password change has to prove you are the person
 * whose password it is — `current_password` — and a form that asked for the
 * current password before letting somebody fix a typo in their name would be
 * asking for it on the wrong screen. Two forms, two questions, two buttons.
 *
 * The current password is *also* why this cannot live on the user resource's
 * form: an administrator editing somebody else's account has no current
 * password to give, which is exactly the difference between the two screens.
 *
 * Mountable anywhere. An application that wants the password card on a page of
 * its own writes `@livewire(UpdatePassword::class)` there and turns it off in
 * `wire-module-users.profile.password`.
 */
class UpdatePassword extends Component implements IdentifiesHookTarget
{
    use WithForms;

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
            // The runtime notifies on a successful save; this names the message
            // rather than sending a second one beside it.
            ->successMessage(__('wire-module-users::messages.password_saved'))
            ->schema([
                TextInput::make('current_password')
                    ->label(__('wire-module-users::messages.current_password'))
                    ->password()
                    ->revealable()
                    ->required()
                    // Laravel's own rule, against the guard's own hash: this is
                    // the one check that must not be re-implemented, and there
                    // is nothing here to re-implement it with.
                    ->rules(['current_password']),

                TextInput::make('password')
                    ->label(__('wire-module-users::messages.new_password'))
                    ->password()
                    ->revealable()
                    ->required()
                    // The application's policy, not this package's opinion about
                    // how long a password should be: `Password::defaults()` is
                    // whatever the application set in its own provider, and an
                    // application that set nothing gets Laravel's default.
                    ->rules(['confirmed', Password::defaults()]),

                TextInput::make('password_confirmation')
                    ->label(__('wire-module-users::messages.confirm_password'))
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            // No model and no `save()`: the write is a command, and the record it
            // writes to is never a route parameter — it is whoever is signed in.
            ->using(function (array $data): array {
                $this->writePassword((string) ($data['password'] ?? ''));

                return $data;
            });
    }

    public function save(): void
    {
        $this->form->save();

        // Emptied rather than left filled: three password boxes still holding
        // what was typed, on a page that has already saved them, is both a
        // surprise and something to shoulder-surf.
        $this->data = $this->form->getInitialState();
        $this->form->fill($this->data);
    }

    public function hookKey(): ?string
    {
        return 'users.password';
    }

    public function render(): View
    {
        return view('wire-module-users::livewire.update-password');
    }

    /**
     * Write the new hash, and keep this session signed in while doing it.
     *
     * `AuthenticateSession` compares the session's copy of the password hash
     * against the user's on every request. Change the password without moving
     * that copy along and the very next click signs you out — on the applications
     * that enable the middleware, which is exactly the ones that care most.
     */
    protected function writePassword(string $password): void
    {
        $user = Auth::user();

        // Defensive: `current_password` cannot have passed without a signed-in
        // user, so this is unreachable through save(). It stays because a host
        // that calls the form's `using()` some other way should write nothing
        // rather than fatal.
        // @codeCoverageIgnoreStart
        if (! $user instanceof Model) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $user->forceFill([
            UserResource::field('password') => Hash::make($password),
        ])->save();

        session()->put(
            'password_hash_'.Auth::getDefaultDriver(),
            $user->getAuthPassword(),
        );
    }
}
