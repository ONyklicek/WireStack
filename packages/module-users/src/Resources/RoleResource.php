<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Resources;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Infolists\Components\BadgeEntry;
use NyonCode\WireCore\Infolists\Components\ListEntry;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireForms\Components\CheckboxList;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleUsers\Pages\CreateRole;
use NyonCode\WireModuleUsers\Pages\EditRole;
use NyonCode\WireModuleUsers\Pages\ListRoles;
use NyonCode\WireModuleUsers\Pages\ViewRole;
use NyonCode\WireModuleUsers\Support\Roles;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TagsColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

/**
 * Roles, where the application has them.
 *
 * Over the permission package's own model, read from its config, so an
 * application that swapped the model keeps its swap. Permissions are edited as
 * names — which is what `syncPermissions()` takes, and what a wildcard like
 * `invoices.*` is when `nyoncode/laravel-permission-extended` is installed.
 *
 * All three surfaces are built from the same two facts — what the role *is*,
 * and what it *grants* — so the list, the form and the read-only page group
 * their fields the same way and a permission name looks like a permission name
 * on each of them.
 */
class RoleResource implements DescribesResource, ProvidesNavigation, ProvidesPages, ProvidesResourceForm, ProvidesResourceInfolist, ProvidesResourceTable
{
    use DescribesRecords;

    public static function key(): string
    {
        return 'roles';
    }

    public static function modelClass(): ?string
    {
        return Roles::enabled() ? Roles::roleModel() : null;
    }

    public static function label(): string
    {
        return __('wire-module-users::messages.role');
    }

    public static function pluralLabel(): string
    {
        return __('wire-module-users::messages.roles');
    }

    public static function pages(): array
    {
        return [
            'index' => ListRoles::class,
            'create' => CreateRole::class,
            'view' => ViewRole::class,
            'edit' => EditRole::class,
        ];
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make(fn (): string => __('wire-module-users::messages.roles'))
            ->group((string) config('wire-module-users.navigation.group', 'access'))
            ->icon('outline:shield-check');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('wire-module-users::messages.name'))
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),

                // The guard is a short, low-cardinality token — a chip reads as
                // one value, where a bare cell in a column of "web" reads as
                // repetition.
                BadgeColumn::make('guard_name')
                    ->label(__('wire-module-users::messages.guard'))
                    ->color(Color::Gray)
                    ->sortable(),

                // Chips rather than a comma-joined string: a permission name
                // contains dots and stars, and twenty of them run together into
                // one unreadable line. Capped, because a role with forty
                // permissions must not decide how tall every other row is —
                // the view page is where the whole list belongs.
                TagsColumn::make('permissions')
                    ->label(__('wire-module-users::messages.permissions'))
                    ->state(static fn (Model $record): array => self::permissionNames($record))
                    ->colorUsing(static fn (string $tag): string => str_contains($tag, '*') ? 'warning' : 'gray')
                    ->limitList(4)
                    ->wrap(),
            ])
            ->defaultSort('name');
    }

    public function form(Form $form): Form
    {
        // Asked once. A form is composed again on every live update a field
        // sends, and both the options and the grouping are a query.
        $groups = Roles::permissionGroups();

        return $form->schema([
            // Two sections rather than three fields in a column, because they
            // answer two different questions: who this role is, and what it can
            // reach. The second one is the long half.
            Section::make('identity')
                ->label(__('wire-module-users::messages.details'))
                ->icon('outline:shield-check')
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    TextInput::make('name')->label(__('wire-module-users::messages.name'))->required(),
                    TextInput::make('guard_name')
                        ->label(__('wire-module-users::messages.guard'))
                        ->helperText(__('wire-module-users::messages.guard_hint')),
                ]),

            Section::make('permissions')
                ->label(__('wire-module-users::messages.permissions'))
                ->icon('outline:key')
                ->description(Roles::hasExtendedPermissions() ? __('wire-module-users::messages.wildcard_hint') : null)
                ->schema([
                    // Checkboxes rather than a multi-select, because the two
                    // answer different questions. A multi-select shows what you
                    // have *chosen* and hides the rest, which is right for the
                    // three roles on a user; a permission list is the opposite —
                    // the question is always "what else is there", and the space
                    // is a hundred names nobody has memorised.
                    //
                    // Grouped by the resource the permission is about, so the
                    // wall of names becomes the matrix people think in — and
                    // flat where that would not help (see permissionGroups()).
                    CheckboxList::make('permissions')
                        ->label(__('wire-module-users::messages.permissions'))
                        ->hiddenLabel()
                        ->options(Roles::permissionOptions())
                        // `groups()` turns the grouped layout on by itself, and
                        // an empty map renders flat — so declining to group is
                        // the same call with nothing in it.
                        ->groups($groups)
                        ->searchable()
                        ->bulkToggleable()
                        // What is granted, above the list that hides it. With a
                        // search typed or the list scrolled, the ticks are off
                        // screen — and "what does this role actually have" is
                        // the question somebody opens the page with.
                        ->showSelected()
                        ->columns(['default' => 1, 'sm' => 2, 'xl' => 3]),
                ]),
        ])->mutateDataBeforeSave(static function (array $data): array {
            unset($data['permissions']);

            if (($data['guard_name'] ?? '') === '') {
                $data['guard_name'] = config('auth.defaults.guard', 'web');
            }

            return $data;
        });
    }

    /**
     * One role, read top to bottom: what it is, then everything it grants.
     *
     * The grants are split in two where the permission layer matches wildcards,
     * because `invoices.*` and `invoices.view` are not the same kind of fact —
     * one is a rule and the other an entry, and a single alphabetical list of
     * chips hides the rule that makes half of it redundant.
     */
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            // `make()` takes a key and `label()` the heading: passing the
            // translated string as the name sends it through `Str::headline()`,
            // which title-cases it — wrong in every language that is not
            // English.
            Section::make('identity')
                ->label(__('wire-module-users::messages.details'))
                ->icon('outline:shield-check')
                ->columns(['default' => 1, 'sm' => 2, 'xl' => 4])
                ->schema([
                    TextEntry::make('name')
                        ->label(__('wire-module-users::messages.name'))
                        ->icon('outline:identification')
                        ->weight('semibold'),

                    BadgeEntry::make('guard_name')
                        ->label(__('wire-module-users::messages.guard'))
                        ->color(Color::Gray),

                    // The count on top of the list below it: "how much does this
                    // role grant" is answered by a number, not by counting chips.
                    // Nothing is counted through a relation that would have to be
                    // queried — a read-only page must not be the thing that
                    // fatals on an installation whose related table is elsewhere.
                    TextEntry::make('permissions')
                        ->label(__('wire-module-users::messages.permissions'))
                        ->icon('outline:key')
                        ->state(static fn (Model $record): string => (string) count(self::permissionNames($record))),

                    TextEntry::make('created_at')
                        ->label(__('wire-module-users::messages.created'))
                        ->icon('outline:calendar-days')
                        ->dateTime()
                        ->placeholder('—'),
                ]),

            Section::make('permissions')
                ->label(__('wire-module-users::messages.permissions'))
                ->icon('outline:key')
                ->schema([
                    // Declared always and *shown* conditionally, through the
                    // canonical visibility concern rather than a ternary that
                    // builds a different schema: an installation on bare Spatie
                    // has no wildcard matching, so `invoices.*` there is a
                    // permission with a star in its name and nothing more.
                    ListEntry::make('wildcards')
                        ->label(__('wire-module-users::messages.wildcards'))
                        ->visible(Roles::hasExtendedPermissions())
                        ->state(static fn (Model $record): array => self::permissionNames($record, wildcards: true))
                        ->badge()
                        ->icon('outline:sparkles')
                        ->color(Color::Warning)
                        ->placeholder(__('wire-module-users::messages.no_wildcards')),

                    ListEntry::make('permissions')
                        ->label(__('wire-module-users::messages.granted'))
                        // Everything, where nothing was split off above.
                        ->state(static fn (Model $record): array => self::permissionNames(
                            $record,
                            wildcards: Roles::hasExtendedPermissions() ? false : null,
                        ))
                        ->badge()
                        ->color(Color::Info)
                        ->placeholder(__('wire-module-users::messages.no_permissions')),
                ]),
        ]);
    }

    /**
     * A role's permission names, optionally only the wildcards or only the
     * exact ones.
     *
     * Guarded rather than assumed: an application may point the module at a role
     * model of its own, and one without a `permissions()` relation is a model
     * these screens still have to render — empty, not fatal.
     *
     * @return array<int, string>
     */
    protected static function permissionNames(Model $record, ?bool $wildcards = null): array
    {
        if (! method_exists($record, 'permissions')) {
            return [];
        }

        $names = $record->permissions->pluck('name')->map(static fn (mixed $name): string => (string) $name);

        if ($wildcards !== null) {
            $names = $names->filter(static fn (string $name): bool => str_contains($name, '*') === $wildcards);
        }

        return $names->values()->all();
    }
}
