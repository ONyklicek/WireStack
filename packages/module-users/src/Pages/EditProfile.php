<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Pages;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireForms\Components\Field;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleUsers\Livewire\DeleteAccount;
use NyonCode\WireModuleUsers\Livewire\TwoFactorAuthentication;
use NyonCode\WireModuleUsers\Livewire\UpdatePassword;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WireModuleUsers\Support\TwoFactor;
use NyonCode\WirePanels\Resources\Pages\EditPage;

/**
 * Your own account: `/profile`, or wherever the application routes it.
 *
 * The same form as {@see EditUser}, from the same resource, with two fields
 * taken away — and both of them are the point.
 *
 * **The record is the signed-in user, never a route parameter.** A profile page
 * that took an id would be an edit page with a friendlier name, and the first
 * time somebody changed the number in the URL it would be an account takeover.
 * There is nothing to guess here because there is nothing to pass.
 *
 * **Roles are removed from the schema**, not merely ignored on save. Somebody
 * editing their own account must not be able to add themselves to a role, and a
 * field that is rendered and then discarded is one refactor away from being a
 * field that is rendered and then honoured.
 *
 * **The password is removed too**, and that is the newer half. A field with
 * "leave empty to keep the current password" under it is an administrator's
 * control, on a screen where the administrator is the account holder — so it
 * moves to {@see UpdatePassword}, which can ask for the current password first.
 * On an admin editing somebody else's account there is no current password to
 * ask for, which is exactly why the two screens cannot share one field.
 *
 * Everything else — the avatar, the column names an application configured, any
 * field an application added to its own user resource — comes from
 * `UserResource::form()`, because a second copy of those rules is a second copy
 * that drifts.
 *
 * The other cards are components, not sections: each of them is a separate
 * question with a separate button, and an application that wants only one of
 * them mounts it on a page of its own. What this page decides is which ones
 * appear (`wire-module-users.profile`), not what they contain.
 */
class EditProfile extends EditPage
{
    protected static ?string $resource = UserResource::class;

    public function form(Form $form): Form
    {
        $configured = parent::form($form);

        $password = UserResource::field('password');

        // Filtered out of the composed schema rather than rebuilt without them:
        // the resource decides what a user form is, and this page's job is to
        // say which two fields it may not contain.
        return $configured
            // The form already notifies on a successful save; this names the
            // message rather than sending a second one beside it. Two toasts for
            // one save is what an override of save() would have produced.
            ->successMessage(__('wire-module-users::messages.profile_saved'))
            ->schema($this->withoutAdministratorFields($configured->getSchema(), $password));
    }

    /**
     * The composed schema with the password and the roles select taken out of it,
     * at whatever depth the resource put them.
     *
     * **Recursive on purpose.** A one-level filter was correct exactly as long as
     * the resource laid its fields out flat; the day it grouped them into
     * sections, the same filter matched three headings, removed nothing, and
     * every assertion over the top-level names still passed — with the password
     * field on the page. A removal that silently becomes a no-op when somebody
     * else rearranges their own schema is not a removal.
     *
     * A section left holding nothing goes with them, rather than staying as a
     * heading over empty space.
     *
     * @param  array<int, mixed>  $schema
     * @return array<int, mixed>
     */
    protected function withoutAdministratorFields(array $schema, string $password): array
    {
        $kept = [];

        foreach ($schema as $component) {
            if ($component instanceof Select && $component->getName() === 'roles') {
                continue;
            }

            if ($component instanceof Field && $component->getName() === $password) {
                continue;
            }

            if ($component instanceof LayoutComponent) {
                $children = $this->withoutAdministratorFields($component->getSchema(), $password);

                if ($children === []) {
                    continue;
                }

                $component->schema($children);
            }

            $kept[] = $component;
        }

        return $kept;
    }

    /**
     * Whoever is signed in, and nobody else.
     *
     * @throws AuthenticationException When nobody is.
     */
    public function resolveRecord(): Model
    {
        $user = Auth::user();

        if (! $user instanceof Model) {
            // The route should sit behind `auth`. If it does not, this is the
            // difference between a redirect and a page that edits nothing while
            // looking like it worked.
            throw new AuthenticationException;
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function recordData(): array
    {
        $data = parent::recordData();

        // Removed rather than trusted to be hidden — see EditUser for why one
        // `unset` beats depending on somebody else's `$hidden`.
        unset($data[UserResource::field('password')], $data['roles']);

        return $data;
    }

    public function getTitle(): ?string
    {
        return $this->title ?? __('wire-module-users::messages.profile');
    }

    /**
     * Which cards this installation shows below the profile form.
     *
     * @return array<int, class-string>
     */
    public function cards(): array
    {
        $cards = [];

        if (config('wire-module-users.profile.password', true)) {
            $cards[] = UpdatePassword::class;
        }

        // Two switches, and both have to be on: the application may want the
        // card off, and the application may have no Fortify to drive it.
        if (config('wire-module-users.profile.two_factor', true) && TwoFactor::enabled()) {
            $cards[] = TwoFactorAuthentication::class;
        }

        if (config('wire-module-users.profile.delete_account', false)) {
            $cards[] = DeleteAccount::class;
        }

        return $cards;
    }

    public function render(): View
    {
        return view('wire-module-users::livewire.profile', [
            'title' => $this->getTitle(),
            'breadcrumbs' => $this->breadcrumbs(),
            'cards' => $this->cards(),
        ]);
    }
}
