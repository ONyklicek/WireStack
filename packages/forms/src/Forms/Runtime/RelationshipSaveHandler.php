<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Runtime;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireForms\Components\Repeater;

/**
 * Handles saving relationship data from repeaters (owned-rows cascade and
 * BelongsToMany pivot sync).
 *
 * Called after the parent model is saved to sync children. An owned relation
 * (`HasMany` / `MorphMany`, both `HasOneOrMany`) creates/updates/deletes its rows
 * — a morph relation's `create()` sets the morph type/id automatically; a
 * `BelongsToMany` repeater syncs the pivot (attach/detach/update pivot columns),
 * keyed by the related model's key.
 *
 * A repeater with {@see Repeater::orderColumn()} also writes each row's position
 * into that column (a pivot column, for a `BelongsToMany`) — without it a drag
 * lives only as long as the page, since the rows come back in the database's
 * order.
 *
 * @internal
 */
final class RelationshipSaveHandler
{
    /**
     * Save all relationship fields for the given record.
     *
     * @param  array<int, Component|LayoutComponent>  $schema
     * @param  array<string, mixed>  $data
     */
    public function save(Model $record, array $schema, array $data): void
    {
        foreach ($this->findRepeaters($schema) as $repeater) {
            $this->saveRepeater($record, $repeater, $data);
        }
    }

    /**
     * @param  array<int, Component|LayoutComponent>  $schema
     * @return array<int, Repeater>
     */
    private function findRepeaters(array $schema): array
    {
        $repeaters = [];

        foreach ($schema as $component) {
            if ($component instanceof Repeater && $component->getRelationship() !== null) {
                $repeaters[] = $component;
            } elseif ($component instanceof LayoutComponent) {
                $repeaters = array_merge($repeaters, $this->findRepeaters($component->getSchema()));
            }
        }

        return $repeaters;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveRepeater(Model $record, Repeater $repeater, array $data): void
    {
        $relationName = $repeater->getRelationship();
        if ($relationName === null || ! method_exists($record, $relationName)) {
            return;
        }

        $relation = $record->{$relationName}();

        $fieldName = $repeater->getName();
        $items = $data[$fieldName] ?? [];
        if (! is_array($items)) {
            return;
        }

        $mutator = $repeater->getMutateRelationshipDataBeforeSaveUsing();
        $orderColumn = $repeater->getOrderColumn();

        if ($relation instanceof BelongsToMany) {
            $this->syncBelongsToMany($record, $relationName, $items, $mutator, $orderColumn);

            return;
        }

        // HasMany and MorphMany (both HasOneOrMany) share the same create/update/
        // delete cascade — a morph create() sets the *_type/*_id on its own.
        if (! $relation instanceof HasOneOrMany) {
            return;
        }

        $relatedModel = $relation->getRelated();
        $primaryKey = $relatedModel->getKeyName();

        // Track existing IDs for deletion
        $existingIds = $relation->pluck($primaryKey)->all();
        $keptIds = [];

        $position = 0;

        foreach ($items as $itemData) {
            if ($mutator) {
                $itemData = $mutator($itemData);
            }

            // After the mutator, so a mutator that reorders or renames fields
            // cannot overwrite the position — and before the key is read, so the
            // column travels with both the create and the update branch.
            // Counted here rather than taken from the array key: a repeater whose
            // rows were removed and re-added can hand us a non-list, and the
            // stored order has to be 0..n-1 whatever the keys say.
            if ($orderColumn !== null && is_array($itemData)) {
                $itemData[$orderColumn] = $position;
            }

            $position++;

            $id = $itemData[$primaryKey] ?? null;

            // Never mass-assign the primary key from client state (it is used only
            // to match existing rows, not to write into create/update payloads).
            unset($itemData[$primaryKey]);

            if ($id && in_array($id, $existingIds)) {
                // Fetch the model and fill+save so casts, mutators and model events
                // fire — a query-builder `->update()` runs none of them. A plain
                // 'array'/'json' cast survives it by accident (the query grammar
                // json_encodes an array binding on its own), which is why that half
                // reads as safe; a cast whose set() transforms the value —
                // 'encrypted', an enum, any CastsAttributes — and every model event
                // do not. This mirrors the create branch (casts via the model) and
                // the delete branch (loads models to fire events / respect
                // SoftDeletes); both halves are pinned by their own tests.
                $existing = $record->{$relationName}()->find($id);
                if ($existing !== null) {
                    $existing->fill($itemData)->save();
                    $keptIds[] = $id;
                }
            } else {
                // Create new — use fresh query
                $newRecord = $record->{$relationName}()->create($itemData);
                $keptIds[] = $newRecord->getKey();
            }
        }

        // Delete removed items individually to fire model events and respect SoftDeletes
        $toDelete = array_diff($existingIds, $keptIds);
        if (! empty($toDelete)) {
            $record->{$relationName}()->whereIn($primaryKey, $toDelete)->get()->each->delete();
        }
    }

    /**
     * Sync a BelongsToMany from repeater rows. Each row carries the related
     * model's key (under its key name) plus any pivot columns; the whole set is
     * `sync()`ed so unlisted rows detach, listed ones attach, and pivot columns
     * update — in one statement.
     *
     * @param  array<int, mixed>  $items
     */
    private function syncBelongsToMany(Model $record, string $relationName, array $items, ?\Closure $mutator, ?string $orderColumn = null): void
    {
        $relatedKeyName = $record->{$relationName}()->getRelated()->getKeyName();

        /** @var array<int|string, array<string, mixed>> $sync */
        $sync = [];
        $position = 0;

        foreach ($items as $itemData) {
            if (! is_array($itemData)) {
                continue;
            }

            if ($mutator) {
                $itemData = $mutator($itemData);
            }

            $relatedId = $itemData[$relatedKeyName] ?? null;

            if ($relatedId === null || $relatedId === '') {
                continue;
            }

            // A pivot column here, not a column on the related model: everything
            // but the related key is pivot data, and the order of a many-to-many
            // belongs to the link, not to the thing linked.
            //
            // Numbered after the skip above, not before it: a half-filled row
            // that names no related model is not synced, and letting it consume a
            // position would leave a hole in the stored order.
            if ($orderColumn !== null) {
                $itemData[$orderColumn] = $position;
                $position++;
            }

            // Everything but the related key is pivot data.
            unset($itemData[$relatedKeyName]);

            $sync[$relatedId] = $itemData;
        }

        $record->{$relationName}()->sync($sync);
    }
}
