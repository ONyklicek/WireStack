<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireModuleTenants\Pages\ListMembers;
use NyonCode\WireModuleTenants\Support\Membership;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

/**
 * The people of the company being worked in, and what each of them is there.
 *
 * Over the application's user model, narrowed to the current company's
 * members — never a list of every account, which would be a way to find out
 * who else uses the application.
 */
final class MemberResource implements DescribesResource, ProvidesNavigation, ProvidesPages, ProvidesResourceTable
{
    use DescribesRecords;

    public static function key(): string
    {
        return 'members';
    }

    public static function modelClass(): ?string
    {
        return Membership::userModel();
    }

    public static function label(): string
    {
        return __('wire-module-tenants::messages.member');
    }

    public static function pluralLabel(): string
    {
        return __('wire-module-tenants::messages.members');
    }

    public static function pages(): array
    {
        return ['index' => ListMembers::class];
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make(fn (): string => __('wire-module-tenants::messages.members'))
            ->icon('outline:user-group')
            ->group((string) config('wire-module-tenants.navigation.group', 'company'))
            ->sort(20)
            ->visible(static fn (): bool => Membership::current() !== null);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => self::ofCurrentCompany($query))
            ->columns([
                TextColumn::make('name')->label(__('wire-module-tenants::messages.name'))->searchable()->sortable(),
                TextColumn::make('email')->label(__('wire-module-tenants::messages.email'))->searchable(),
                BadgeColumn::make('tenant_role')
                    ->label(__('wire-module-tenants::messages.role'))
                    ->state(fn (Model $record): ?string => self::roleLabel($record)),
            ]);
    }

    /**
     * The people of the company being worked in — and nobody at all outside one.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function ofCurrentCompany(Builder $query): Builder
    {
        $tenant = Membership::current();

        return $tenant === null
            ? $query->whereRaw('1 = 0')
            : $query->whereKey(Membership::memberIds($tenant));
    }

    /** What this person is in the company being worked in, as the column shows it. */
    public static function roleLabel(Model $member): ?string
    {
        $tenant = Membership::current();

        return $tenant === null ? null : Membership::roleOf($tenant, $member)?->label();
    }
}
