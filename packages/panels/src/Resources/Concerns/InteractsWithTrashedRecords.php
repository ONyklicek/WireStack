<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NyonCode\WireCore\Actions\ForceDeleteAction;
use NyonCode\WireCore\Actions\ForceDeleteBulkAction;
use NyonCode\WireCore\Actions\RestoreAction;
use NyonCode\WireCore\Actions\RestoreBulkAction;
use NyonCode\WirePanels\Resources\Contracts\ManagesTrashedRecords;
use NyonCode\WirePanels\Resources\Pages\ListPage;
use NyonCode\WirePanels\Resources\Support\RecordAbility;
use NyonCode\WirePanels\Resources\Support\TrashedRecords;
use NyonCode\WireTable\Filters\TrashedFilter;
use NyonCode\WireTable\Table;

/**
 * The list's half of {@see ManagesTrashedRecords}.
 *
 * Added to the table the resource composed, after it — so the resource's own
 * filters and actions stay first and these join them:
 *
 * - a `TrashedFilter` named `trashed`, which also makes the table find a
 *   trashed row by its key when its action is clicked;
 * - *Restore* and *Force delete* on a row, drawn only on a trashed one;
 * - both as bulk actions, which act only on the trashed records of a
 *   selection — a force delete swept over live rows would not be a restore's
 *   mirror image, it would be data loss.
 *
 * Each is asked of the model's policy (`restore`, `forceDelete`), or of the
 * record's edit page when there is no policy, per record.
 *
 * @phpstan-require-extends ListPage
 */
trait InteractsWithTrashedRecords
{
    protected function applyTrashedRecords(Table $table): Table
    {
        if (! TrashedRecords::managedBy(static::$resource)) {
            return $table;
        }

        return $table
            ->filters([...$table->getFilters(), TrashedFilter::make('trashed')])
            ->actions([
                ...$table->getActions(),
                RestoreAction::make()
                    ->visible(fn (?Model $record): bool => $this->isTrashed($record))
                    ->authorizeUsing(fn ($user, ?Model $record): bool => $this->mayActOnTrashed('restore', $record))
                    ->action(fn (Model $record) => $this->restoreIfTrashed($record)),
                ForceDeleteAction::make()
                    ->visible(fn (?Model $record): bool => $this->isTrashed($record))
                    ->authorizeUsing(fn ($user, ?Model $record): bool => $this->mayActOnTrashed('forceDelete', $record))
                    ->action(fn (Model $record) => $this->isTrashed($record) && $record->forceDelete()),
            ])
            ->bulkActions([
                ...$table->getBulkActions(),
                RestoreBulkAction::make()
                    ->action(fn (Collection $records) => $this->eachTrashed($records, 'restore', fn (Model $record) => $this->restoreIfTrashed($record))),
                ForceDeleteBulkAction::make()
                    ->action(fn (Collection $records) => $this->eachTrashed($records, 'forceDelete', fn (Model $record) => $record->forceDelete())),
            ]);
    }

    private function isTrashed(?Model $record): bool
    {
        return $record !== null && method_exists($record, 'trashed') && $record->trashed();
    }

    /** Restore a record the trash holds; anything else is left as it is. */
    private function restoreIfTrashed(Model $record): void
    {
        if ($this->isTrashed($record) && method_exists($record, 'restore')) {
            $record->restore();
        }
    }

    /** The policy's `$ability`, or the record's edit page when the model has no policy. */
    private function mayActOnTrashed(string $ability, ?Model $record): bool
    {
        return $record !== null
            && app(RecordAbility::class)->allows($ability, $record, $this->reachablePageUrl('edit', $record) !== null);
    }

    /**
     * Run `$do` on every trashed record of the selection this user may do it to.
     *
     * @param  Collection<int, Model>  $records
     * @param  callable(Model): mixed  $do
     */
    private function eachTrashed(Collection $records, string $ability, callable $do): void
    {
        foreach ($records as $record) {
            if ($this->isTrashed($record) && $this->mayActOnTrashed($ability, $record)) {
                $do($record);
            }
        }
    }
}
