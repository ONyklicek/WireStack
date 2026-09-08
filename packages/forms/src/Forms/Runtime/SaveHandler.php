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
use NyonCode\WireCore\Core\Plugin\HookDispatch;
use NyonCode\WireCore\Core\Plugin\Hooks\FormSavedPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\FormSavingPayload;
use NyonCode\WireCore\Core\Plugin\HookTarget;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Contracts\CanBeDehydrated;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireForms\Components\Field;
use NyonCode\WireForms\Components\MorphToSelect;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Contracts\SavesAfterRecord;
use NyonCode\WireForms\Exceptions\FormConfigurationException;
use NyonCode\WireForms\Forms\Config\FormConfig;

/**
 * Handles the save lifecycle: validate → mutate → beforeSave → persist → afterSave → notify.
 *
 * @internal This class is not part of the public API.
 */
final class SaveHandler
{
    private readonly StateDehydrator $dehydrator;

    public function __construct(
        private readonly FormConfig $config,
        private readonly FormRuntime $runtime,
    ) {
        $this->dehydrator = new StateDehydrator;
    }

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
        //
        // One of the seven legacy names, so both dispatchers run — see
        // `PluginManager::callbackExpectsArray()`. The guard is HookDispatch's,
        // which is also where the `hasHook()` short-circuit comes from: this used
        // to build both payloads whenever a manager was bound at all.
        $manager = HookDispatch::manager(Hook::FormSaving);

        if ($manager !== null) {
            $target = $this->hookTarget();

            $payload = $manager->runHook('form.saving', [
                'config' => $this->config,
                'data' => $data,
            ], $target);
            $hookData = $payload['data'] ?? $data;
            $data = is_array($hookData) ? $hookData : $data;

            $typedPayload = $manager->runTypedHook(
                'form.saving',
                new FormSavingPayload($this->config, $data, $target),
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

        // 6b. Fields that persist themselves against the saved record.
        //
        // After the record, never before: a new one has no key until it is
        // written, and a pivot row needs that key. Given the raw state rather
        // than the validated data, because these fields carry no rule of their
        // own and validate() drops what it was not asked about.
        foreach ($this->afterRecordFields() as $field) {
            $field->saveAfterRecord($record, $this->runtime->getStateManager()->getState()[$field->getName()] ?? null);
        }

        // 7. afterSave hook (void)
        if ($this->config->afterSave) {
            ($this->config->afterSave)($record);
        }

        // 8. Plugin hook: form.saved (observation)
        $manager = HookDispatch::manager(Hook::FormSaved);

        if ($manager !== null) {
            $target = $this->hookTarget();

            $manager->runHook('form.saved', [
                'config' => $this->config,
                'record' => $record,
            ], $target);

            $manager->runTypedHook(
                'form.saved',
                new FormSavedPayload($this->config, $record, $target),
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

        // Whatever the schema says is not a column on this record: a relationship
        // repeater's has-many rows (written by RelationshipSaveHandler after the
        // parent), a relationship-bound Tags field, a morph select, a password
        // confirmation, anything an owner switched off with dehydrated(false).
        // Left in place, each would dehydrate a column that does not exist and
        // fatal. Only the payload is stripped — $data keeps every key for the
        // relationship pass and the after-record fields further down.
        foreach ($this->nonDehydratedNames() as $name) {
            unset($data[$name]);
        }

        // A MorphToSelect is dropped by the sweep above with everything else that
        // is not a column; what is particular to it is the replacement. The two
        // real columns it manages (`{name}_type` / `{name}_id`) are read from raw
        // state: those sub-fields carry no validation rule of their own, so
        // validate() dropped them from $data.
        $rawState = $this->runtime->getStateManager()->getState();
        foreach ($this->morphToSelectFields() as $field) {
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
     * Apply every field's own dehydration to the data about to be persisted.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function dehydrateFields(array $data): array
    {
        return $this->dehydrator->dehydrate(
            $data,
            $this->config->schema,
            $this->config->model instanceof Model ? $this->config->model : null,
        );
    }

    /**
     * Names of schema components whose state must not be written to the record.
     *
     * Asked of the schema rather than enumerated here: a component says whether
     * it is a column, and the ones that are not say so for their own reasons —
     * a relation name, a morph pair, an owner's `dehydrated(false)`.
     * Both hosts of the payload — a field and a repeater — answer the
     * {@see CanBeDehydrated} contract, so the question is asked without a type
     * check.
     * {@see SavesAfterRecord} is the one implication left in this layer: a field
     * that writes itself against the saved record is by definition not a column
     * on it, so the contract answers for it and no field has to declare both.
     *
     * @return array<int, string>
     */
    private function nonDehydratedNames(): array
    {
        $names = [];

        foreach ($this->dehydrator->payloadComponents($this->config->schema) as $component) {
            if ($component instanceof SavesAfterRecord || ! $component->isDehydrated()) {
                $names[] = $component->getName();
            }
        }

        return $names;
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
     * Every field in the schema that saves itself after the record.
     *
     * @return array<int, Field&SavesAfterRecord>
     */
    private function afterRecordFields(): array
    {
        return $this->collectAfterRecordFields($this->config->schema);
    }

    /**
     * @param  array<int, mixed>  $schema
     * @return array<int, Field&SavesAfterRecord>
     */
    private function collectAfterRecordFields(array $schema): array
    {
        $fields = [];

        foreach ($schema as $component) {
            // Both, because the name comes from the field and the behaviour
            // from the contract: something that implements one without the other
            // is not a form field and has no name to remove from the data.
            if ($component instanceof Field && $component instanceof SavesAfterRecord) {
                $fields[] = $component;
            } elseif ($component instanceof LayoutComponent) {
                $fields = array_merge($fields, $this->collectAfterRecordFields($component->getSchema()));
            }
        }

        return $fields;
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

    /**
     * Where a save hook's callbacks are being run.
     *
     * The host is asked of the runtime rather than held here: a form may be
     * saved from a Livewire component, from a modal action or from nothing at
     * all, and only the state manager knows which. A form with no host is still
     * addressable by its model.
     */
    private function hookTarget(): HookTarget
    {
        return HookTarget::for(
            'form',
            $this->runtime->getStateManager()->getLivewire(),
            $this->config->model,
        );
    }
}
