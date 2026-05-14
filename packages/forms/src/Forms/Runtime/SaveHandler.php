<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Runtime;

use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
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

        // 2. Mutate data
        if ($this->config->mutateDataBeforeSave) {
            $data = ($this->config->mutateDataBeforeSave)($data);
            if ($data === null) {
                return null;
            }
        }

        // 3. beforeSave hook (void)
        if ($this->config->beforeSave) {
            ($this->config->beforeSave)($data);
        }

        // 4. Persist
        $record = $this->persist($data);

        // 5. afterSave hook (void)
        if ($this->config->afterSave) {
            ($this->config->afterSave)($record);
        }

        // 6. Success notification
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

        // Update mode
        if ($model instanceof Model) {
            $model->update($data);

            return $model;
        }

        // Create mode (model is class-string)
        return $model::create($data);
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
