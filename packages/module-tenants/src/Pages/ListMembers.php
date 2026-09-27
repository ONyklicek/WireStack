<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Pages;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Notifications\NotificationManager;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireModuleTenants\Actions\ChangeMemberRole;
use NyonCode\WireModuleTenants\Actions\InviteMember;
use NyonCode\WireModuleTenants\Actions\RemoveMember;
use NyonCode\WireModuleTenants\Enums\MemberRole;
use NyonCode\WireModuleTenants\Exceptions\TenantMembershipException;
use NyonCode\WireModuleTenants\Resources\MemberResource;
use NyonCode\WireModuleTenants\Support\Membership;
use NyonCode\WirePanels\Resources\Pages\ListPage;
use NyonCode\WireTable\Table;

/**
 * The company's members. Everybody in it may look; only an owner invites,
 * removes and changes roles — each action asked of Membership again when it
 * runs, since a button that was drawn is not a permission.
 */
class ListMembers extends ListPage
{
    protected static ?string $resource = MemberResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)->actions([
            Action::make('makeOwner')
                ->label(__('wire-module-tenants::messages.make_owner'))
                ->icon('outline:star')
                ->visible(fn (Model $record): bool => $this->isOwner() && Membership::roleOf($this->tenant(), $record) === MemberRole::Member)
                ->requiresConfirmation()
                ->action(fn (Model $record) => $this->attempt(fn () => (new ChangeMemberRole)($this->tenant(), $record, MemberRole::Owner, $this->actor()))),

            Action::make('makeMember')
                ->label(__('wire-module-tenants::messages.make_member'))
                ->icon('outline:user')
                ->visible(fn (Model $record): bool => $this->isOwner() && Membership::roleOf($this->tenant(), $record) === MemberRole::Owner && ! Membership::isLastOwner($this->tenant(), $record))
                ->requiresConfirmation()
                ->action(fn (Model $record) => $this->attempt(fn () => (new ChangeMemberRole)($this->tenant(), $record, MemberRole::Member, $this->actor()))),

            Action::make('removeMember')
                ->label(__('wire-module-tenants::messages.remove_member'))
                ->icon('outline:user-minus')
                ->color('danger')
                ->visible(fn (Model $record): bool => $this->isOwner() && ! Membership::isLastOwner($this->tenant(), $record))
                ->requiresConfirmation()
                ->action(fn (Model $record) => $this->attempt(fn () => (new RemoveMember)($this->tenant(), $record, $this->actor()))),
        ]);
    }

    /** @return array<int, Action|null> */
    protected function headerActions(): array
    {
        return [
            Action::make('invite')
                ->label(__('wire-module-tenants::messages.invite'))
                ->icon('outline:envelope')
                ->visible(fn (): bool => $this->isOwner())
                ->form([
                    TextInput::make('email')->label(__('wire-module-tenants::messages.email'))->email()->required(),
                    Select::make('role')
                        ->label(__('wire-module-tenants::messages.role'))
                        ->options([MemberRole::Member->value => MemberRole::Member->label(), MemberRole::Owner->value => MemberRole::Owner->label()])
                        ->default(MemberRole::Member->value)
                        ->required(),
                ])
                ->action(fn (array $data) => $this->invite($data)),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function invite(array $data): void
    {
        $this->attempt(function () use ($data): void {
            (new InviteMember)($this->tenant(), (string) $data['email'], MemberRole::from((string) $data['role']), $this->actor());
            NotificationManager::success(__('wire-module-tenants::messages.invited', ['email' => $data['email']]));
        });
    }

    public function getTitle(): ?string
    {
        return __('wire-module-tenants::messages.members');
    }

    protected function tenant(): Model
    {
        return Membership::current() ?? abort(404);
    }

    protected function actor(): Model
    {
        $user = auth()->user();

        return $user instanceof Model ? $user : abort(403);
    }

    protected function isOwner(): bool
    {
        return Membership::current() !== null && Membership::isOwner($this->tenant(), auth()->user());
    }

    /** Run a membership change and say so when a rule refuses it. */
    protected function attempt(Closure $change): void
    {
        try {
            $change();
        } catch (TenantMembershipException $refused) {
            NotificationManager::error($refused->getMessage());
        }
    }
}
