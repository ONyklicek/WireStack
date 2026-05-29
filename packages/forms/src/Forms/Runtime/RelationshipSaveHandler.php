<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Runtime;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireForms\Components\Repeater;

/**
 * Handles saving relationship data (Repeater HasMany cascade save).
 *
 * Called after the parent model is saved to sync children.
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
        if (! $relation instanceof HasMany) {
            return;
        }

        $fieldName = $repeater->getName();
        $items = $data[$fieldName] ?? [];
        if (! is_array($items)) {
            return;
        }

        $mutator = $repeater->getMutateRelationshipDataBeforeSaveUsing();
        $relatedModel = $relation->getRelated();
        $primaryKey = $relatedModel->getKeyName();

        // Track existing IDs for deletion
        $existingIds = $relation->pluck($primaryKey)->all();
        $keptIds = [];

        foreach ($items as $index => $itemData) {
            if ($mutator) {
                $itemData = $mutator($itemData);
            }

            $id = $itemData[$primaryKey] ?? null;

            if ($id && in_array($id, $existingIds)) {
                // Update existing — use fresh query to avoid accumulating where clauses
                $record->{$relationName}()->where($primaryKey, $id)->update($itemData);
                $keptIds[] = $id;
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
}
