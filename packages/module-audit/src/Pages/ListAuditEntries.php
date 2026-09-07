<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAudit\Pages;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ViewAction;
use NyonCode\WireModuleAudit\Resources\AuditResource;
use NyonCode\WireModuleAudit\Support\AuditedRecords;
use NyonCode\WirePanels\Resources\Concerns\BelongsToResource;
use NyonCode\WirePanels\Resources\Pages\ListPage;
use NyonCode\WireTable\Table;

/**
 * The trail, newest first — and the two ways out of it.
 *
 * The columns are the resource's; the links are this page's, because where a
 * record's pages live depends on the zone the list was opened in and only the
 * page knows which one that is ({@see BelongsToResource::pageUrl()}). The list
 * shipped with no row action at all, so the entry page it declared was routed
 * and unreachable from the screen in front of it.
 *
 * The second link is the one that changes what the log is worth: **open the
 * record this entry is about**. An audit row says something happened to invoice
 * seven, and the next thing anybody wants is invoice seven. Hidden rather than
 * dead where it cannot be resolved — an application that audits a model it has
 * no resource for, or routes no pages at all, gets a log with no link instead of
 * one with a broken link.
 */
class ListAuditEntries extends ListPage
{
    protected static ?string $resource = AuditResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->actions([
                ViewAction::make()
                    ->url(fn (Model $record): ?string => $this->pageUrl('view', $record))
                    ->permission($this->pagePermission('view'))
                    ->visible(fn (Model $record): bool => $this->pageUrl('view', $record) !== null),

                Action::make('auditedRecord')
                    ->label(__('wire-module-audit::messages.open_record'))
                    ->icon('outline:arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Model $record): ?string => $this->recordUrl($record))
                    ->visible(fn (Model $record): bool => $this->recordUrl($record) !== null),
            ]);
    }

    /** Where the record this entry is about can be read, in this page's zone. */
    protected function recordUrl(Model $record): ?string
    {
        return AuditedRecords::urlFor(
            $record->auditable_type,
            $record->auditable_id,
            $this->breadcrumbZone,
        );
    }
}
