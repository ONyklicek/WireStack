<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Runtime;

use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use NyonCode\WireCore\Core\Hydration\CastResolver;
use NyonCode\WireCore\Core\Hydration\Dehydrator;
use NyonCode\WireCore\Core\Hydration\ValueTransformer;
use NyonCode\WireCore\Core\Plugin\Hooks\FormSavedPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\FormSavingPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireForms\Components\Repeater;
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
            throw new InvalidArgumentException('Form has no model configured. Call ->model() or ->using() before save().');
        }

        // Relationship-backed repeaters (e.g. Repeater::make('children')->relationship('children'))
        // hold has-many rows, not parent columns. They are persisted separately by
        // RelationshipSaveHandler after the parent save, so strip them here to avoid
        // dehydrating a non-existent column onto the parent.
        foreach ($this->relationshipRepeaterNames() as $name) {
            unset($data[$name]);
        }

        $dehydrator = new Dehydrator(new ValueTransformer, new CastResolver);

        // Update mode
        if ($model instanceof Model) {
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
        $names = [];

        foreach ($schema as $component) {
            if ($component instanceof Repeater && $component->getRelationship() !== null) {
                $names[] = $component->getName();
            } elseif ($component instanceof LayoutComponent) {
                $names = array_merge($names, $this->collectRelationshipRepeaterNames($component->getSchema()));
            }
        }

        return $names;
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
