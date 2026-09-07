<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Pages;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireCore\Actions\EditAction;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Actions\ViewAction;
use NyonCode\WireModuleUsers\Resources\RoleResource;
use NyonCode\WirePanels\Resources\Pages\ListPage;
use NyonCode\WireTable\Table;

/**
 * The role list, present only where roles are.
 *
 * View, edit and delete. The view page exists because a role's permission list
 * outgrows the control that edits it: forty chips inside a multi-select are for
 * picking, not for reading, and "what can this role actually do" deserves an
 * answer that is laid out rather than scrolled ({@see ViewRole}).
 *
 * Deleting a role takes its assignments with it, through the foreign keys the
 * permission package's own migration declares. The confirmation the preset
 * carries is the only guard, and deliberately: which roles an installation
 * considers load-bearing is the application's to know, not this module's to
 * guess.
 */
class ListRoles extends ListPage
{
    protected static ?string $resource = RoleResource::class;

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
                    ->label(__('wire-panels::messages.create', ['label' => RoleResource::label()]))
                    ->icon('plus')
                    ->url($create)
                    ->permission($this->pagePermission('create')),
            ])))
            ->actions([
                ViewAction::make()
                    ->url(fn (Model $record): ?string => $this->pageUrl('view', $record))
                    ->permission($this->pagePermission('view'))
                    ->visible(fn (Model $record): bool => $this->pageUrl('view', $record) !== null),

                EditAction::make()
                    ->url(fn (Model $record): ?string => $this->pageUrl('edit', $record))
                    ->permission($this->pagePermission('edit'))
                    ->visible(fn (Model $record): bool => $this->pageUrl('edit', $record) !== null),

                DeleteAction::make()
                    ->permission($this->pagePermission('edit'))
                    ->successNotification(__('wire-module-users::messages.role_deleted'))
                    ->action(fn (Model $record) => $record->delete()),
            ]);
    }
}
