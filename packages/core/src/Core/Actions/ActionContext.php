<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Context object for action execution.
 *
 * Mutable container that accumulates data during pipeline processing.
 */
final class ActionContext
{
    /**
     * @param  Model|null  $record  Single record for the action
     * @param  Collection|null  $records  Collection of records for bulk actions
     * @param  Collection<int, Model>|null  $records
     * @param  array<string, mixed>  $formData  Form data submitted with the action
     * @param  array<string, mixed>  $arguments  Additional arguments accumulated during pipeline
     * @param  string|null  $actionName  Name of the action being executed
     */
    public function __construct(
        public ?Model $record = null,
        public ?Collection $records = null,
        public array $formData = [],
        public array $arguments = [],
        public ?string $actionName = null,
    ) {}

    /**
     * Determine if this is a bulk action.
     */
    public function isBulk(): bool
    {
        return $this->records !== null && $this->records->isNotEmpty();
    }

    /**
     * Get the single record.
     */
    public function getRecord(): ?Model
    {
        return $this->record;
    }

    /**
     * Get the collection of records.
     *
     * @return Collection<int, Model>
     */
    public function getRecords(): Collection
    {
        return $this->records ?? new Collection;
    }

    /**
     * Get the form data.
     *
     * @return array<string, mixed>
     */
    public function getFormData(): array
    {
        return $this->formData;
    }

    /**
     * Get a value from arguments.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->arguments[$key] ?? $default;
    }

    /**
     * Set a value in arguments.
     */
    public function set(string $key, mixed $value): void
    {
        $this->arguments[$key] = $value;
    }
}
