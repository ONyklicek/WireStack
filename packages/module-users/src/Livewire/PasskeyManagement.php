<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use NyonCode\WireCore\Core\Plugin\Contracts\IdentifiesHookTarget;
use NyonCode\WireCore\Notifications\NotificationManager;
use NyonCode\WireModuleUsers\Support\Passkeys;

/**
 * The passkey card: a list, a way to add one, and a way to take one away.
 *
 * **The ceremony is not here and cannot be.** Registering a passkey happens in
 * the browser — `navigator.credentials.create()`, the platform's own dialog —
 * and the two routes around it are `laravel/passkeys`'. So this component owns
 * exactly two things Livewire can own: what the list says, and deleting a row.
 * Adding one is a button bound to wire-core's `wirePasskey` controller, which
 * calls those routes and then tells this component to refresh.
 *
 * That split is why {@see refresh()} exists at all: the new key lands in the
 * database through a request this component never saw, so the list has to be
 * asked for again rather than mutated here.
 *
 * Deleting goes through the package's own `DeletePasskey` action, so the
 * `PasskeyDeleted` event fires for anything listening — the same shape the
 * two-factor card uses for Fortify's actions.
 */
class PasskeyManagement extends Component implements IdentifiesHookTarget
{
    /** The label a new key is offered under, and the only thing anybody types. */
    public string $name = '';

    /**
     * A passkey was registered in the browser; ask the database what it says now.
     *
     * Dispatched by the controller rather than returned by an action, because
     * the request that created the row was the passkeys package's own.
     */
    #[On('wire-passkey-registered')]
    public function refresh(): void
    {
        $this->name = '';

        NotificationManager::success(__('wire-module-users::messages.passkey_added'));
    }

    /**
     * Remove one, through the package's own action.
     *
     * Scoped to the signed-in user's own keys by the query rather than by trust:
     * the id arrives from the browser, and a component that looked it up globally
     * would let anybody delete anybody's passkey by typing a number.
     */
    public function forget(int|string $passkey): void
    {
        $user = $this->user();

        if ($user === null || ! Passkeys::usable($user) || ! class_exists(Passkeys::DELETE_ACTION)) {
            return;
        }

        /** @var Model|null $record */
        $record = $user->passkeys()->whereKey($passkey)->first();

        if ($record === null) {
            return;
        }

        /** @var object{__invoke: callable} $delete */
        $delete = app(Passkeys::DELETE_ACTION);

        $delete($user, $record);

        NotificationManager::success(__('wire-module-users::messages.passkey_removed'));
    }

    /** What a plugin targets this card by, the way every other card is targeted. */
    public function hookKey(): ?string
    {
        return 'users.passkeys';
    }

    public function render(): View
    {
        $user = $this->user();

        return view('wire-module-users::livewire.passkeys', [
            'passkeys' => Passkeys::forUser($user),
            // The trait is the one thing an application has to add itself, and
            // its absence is silent everywhere else: the routes exist, the
            // button works, and the key it registers belongs to nobody.
            'usable' => Passkeys::usable($user),
        ]);
    }

    /** The signed-in person, as a model — the card is about their own account. */
    protected function user(): ?Model
    {
        $user = Auth::user();

        return $user instanceof Model ? $user : null;
    }
}
