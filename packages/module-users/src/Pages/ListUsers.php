<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Pages;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireCore\Actions\EditAction;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Actions\ViewAction;
use NyonCode\WireCore\Notifications\NotificationManager;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WireModuleUsers\Support\AccountGuard;
use NyonCode\WireModuleUsers\Support\Teams;
use NyonCode\WirePanels\Resources\Concerns\BelongsToResource;
use NyonCode\WirePanels\Resources\Pages\ListPage;
use NyonCode\WireTable\Table;

/**
 * The user list.
 *
 * The columns are the resource's; what this page adds is the way *out* of the
 * list, and that is a page's job rather than a resource's. Where a record's
 * pages live depends on the zone this list was opened in — the same resource
 * mounted twice has two sets of URLs — and only the page knows which one it is
 * ({@see BelongsToResource::pageUrl()}).
 *
 * Every action is hidden where its page is not routed, rather than rendered as a
 * dead button: an application may mount the list and nothing else, and the
 * honest answer to "there is no edit page" is no Edit.
 *
 * **You cannot delete yourself here.** Not politeness — an administrator who
 * removes their own row is signed out mid-request into an application they can
 * no longer reach, and if they were the only one, nobody can. Closing your own
 * account is the profile page's business, where it asks twice and takes a
 * password.
 */
class ListUsers extends ListPage
{
    protected static ?string $resource = UserResource::class;

    public function table(Table $table): Table
    {
        // Resolved once, not per render: a header action's URL is
        // record-independent by definition, and `HeaderAction::url()` takes the
        // string rather than a closure for exactly that reason. An unrouted
        // create page contributes no button at all — better than one that leads
        // nowhere.
        $create = $this->pageUrl('create');

        return parent::table($table)
            ->headerActions(array_values(array_filter([
                $create === null ? null : HeaderAction::make('create')
                    ->label(__('wire-panels::messages.create', ['label' => UserResource::label()]))
                    ->icon('plus')
                    ->url($create)
                    ->permission($this->pagePermission('create')),
            ])))
            ->actions([
                ViewAction::make()
                    ->url(fn (Model $record): ?string => $this->pageUrl('view', $record))
                    ->permission($this->pagePermission('view'))
                    ->visible(fn (Model $record): bool => $this->pageUrl('view', $record) !== null),

                // What may be done to an account is AccountGuard's: a super-admin's
                // only by a super-admin, the last super-admin never deleted, and a
                // team's manager removes from the team rather than deleting and
                // sends a reset link rather than setting a password.
                EditAction::make()
                    ->url(fn (Model $record): ?string => $this->pageUrl('edit', $record))
                    ->permission($this->pagePermission('edit'))
                    ->visible(fn (Model $record): bool => $this->pageUrl('edit', $record) !== null && AccountGuard::mayEdit($record)),

                Action::make('sendPasswordReset')
                    ->label(__('wire-module-users::messages.send_password_reset'))
                    ->icon('outline:key')
                    ->permission($this->pagePermission('edit'))
                    ->visible(fn (Model $record): bool => ! $this->isCurrentUser($record) && AccountGuard::mayEdit($record) && ! AccountGuard::mayChangeCredentials($record))
                    ->requiresConfirmation()
                    ->action(fn (Model $record) => $this->sendPasswordReset($record)),

                Action::make('removeFromTeam')
                    ->label(__('wire-module-users::messages.remove_from_team'))
                    ->icon('outline:user-minus')
                    ->color('danger')
                    ->permission($this->pagePermission('edit'))
                    ->visible(fn (Model $record): bool => ! $this->isCurrentUser($record) && AccountGuard::mayRemoveFromTeam($record))
                    ->requiresConfirmation()
                    ->successNotification(__('wire-module-users::messages.removed_from_team'))
                    ->action(fn (Model $record) => Teams::removeMember($record, Teams::currentId() ?? abort(403))),

                DeleteAction::make()
                    ->permission($this->pagePermission('edit'))
                    ->visible(fn (Model $record): bool => ! $this->isCurrentUser($record) && AccountGuard::mayDelete($record))
                    ->successNotification(__('wire-module-users::messages.user_deleted'))
                    // What "delete" means stays the application's model's, so a
                    // `users` table with soft deletes soft-deletes.
                    ->action(fn (Model $record) => $record->delete()),
            ]);
    }

    /** Whether this row is the person reading the page. */
    /**
     * Send the account the application's own password reset link.
     *
     * Through Laravel's password broker, so the link, the mail and its expiry
     * are the application's — the same ones "forgot password" sends.
     */
    protected function sendPasswordReset(Model $record): void
    {
        $status = Password::broker()->sendResetLink([
            UserResource::field('email') => $record->getAttribute(UserResource::field('email')),
        ]);

        $status === Password::RESET_LINK_SENT
            ? NotificationManager::success(__('wire-module-users::messages.password_reset_sent'))
            : NotificationManager::error(__('wire-module-users::messages.password_reset_failed'));
    }

    protected function isCurrentUser(Model $record): bool
    {
        $user = Auth::user();

        return $user instanceof Model
            && $user::class === $record::class
            && $user->getKey() === $record->getKey();
    }
}
