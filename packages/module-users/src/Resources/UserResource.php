<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Infolists\Components\ImageEntry;
use NyonCode\WireCore\Infolists\Components\ListEntry;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireForms\Components\FileUpload;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleUsers\Pages\CreateUser;
use NyonCode\WireModuleUsers\Pages\EditProfile;
use NyonCode\WireModuleUsers\Pages\EditUser;
use NyonCode\WireModuleUsers\Pages\ListUsers;
use NyonCode\WireModuleUsers\Pages\ViewUser;
use NyonCode\WireModuleUsers\Support\Avatars;
use NyonCode\WireModuleUsers\Support\Roles;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WireTable\Columns\ImageColumn;
use NyonCode\WireTable\Columns\TagsColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

/**
 * The people who can sign in, as a resource.
 *
 * Over the **application's** user model rather than one of the module's: a
 * package that brought its own users table would be unusable in every
 * application that already has one, which is all of them. The model and the
 * three column names it touches are configuration, because `users` is the one
 * table every application has changed.
 *
 * Roles appear only where they exist (see {@see Roles}), and nothing here does
 * authorization: that is `Gate::allows()` through the resource's own policy, the
 * same as every other resource in this framework.
 */
class UserResource implements DescribesResource, ProvidesNavigation, ProvidesPages, ProvidesResourceForm, ProvidesResourceInfolist, ProvidesResourceTable
{
    use DescribesRecords;

    public static function key(): string
    {
        return 'users';
    }

    public static function modelClass(): ?string
    {
        $model = config('wire-module-users.model');

        return is_string($model) && $model !== '' ? $model : null;
    }

    public static function label(): string
    {
        return __('wire-module-users::messages.user');
    }

    public static function pluralLabel(): string
    {
        return __('wire-module-users::messages.users');
    }

    public static function pages(): array
    {
        return [
            'index' => ListUsers::class,
            'create' => CreateUser::class,
            // Before `view`, and the order is load-bearing: an unknown page key
            // routes at `{prefix}/{name}`, so `users/profile` and the
            // `users/{record}` this sits above are the same URL shape. Declared
            // after it, "profile" would be looked up as a user's key and 404.
            'profile' => EditProfile::class,
            'view' => ViewUser::class,
            'edit' => EditUser::class,
        ];
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make(fn (): string => __('wire-module-users::messages.users'))
            ->group((string) config('wire-module-users.navigation.group', 'access'))
            ->icon((string) config('wire-module-users.navigation.icon', 'outline:users'));
    }

    public function table(Table $table): Table
    {
        $columns = [];

        if (Avatars::enabled()) {
            // A list of people reads as a list of people. Circular and unlabelled
            // because the name is the next column along and a header over a
            // 32px picture is noise.
            $columns[] = ImageColumn::make(Avatars::column())
                ->label('')
                ->circular()
                ->size('sm')
                ->disk(Avatars::disk());
        }

        $columns[] = TextColumn::make(self::field('name'))->label(__('wire-module-users::messages.name'))->searchable()->sortable();
        $columns[] = TextColumn::make(self::field('email'))->label(__('wire-module-users::messages.email'))->searchable()->sortable();

        if (Roles::enabled()) {
            // Read through the relation the permission package defines, so a
            // renamed roles table or a swapped model is still followed. Chips
            // rather than a comma-joined string, because a role name is a label
            // and three of them run together into one word otherwise.
            $columns[] = TagsColumn::make('roles')
                ->label(__('wire-module-users::messages.roles'))
                ->state(static fn (Model $record): array => self::relatedNames($record, 'roles'))
                ->color(Color::Info)
                ->limitList(3)
                ->wrap();
        }

        return $table->columns($columns)->defaultSort(self::field('name'));
    }

    /**
     * The three questions a user record answers, one section each: who they are,
     * how they sign in, and what they may reach.
     *
     * Grouped rather than listed because the middle one is the dangerous one — a
     * password field sitting between an e-mail and a role picker reads as one
     * more detail, and it is not.
     */
    public function form(Form $form): Form
    {
        $password = self::field('password');

        $profile = [];

        if (Avatars::enabled()) {
            // `avatar()` is the shipped mode, not a bag of options assembled
            // here: single image, round crop UI, 1:1 locked. Where it goes and
            // what disk it lands on is the application's, through config.
            // Full width above the name and e-mail: a round 96px crop control
            // in a half-column is a crop control nobody can aim.
            $profile[] = FileUpload::make(Avatars::column())
                ->label(__('wire-module-users::messages.avatar'))
                ->avatar()
                ->disk(Avatars::disk())
                ->directory(Avatars::directory())
                ->maxSize(2048)
                ->columnSpanFull()
                ->helperText(__('wire-module-users::messages.avatar_hint'));
        }

        $profile[] = TextInput::make(self::field('name'))->label(__('wire-module-users::messages.name'))->required();
        $profile[] = TextInput::make(self::field('email'))->label(__('wire-module-users::messages.email'))->email()->required();

        $schema = [
            // `make()` takes a key and `label()` the heading: passing the
            // translated string as the name sends it through `Str::headline()`,
            // which title-cases it — wrong in every language that is not
            // English.
            Section::make('profile')
                ->label(__('wire-module-users::messages.profile_information'))
                ->icon('outline:user-circle')
                ->columns(['default' => 1, 'sm' => 2])
                ->schema($profile),

            Section::make('security')
                ->label(__('wire-module-users::messages.security'))
                ->icon('outline:lock-closed')
                ->description(__('wire-module-users::messages.password_hint'))
                ->schema([
                    TextInput::make($password)
                        ->label(__('wire-module-users::messages.password'))
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password'),
                ]),
        ];

        if (Roles::enabled()) {
            $schema[] = Section::make('access')
                ->label(__('wire-module-users::messages.access'))
                ->icon('outline:shield-check')
                ->schema([
                    Select::make('roles')
                        ->label(__('wire-module-users::messages.roles'))
                        ->hiddenLabel()
                        ->multiple()
                        ->options(Roles::options()),
                ]);
        }

        return $form
            ->schema($schema)
            // Two things the form must never do: write an empty password over a
            // real one, and store what was typed. Both happen here rather than
            // in a page, so every host that composes this form is covered —
            // including one an application writes itself.
            ->mutateDataBeforeSave(static function (array $data) use ($password): array {
                unset($data['roles']);

                if (($data[$password] ?? '') === '' || ! isset($data[$password])) {
                    unset($data[$password]);

                    return $data;
                }

                $data[$password] = Hash::make((string) $data[$password]);

                return $data;
            });
    }

    /**
     * One user, grouped the way the form groups them, so the read-only page and
     * the editable one are the same screen with the controls taken out.
     *
     * The picture leads because it is what a person is recognised by; roles are
     * chips rather than a joined string for the reason the list column gives.
     */
    public function infolist(Infolist $infolist): Infolist
    {
        $profile = [];

        if (Avatars::enabled()) {
            $profile[] = ImageEntry::make(Avatars::column())
                ->label(__('wire-module-users::messages.avatar'))
                ->hiddenLabel()
                ->circular()
                ->imageSize(80)
                ->disk(Avatars::disk())
                ->columnSpanFull();
        }

        $profile[] = TextEntry::make(self::field('name'))
            ->label(__('wire-module-users::messages.name'))
            ->icon('outline:identification')
            ->weight('semibold');

        $profile[] = TextEntry::make(self::field('email'))
            ->label(__('wire-module-users::messages.email'))
            ->icon('outline:envelope')
            ->copyable();

        $profile[] = TextEntry::make('created_at')
            ->label(__('wire-module-users::messages.created'))
            ->icon('outline:calendar-days')
            ->dateTime()
            ->placeholder('—');

        $schema = [
            Section::make('profile')
                ->label(__('wire-module-users::messages.profile_information'))
                ->icon('outline:user-circle')
                ->columns(['default' => 1, 'sm' => 2, 'xl' => 3])
                ->schema($profile),
        ];

        if (Roles::enabled()) {
            $schema[] = Section::make('access')
                ->label(__('wire-module-users::messages.access'))
                ->icon('outline:shield-check')
                ->schema([
                    ListEntry::make('roles')
                        ->label(__('wire-module-users::messages.roles'))
                        ->hiddenLabel()
                        ->state(static fn (Model $record): array => self::relatedNames($record, 'roles'))
                        ->badge()
                        ->color(Color::Info)
                        ->placeholder(__('wire-module-users::messages.no_roles')),
                ]);
        }

        return $infolist->schema($schema);
    }

    /**
     * The `name` column of everything hanging off a relation.
     *
     * Guarded rather than assumed: an application may point the module at a user
     * model that never took the permission package's trait, and a screen that
     * fatals on `$record->roles` is worse than one that shows none.
     *
     * @return array<int, string>
     */
    protected static function relatedNames(Model $record, string $relation): array
    {
        if (! method_exists($record, $relation)) {
            return [];
        }

        return $record->{$relation}
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->values()
            ->all();
    }

    /** The column an application uses for one of the three fields this touches. */
    public static function field(string $name): string
    {
        $configured = config("wire-module-users.fields.{$name}");

        return is_string($configured) && $configured !== '' ? $configured : $name;
    }
}
