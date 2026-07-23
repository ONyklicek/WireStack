<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Services\CellEditPipeline;

/**
 * The result of one inline-edit attempt.
 *
 * {@see CellEditPipeline} is a service, and a service does not return an error
 * shape (see the Exceptions section of AI_CODING_STANDARD.md). The
 * `['success' => false, …]` array is nonetheless the published wire contract of
 * `updateTableCell()` — `wireEditableCell.commit()` in dropdown.js reads
 * `success`, `message`, `errors`, `conflict`, `currentValue`, `currentVersion`
 * and `version` by name. This object reconciles the two: the pipeline returns
 * the outcome typed, and the Livewire host converts it with {@see toArray()} at
 * the boundary that owns the contract.
 *
 * `$record`, `$savedValue` and `$oldValue` exist only so a caller can run
 * post-transaction side effects (afterStateUpdated, the CellUpdated event) once
 * the lock is released. They are deliberately absent from `toArray()` — the
 * array previously carried them and had to `unset()` them again before
 * answering the client, which is the kind of thing a type should prevent.
 */
final readonly class CellEditOutcome
{
    /**
     * @param  array<int, string>  $errors
     */
    private function __construct(
        public bool $success,
        public ?string $message = null,
        public array $errors = [],
        public bool $conflict = false,
        public string $currentValue = '',
        public ?string $currentVersion = null,
        public ?string $version = null,
        public ?Model $record = null,
        public mixed $savedValue = null,
        public mixed $oldValue = null,
    ) {}

    /** The value was written; `$version` is the record's new optimistic-lock stamp. */
    public static function saved(Model $record, ?string $version, mixed $savedValue, mixed $oldValue): self
    {
        return new self(
            success: true,
            version: $version,
            record: $record,
            savedValue: $savedValue,
            oldValue: $oldValue,
        );
    }

    /** Refused before any write — unknown column, not editable, not permitted, no record. */
    public static function rejected(string $message): self
    {
        return new self(success: false, message: $message);
    }

    /**
     * The value did not pass validation.
     *
     * @param  array<int, string>  $errors
     */
    public static function invalid(string $message, array $errors): self
    {
        return new self(success: false, message: $message, errors: $errors);
    }

    /**
     * The record moved since the client read it. Carries the current value so the
     * cell can reconcile itself inline without a table re-render.
     */
    public static function conflicted(string $message, mixed $currentValue, ?string $currentVersion): self
    {
        return new self(
            success: false,
            message: $message,
            conflict: true,
            currentValue: (string) ($currentValue ?? ''),
            currentVersion: $currentVersion,
        );
    }

    /**
     * The wire shape read by `wireEditableCell`. Optional keys stay absent rather
     * than null, matching what `updateTableCell()` has always returned.
     *
     * @return array{success: bool, message?: string, errors?: array<int, string>, conflict?: bool, currentValue?: string, currentVersion?: string|null, version?: string|null}
     */
    public function toArray(): array
    {
        if ($this->success) {
            return ['success' => true, 'version' => $this->version];
        }

        $payload = ['success' => false, 'message' => $this->message];

        if ($this->errors !== []) {
            $payload['errors'] = $this->errors;
        }

        if ($this->conflict) {
            $payload['conflict'] = true;
            $payload['currentValue'] = $this->currentValue;
            $payload['currentVersion'] = $this->currentVersion;
        }

        return $payload;
    }
}
