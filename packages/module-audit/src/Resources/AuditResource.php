<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAudit\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\RoutePage;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Infolists\Components\ChangesEntry;
use NyonCode\WireCore\Infolists\Components\KeyValueEntry;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireModuleAudit\Pages\ListAuditEntries;
use NyonCode\WireModuleAudit\Pages\ViewAuditEntry;
use NyonCode\WireModuleAudit\Support\Actors;
use NyonCode\WireModuleAudit\Support\AuditedRecords;
use NyonCode\WireModuleAudit\Support\AuditEvents;
use NyonCode\WireModuleAudit\Support\AuditLog;
use NyonCode\WireModuleAudit\Support\Changes;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Table;

/**
 * The trail wire-core already records, as something you can read.
 *
 * The engine, the table and the pruning command have shipped in `wire-core` for
 * versions — `HasAuditable` fires the events, `AuditLogger` writes them. What no
 * package shipped was a screen, so every application that turned auditing on had
 * a table it could only reach with SQL.
 *
 * **Read-only, deliberately.** An audit entry that can be edited is not an audit
 * entry. There is no form and no create page; the resource declares an index and
 * a view, and nothing else.
 *
 * **Everything a column shows comes from a named owner**, and that is what this
 * screen is: {@see AuditEvents} for what happened, {@see Actors} for who did it,
 * {@see AuditedRecords} for what it happened to, {@see Changes} for what moved.
 * Each of them exists because the answer is needed in more than one place — the
 * event's label in a badge, a filter and an entry; the actor's name in a column,
 * a filter and a detail — and a screen that answered each twice would eventually
 * answer them differently.
 */
class AuditResource implements DescribesResource, ProvidesNavigation, ProvidesPages, ProvidesResourceInfolist, ProvidesResourceTable
{
    use DescribesRecords;

    public static function key(): string
    {
        return 'audit-log';
    }

    public static function modelClass(): ?string
    {
        return AuditLog::model();
    }

    public static function label(): string
    {
        return __('wire-module-audit::messages.entry');
    }

    public static function pluralLabel(): string
    {
        return __('wire-module-audit::messages.entries');
    }

    /**
     * Two pages, and an ability in front of them when the application named one.
     *
     * Ungated by default, like every other module here — a permission this
     * package invented would lock the screen out of every installation that has
     * no such ability, which is a worse first impression than an open one. Where
     * `wire-module-audit.permission` is set, the declaration guards the route
     * (`ResourceRoutes` turns it into `can:` middleware) and hides the buttons
     * that lead to it, from the one statement.
     *
     * @return array<string, class-string|RoutePage>
     */
    public static function pages(): array
    {
        return [
            'index' => self::guard(ListAuditEntries::class),
            'view' => self::guard(ViewAuditEntry::class),
        ];
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make(fn (): string => __('wire-module-audit::messages.entries'))
            ->group((string) config('wire-module-audit.navigation.group', 'system'))
            ->icon((string) config('wire-module-audit.navigation.icon', 'outline:clipboard-document-list'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                BadgeColumn::make('event')
                    ->label(__('wire-module-audit::messages.event'))
                    // The state is the label rather than the stored string, so
                    // `bulk_action` reads as one — and the colour map is keyed
                    // the same way, from the same owner.
                    ->state(static fn (Model $record): string => AuditEvents::label((string) $record->event))
                    ->colors(AuditEvents::colors())
                    ->sortable(),

                TextColumn::make('auditable_type')
                    ->label(__('wire-module-audit::messages.record'))
                    ->state(static fn (Model $record): string => AuditedRecords::label(
                        $record->auditable_type,
                        $record->auditable_id,
                    ))
                    // Searching still runs against the stored column, which is
                    // what makes typing "Invoice" find `App\Models\Invoice`.
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user_id')
                    ->label(__('wire-module-audit::messages.actor'))
                    ->state(static fn (Model $record): string => Actors::label($record))
                    // Sortable but not searchable: sorting groups a person's
                    // work together, while searching would run against the key
                    // rather than the name nobody can see it under.
                    ->sortable(),

                TextColumn::make('changed')
                    ->label(__('wire-module-audit::messages.changed'))
                    ->state(static fn (Model $record): string => implode(', ', Changes::fields($record))),

                TextColumn::make('created_at')
                    ->label(__('wire-module-audit::messages.when'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters($this->filters())
            // One query for every actor on the page instead of one per row. The
            // relation is only touched where the user model is really there:
            // core's default points at `App\Models\User`, which a package
            // installation may not have at all.
            ->modifyQueryUsing(static fn (Builder $query): Builder => Actors::available()
                ? $query->with('user')
                : $query)
            ->defaultSort('created_at', 'desc')
            ->emptyState(
                __('wire-module-audit::messages.empty_heading'),
                __('wire-module-audit::messages.empty_description'),
                'outline:clipboard-document-list',
            );
    }

    /**
     * The four questions this log is opened with — and only three of them where
     * an actor cannot be named at all.
     *
     * A list built statement by statement rather than an array with a `null` in
     * it: the conditional filter is a decision, and a decision reads better as
     * one line than as a ternary nobody can see the end of.
     *
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        $filters = [
            SelectFilter::make('event')
                ->label(__('wire-module-audit::messages.event'))
                ->options(AuditEvents::options()),

            SelectFilter::make('auditable_type')
                ->label(__('wire-module-audit::messages.record'))
                // A Closure, so the log is asked when the filter is drawn rather
                // than every time this table is composed — a table is composed
                // again on every search keystroke.
                ->options(static fn (): array => AuditLog::recordTypes()),
        ];

        if (Actors::available()) {
            $filters[] = SelectFilter::make('user_id')
                ->label(__('wire-module-audit::messages.actor'))
                ->options(static fn (): array => Actors::options());
        }

        // The question an audit log is nearly always opened with: what happened
        // between these two days.
        $filters[] = DateFilter::make('created_at')
            ->label(__('wire-module-audit::messages.when'))
            ->range();

        return $filters;
    }

    /**
     * One entry, read top to bottom: what happened, what moved, where from.
     *
     * Three sections rather than six entries in a row, because they answer three
     * different questions and a reader arrives with one of them. The flat list
     * put the IP and the user agent between the actor and the diff, so the thing
     * the page exists for was below the fold on a wide change.
     *
     * The context section is collapsed. It is the half nobody opens the page
     * for — and the half that is longest, because an application may put
     * anything in the event's metadata.
     */
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            // `make()` takes a key and `label()` the heading, the same split
            // NavigationGroup uses. Passing the translated string as the name
            // sends it through `Str::headline()`, which title-cases it — "Co Se
            // Stalo" in Czech, which is not how the language works.
            Section::make('what-happened')
                ->label(__('wire-module-audit::messages.what_happened'))
                ->icon('outline:information-circle')
                ->columns(2)
                ->schema([
                    TextEntry::make('event')
                        ->label(__('wire-module-audit::messages.event'))
                        ->state(static fn (Model $record): string => AuditEvents::label((string) $record->event))
                        ->badge()
                        ->color(static fn (Model $record): string => AuditEvents::color((string) $record->event)),

                    TextEntry::make('created_at')->label(__('wire-module-audit::messages.when')),

                    TextEntry::make('user_id')
                        ->label(__('wire-module-audit::messages.actor'))
                        ->state(static fn (Model $record): string => Actors::label($record)),

                    TextEntry::make('auditable_type')
                        ->label(__('wire-module-audit::messages.record'))
                        ->state(static fn (Model $record): string => AuditedRecords::label(
                            $record->auditable_type,
                            $record->auditable_id,
                        )),
                ]),

            // The before and the after side by side, one row per field that
            // moved — one table with one set of headings, not a card per field
            // repeating them. It is core's `ChangesEntry`, the same component
            // the trail slide-over draws, so a diff reads the same in both
            // places and a boolean is `true` in both.
            Section::make('changes')
                ->label(__('wire-module-audit::messages.changes'))
                ->icon('outline:arrows-right-left')
                ->schema([
                    ChangesEntry::make('changes')
                        ->hiddenLabel()
                        ->state(static fn (Model $record): array => Changes::rows($record))
                        ->placeholder(__('wire-module-audit::messages.no_changes')),
                ]),

            // Where it came from: the IP and the user agent `AuditLogger`
            // records, plus anything the application put in the event. Promised
            // by the docs since the module shipped and never actually rendered.
            Section::make('context')
                ->label(__('wire-module-audit::messages.context'))
                ->icon('outline:globe-alt')
                ->collapsed()
                ->schema([
                    KeyValueEntry::make('metadata')
                        ->hiddenLabel()
                        ->keyLabel(__('wire-module-audit::messages.context_key'))
                        ->valueLabel(__('wire-module-audit::messages.context_value'))
                        ->placeholder(__('wire-module-audit::messages.no_context')),
                ]),
        ]);
    }

    /** The ability guarding both pages, or null when the application named none. */
    public static function permission(): ?string
    {
        $permission = config('wire-module-audit.permission');

        return is_string($permission) && $permission !== '' ? $permission : null;
    }

    /**
     * A page as declared: the bare component, or one behind the configured
     * ability.
     *
     * @param  class-string  $component
     * @return class-string|RoutePage
     */
    private static function guard(string $component): string|RoutePage
    {
        $permission = self::permission();

        return $permission === null
            ? $component
            : RoutePage::make($component)->permission($permission);
    }
}
