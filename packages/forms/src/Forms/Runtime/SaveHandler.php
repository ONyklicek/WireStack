<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Runtime;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use NyonCode\WireCore\Core\Hydration\CastResolver;
use NyonCode\WireCore\Core\Hydration\Dehydrator;
use NyonCode\WireCore\Core\Hydration\ValueTransformer;
use NyonCode\WireCore\Core\Plugin\Hooks\FormSavedPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\FormSavingPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Contracts\DehydratesState;
use NyonCode\WireForms\Components\Field;
use NyonCode\WireForms\Components\MorphToSelect;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\Tags;
use NyonCode\WireForms\Exceptions\FormConfigurationException;
use NyonCode\WireForms\Forms\Config\FormConfig;

/**
 * Handles the save lifecycle: validate → mutate → beforeSave → persist → afterSave → notify.
 *
 * @internal This class is not part of the public API.
 */
final class SaveHandler
{
    public function __construct(
        private readonly FormConfig $config,
        private readonly FormRuntime $runtime,
    ) {}

    public function save(): mixed
    {
        // 1. Validate
        $data = $this->runtime->validate();

        // Livewire's validate() returns the full component state keyed by statePath
        // (e.g. ['data' => ['name' => '...']]).  Unwrap so persist() gets flat attributes.
        if ($this->config->statePath && array_key_exists($this->config->statePath, $data)) {
            $data = (array) $data[$this->config->statePath];
        }

        // 2. Mutate data
        if ($this->config->mutateDataBeforeSave) {
            $data = ($this->config->mutateDataBeforeSave)($data);
            if ($data === null) {
                return null;
            }
        }

        // 2.5 Let each field shape its own value before it is persisted — the
        // write-path counterpart of getStateType() (ADR 0021). This is how a
        // FileUpload moves validated temporary uploads to permanent storage (so
        // an abandoned form leaves no orphan) and how a date field applies its
        // storage format and timezone.
        $data = $this->dehydrateFields($data);

        // 3. Plugin hook: form.saving (can modify data)
        if (app()->bound(PluginManager::class)) {
            $manager = app(PluginManager::class);

            $payload = $manager->runHook('form.saving', [
                'config' => $this->config,
                'data' => $data,
            ]);
            $hookData = $payload['data'] ?? $data;
            $data = is_array($hookData) ? $hookData : $data;

            $typedPayload = $manager->runTypedHook(
                'form.saving',
                new FormSavingPayload($this->config, $data),
            );
            $data = $typedPayload->data;
        }

        // 4. beforeSave hook (void)
        if ($this->config->beforeSave) {
            ($this->config->beforeSave)($data);
        }

        // 5. Persist
        $record = $this->persist($data);

        // 6. Save relationships (Repeater cascade)
        if ($record instanceof Model) {
            // Restore each relationship-repeater item's primary key, dropped by
            // validate() (the key is not a schema field, so it carries no rule).
            // Without it RelationshipSaveHandler matches no existing row and
            // recreates every child on update — losing its identity and any
            // column not present in the form.
            $data = $this->restoreRelationshipRepeaterKeys($record, $data);

            $relationHandler = new RelationshipSaveHandler;
            $relationHandler->save($record, $this->config->schema, $data);
        }

        // 7. afterSave hook (void)
        if ($this->config->afterSave) {
            ($this->config->afterSave)($record);
        }

        // 8. Plugin hook: form.saved (observation)
        if (app()->bound(PluginManager::class)) {
            $manager = app(PluginManager::class);

            $manager->runHook('form.saved', [
                'config' => $this->config,
                'record' => $record,
            ]);

            $manager->runTypedHook(
                'form.saved',
                new FormSavedPayload($this->config, $record),
            );
        }

        // 9. Success notification
        $this->notifySuccess($record);

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(array $data): mixed
    {
        // Custom persistence
        if ($this->config->using) {
            return ($this->config->using)($data);
        }

        $model = $this->config->model;

        if ($model === null) {
            throw FormConfigurationException::noModel();
        }

        // Relationship-backed repeaters hold has-many rows, not parent columns;
        // they are persisted separately by RelationshipSaveHandler after the parent
        // save. A relationship-bound Tags field is likewise not a parent column
        // (its key names a relation, not an attribute). Left in place either would
        // dehydrate a non-existent column and fatal.
        foreach ([...$this->relationshipRepeaterNames(), ...$this->tagsRelationshipNames()] as $name) {
            unset($data[$name]);
        }

        // A MorphToSelect's own name is a morph relation, never a column — writing
        // it fatals. Replace it with the two real columns it manages
        // (`{name}_type` / `{name}_id`), read from raw state: those sub-fields carry
        // no validation rule of their own, so validate() dropped them from $data.
        $rawState = $this->runtime->getStateManager()->getState();
        foreach ($this->morphToSelectFields() as $field) {
            unset($data[$field->getName()]);

            foreach ([$field->getTypeColumn(), $field->getIdColumn()] as $column) {
                if (array_key_exists($column, $rawState)) {
                    $data[$column] = $rawState[$column];
                }
            }
        }

        $dehydrator = new Dehydrator(new ValueTransformer, new CastResolver);

        // Update mode
        if ($model instanceof Model) {
            $this->verifyOptimisticLock($model);

            $dehydrator->dehydrate($data, $model);
            $model->save();

            return $model;
        }

        // Create mode (model is class-string)
        $instance = new $model;
        $dehydrator->dehydrate($data, $instance);
        $instance->save();

        return $instance;
    }

    /**
     * Move any pending file uploads in the data to permanent storage and replace
     * them with their stored paths, keeping already-stored paths as-is (merge).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    /**
     * Apply every field's own dehydration to the data about to be persisted.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function dehydrateFields(array $data): array
    {
        $model = $this->config->model instanceof Model ? $this->config->model : null;

        // Top-level fields (not nested inside a repeater).
        foreach ($this->collectDehydratingFields($this->config->schema) as $field) {
            $name = $field->getName();

            if (! array_key_exists($name, $data)) {
                continue;
            }

            $data[$name] = $field->dehydrateState($data[$name], $model);
        }

        // Repeater children: a DehydratesState child (FileUpload storing its
        // upload, DateTimePicker applying format/timezone) lives under the
        // repeater key as an array of items, so the top-level pass never reaches
        // it. Without this a nested file is never moved to permanent storage and a
        // nested date keeps its raw wire value.
        foreach ($this->dehydratingRepeaters($this->config->schema) as $repeater) {
            $name = $repeater->getName();

            if (! isset($data[$name]) || ! is_array($data[$name])) {
                continue;
            }

            $childFields = $this->collectDehydratingFields($repeater->getSchema());

            foreach ($data[$name] as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                foreach ($childFields as $child) {
                    $childName = $child->getName();

                    if (array_key_exists($childName, $item)) {
                        $data[$name][$index][$childName] = $child->dehydrateState($item[$childName], $model);
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Repeaters anywhere in the schema (used to dehydrate their child fields).
     *
     * @param  array<int, mixed>  $schema
     * @return array<int, Repeater>
     */
    private function dehydratingRepeaters(array $schema): array
    {
        $repeaters = [];

        foreach ($schema as $component) {
            if ($component instanceof Repeater) {
                $repeaters[] = $component;
            } elseif ($component instanceof LayoutComponent) {
                $repeaters = array_merge($repeaters, $this->dehydratingRepeaters($component->getSchema()));
            }
        }

        return $repeaters;
    }

    /**
     * Collect every field that dehydrates its own state, traversing nested layouts.
     *
     * Only a Field carries the name that keys the data array — a layout could
     * implement the contract without one.
     *
     * @param  array<int, mixed>  $schema
     * @return array<int, Field&DehydratesState>
     */
    private function collectDehydratingFields(array $schema): array
    {
        $fields = [];

        foreach ($schema as $component) {
            if ($component instanceof DehydratesState && $component instanceof Field) {
                $fields[] = $component;
            } elseif ($component instanceof LayoutComponent && ! $component instanceof Repeater) {
                // Repeaters are handled per-item by dehydratingRepeaters(); their
                // children must not be flattened into the top-level key match.
                $fields = array_merge($fields, $this->collectDehydratingFields($component->getSchema()));
            }
        }

        return $fields;
    }

    /**
     * Collect the field names of all relationship-backed repeaters in the schema,
     * traversing nested layout components.
     *
     * @return array<int, string>
     */
    private function relationshipRepeaterNames(): array
    {
        return $this->collectRelationshipRepeaterNames($this->config->schema);
    }

    /**
     * @param  array<int, mixed>  $schema
     * @return array<int, string>
     */
    private function collectRelationshipRepeaterNames(array $schema): array
    {
        return array_map(
            static fn (Repeater $repeater): string => $repeater->getName(),
            $this->collectRelationshipRepeaterFields($schema),
        );
    }

    /**
     * @param  array<int, mixed>  $schema
     * @return array<int, Repeater>
     */
    private function collectRelationshipRepeaterFields(array $schema): array
    {
        $fields = [];

        foreach ($schema as $component) {
            if ($component instanceof Repeater && $component->getRelationship() !== null) {
                $fields[] = $component;
            } elseif ($component instanceof LayoutComponent) {
                $fields = array_merge($fields, $this->collectRelationshipRepeaterFields($component->getSchema()));
            }
        }

        return $fields;
    }

    /**
     * Re-attach each relationship-repeater item's primary key from raw state. The
     * key is not a schema field, so validate() drops it; RelationshipSaveHandler
     * needs it to update an existing child in place instead of recreating it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function restoreRelationshipRepeaterKeys(Model $record, array $data): array
    {
        $rawState = $this->runtime->getStateManager()->getState();

        foreach ($this->collectRelationshipRepeaterFields($this->config->schema) as $repeater) {
            $name = $repeater->getName();
            $relationName = $repeater->getRelationship();

            if ($relationName === null || ! method_exists($record, $relationName)) {
                continue;
            }
            if (! isset($data[$name]) || ! is_array($data[$name])) {
                continue;
            }

            $relation = $record->{$relationName}();
            if (! $relation instanceof HasOneOrMany && ! $relation instanceof BelongsToMany) {
                continue;
            }

            $keyName = $relation->getRelated()->getKeyName();
            /** @var array<int|string, mixed> $rawItems */
            $rawItems = is_array($rawState[$name] ?? null) ? $rawState[$name] : [];

            foreach ($data[$name] as $index => $item) {
                if (! is_array($item) || array_key_exists($keyName, $item)) {
                    continue;
                }

                $rawKey = data_get($rawItems, "{$index}.{$keyName}");
                if ($rawKey !== null && $rawKey !== '') {
                    $data[$name][$index][$keyName] = $rawKey;
                }
            }
        }

        return $data;
    }

    /**
     * Names of relationship-bound Tags fields anywhere in the schema. Their key
     * names a relation (not a column), so it must be stripped before dehydration
     * to avoid a "no such column" fatal. A plain (column-backed) Tags field keeps
     * its array value.
     *
     * @return array<int, string>
     */
    private function tagsRelationshipNames(): array
    {
        return $this->collectTagsRelationshipNames($this->config->schema);
    }

    /**
     * @param  array<int, mixed>  $schema
     * @return array<int, string>
     */
    private function collectTagsRelationshipNames(array $schema): array
    {
        $names = [];

        foreach ($schema as $component) {
            if ($component instanceof Tags && $component->getRelationship() !== null) {
                $names[] = $component->getName();
            } elseif ($component instanceof LayoutComponent) {
                $names = array_merge($names, $this->collectTagsRelationshipNames($component->getSchema()));
            }
        }

        return $names;
    }

    /**
     * MorphToSelect fields anywhere in the schema. Their own key is a morph
     * relation, not a column, and their `{name}_type` / `{name}_id` sub-fields
     * carry no validation rule — so the save payload needs both rewriting.
     *
     * @return array<int, MorphToSelect>
     */
    private function morphToSelectFields(): array
    {
        return $this->collectMorphToSelectFields($this->config->schema);
    }

    /**
     * @param  array<int, mixed>  $schema
     * @return array<int, MorphToSelect>
     */
    private function collectMorphToSelectFields(array $schema): array
    {
        $fields = [];

        foreach ($schema as $component) {
            if ($component instanceof MorphToSelect) {
                $fields[] = $component;
            } elseif ($component instanceof LayoutComponent) {
                $fields = array_merge($fields, $this->collectMorphToSelectFields($component->getSchema()));
            }
        }

        return $fields;
    }

    /**
     * Optimistic locking guard (opt-in via Form::optimisticLock()).
     *
     * Compares the baseline lock value captured at fill time (carried in form
     * state) against the authoritative current database value. A mismatch means
     * the record was changed — or deleted — by someone else since the form was
     * opened, so the save is aborted before it can overwrite that change.
     *
     * @throws StaleModelException
     */
    private function verifyOptimisticLock(Model $model): void
    {
        $column = $this->config->optimisticLockColumn;

        if ($column === null || ! $model->exists) {
            return;
        }

        $state = $this->runtime->getStateManager()->getState();

        // No baseline captured (e.g. ->fill() ran before ->model()) — cannot
        // lock without a reference point, so fall through rather than block.
        if (! array_key_exists($column, $state)) {
            return;
        }

        $current = $model->newQueryWithoutScopes()
            ->whereKey($model->getKey())
            ->first()
            ?->getRawOriginal($column);

        if ((string) $state[$column] === (string) $current) {
            return;
        }

        $this->notifyConflict();

        throw new StaleModelException($model, $column);
    }

    private function notifyConflict(): void
    {
        $managerClass = 'NyonCode\\WireCore\\Notifications\\NotificationManager';

        if (! class_exists($managerClass) || ! app()->bound($managerClass)) {
            return;
        }

        $manager = app($managerClass);
        $manager::error(trans('wire-forms::messages.stale'));
    }

    private function notifySuccess(mixed $record): void
    {
        $message = $this->resolveSuccessMessage($record);

        if ($message === null) {
            return;
        }

        $managerClass = 'NyonCode\\WireCore\\Notifications\\NotificationManager';

        if (! class_exists($managerClass) || ! app()->bound($managerClass)) {
            return;
        }

        $manager = app($managerClass);
        $manager::success($message);
    }

    private function resolveSuccessMessage(mixed $record): ?string
    {
        $message = $this->config->successMessage;

        if ($message === null) {
            return null;
        }

        if ($message === '__default__') {
            return $this->config->isEditing()
                ? trans('wire-forms::messages.updated')
                : trans('wire-forms::messages.created');
        }

        if ($message instanceof Closure) {
            return ($message)($record);
        }

        return $message;
    }
}
