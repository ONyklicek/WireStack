<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Config;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Internal builder that accumulates fluent calls and produces a FormConfig.
 *
 * @internal This class is not part of the public API.
 */
final class ConfigBuilder
{
    /** @var array<int, mixed> */
    private array $schema = [];

    private ?string $statePath = null;

    private string|Model|null $model = null;

    private ?Closure $mutateDataBeforeSave = null;

    private ?Closure $beforeSave = null;

    private ?Closure $afterSave = null;

    private ?Closure $using = null;

    private string|Closure|null $successMessage = '__default__';

    /** @var array<string, string> */
    private array $validationMessages = [];

    private bool $isDisabled = false;

    /**
     * @param  array<int, mixed>  $components
     */
    public function schema(array $components): self
    {
        $this->schema = $components;

        return $this;
    }

    public function statePath(string $path): self
    {
        $this->statePath = $path;

        return $this;
    }

    public function model(string|Model|null $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function mutateDataBeforeSave(?Closure $callback): self
    {
        $this->mutateDataBeforeSave = $callback;

        return $this;
    }

    public function beforeSave(?Closure $callback): self
    {
        $this->beforeSave = $callback;

        return $this;
    }

    public function afterSave(?Closure $callback): self
    {
        $this->afterSave = $callback;

        return $this;
    }

    public function using(?Closure $callback): self
    {
        $this->using = $callback;

        return $this;
    }

    public function successMessage(string|Closure|null $message): self
    {
        $this->successMessage = $message;

        return $this;
    }

    /**
     * @param  array<string, string>  $messages
     */
    public function validationMessages(array $messages): self
    {
        $this->validationMessages = $messages;

        return $this;
    }

    public function disabled(bool $condition = true): self
    {
        $this->isDisabled = $condition;

        return $this;
    }

    public function build(): FormConfig
    {
        return new FormConfig(
            schema: $this->schema,
            statePath: $this->statePath,
            model: $this->model,
            mutateDataBeforeSave: $this->mutateDataBeforeSave,
            beforeSave: $this->beforeSave,
            afterSave: $this->afterSave,
            using: $this->using,
            successMessage: $this->successMessage,
            validationMessages: $this->validationMessages,
            isDisabled: $this->isDisabled,
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    public function getStatePath(): ?string
    {
        return $this->statePath;
    }

    public function getModel(): string|Model|null
    {
        return $this->model;
    }

    public function isDisabled(): bool
    {
        return $this->isDisabled;
    }
}
