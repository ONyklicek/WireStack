<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Panels\Components;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Infolists\Components\Entry;
use NyonCode\WireCore\Panels\Concerns\WithEditablePanel;

/**
 * Base class for panel entries that write back to the bound record.
 *
 * Editable entries are the write-side counterpart to read-only infolist
 * {@see Entry}s (which they extend, so both mix freely in one panel schema):
 * they render an inline control (switch, checkbox, select, text input) that
 * commits directly to the Eloquent record through the shared `wireEditableCell`
 * Alpine engine and the host {@see WithEditablePanel}
 * trait — the same optimistic-UI + optimistic-lock contract as editable table
 * columns, so both surfaces share one write path.
 *
 * Being an {@see EditableEntry} is the server-side write whitelist: the host
 * only accepts a write for an entry that is declared editable in the schema, so
 * a read-only entry name (or any arbitrary attribute) can never be persisted.
 *
 * Editing requires a Model-bound record (it needs a primary key and, for
 * optimistic locking, an `updated_at` version). When bound to a plain array the
 * control renders disabled.
 *
 * @phpstan-consistent-constructor
 */
abstract class EditableEntry extends Entry
{
    /** Client-facing control type consumed by the entry view / Alpine engine. */
    protected string $editableType = 'text';

    protected bool|Closure $disabled = false;

    /** @var array<int, mixed> */
    protected array $rules = [];

    protected ?string $permission = null;

    protected ?Closure $saveUsing = null;

    protected ?Closure $afterStateUpdated = null;

    public function getEditableType(): string
    {
        return $this->editableType;
    }

    /** Disable inline editing; a Closure receives ($state, $record) per row. */
    public function disabled(bool|Closure $disabled = true): static
    {
        $this->disabled = $disabled;

        return $this;
    }

    /**
     * Whether the control is disabled for the currently bound record. A Closure
     * receives ($state, $record) via the shared entry evaluator.
     */
    public function isDisabled(): bool
    {
        return (bool) $this->evaluateForState($this->disabled);
    }

    /**
     * Server-side edit guard consulted by the host before a write.
     *
     * The client-side disabled state is only cosmetic (a forged request could
     * still reach the host), so a per-record disabled entry must be rejected
     * here too. The record is rebound first so a Closure disabled() rule sees the
     * freshly locked record.
     */
    public function canEdit(Model $record): bool
    {
        $this->record($record);

        return ! $this->isDisabled();
    }

    /**
     * Validation rules applied before the value is persisted.
     *
     * @param  array<int, mixed>  $rules
     */
    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * @return array<int, mixed>
     */
    public function getEditableRules(): array
    {
        return $this->rules;
    }

    /** Require this ability before the inline edit is allowed to write. */
    public function permission(?string $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    /**
     * Persist the value with a custom callback instead of a direct attribute
     * write. Receives ($record, $value, $entry).
     */
    public function saveUsing(Closure $callback): static
    {
        $this->saveUsing = $callback;

        return $this;
    }

    public function getSaveCallback(): ?Closure
    {
        return $this->saveUsing;
    }

    /**
     * Run a side effect after a successful write. Receives ($record, $value).
     */
    public function afterStateUpdated(Closure $callback): static
    {
        $this->afterStateUpdated = $callback;

        return $this;
    }

    public function getAfterStateUpdatedCallback(): ?Closure
    {
        return $this->afterStateUpdated;
    }

    /**
     * Normalize the raw client value before it is persisted. Override per
     * control type (e.g. cast a toggle to a boolean).
     */
    public function formatForSave(mixed $value): mixed
    {
        return $value;
    }

    /**
     * The Model this entry writes to, or null when bound to a plain array.
     */
    public function getRecordModel(): ?Model
    {
        return $this->record instanceof Model ? $this->record : null;
    }

    public function getRecordKey(): ?string
    {
        $model = $this->getRecordModel();

        return $model !== null ? (string) $model->getKey() : null;
    }

    /**
     * Whether the entry can render an editable control (needs a Model record).
     */
    public function isEditable(): bool
    {
        return $this->getRecordModel() !== null;
    }

    /**
     * Optimistic-lock version for the bound record (updated_at timestamp, or '0'
     * when the model is not timestamped / not a model).
     */
    public function getRecordVersion(): string
    {
        $model = $this->getRecordModel();
        $updatedAt = $model?->getAttribute('updated_at');

        return $updatedAt instanceof \DateTimeInterface ? (string) $updatedAt->getTimestamp() : '0';
    }
}
