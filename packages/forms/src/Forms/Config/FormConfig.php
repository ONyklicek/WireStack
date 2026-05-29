<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Config;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Immutable form configuration.
 *
 * Holds all configuration set via the fluent Form API.
 * Once constructed, values cannot be changed.
 *
 * @internal This class is not part of the public API.
 */
final class FormConfig
{
    /**
     * @param  array<int, mixed>  $schema  Schema components
     * @param  array<string, string>  $validationMessages
     */
    public function __construct(
        public readonly array $schema = [],
        public readonly ?string $statePath = null,
        public readonly string|Model|null $model = null,
        public readonly ?Closure $mutateDataBeforeSave = null,
        public readonly ?Closure $beforeSave = null,
        public readonly ?Closure $afterSave = null,
        public readonly ?Closure $using = null,
        public readonly string|Closure|null $successMessage = '__default__',
        public readonly array $validationMessages = [],
        public readonly bool $isDisabled = false,
        public readonly bool $isLive = false,
    ) {}

    public function isCreating(): bool
    {
        return is_string($this->model);
    }

    public function isEditing(): bool
    {
        return $this->model instanceof Model;
    }

    public function hasModel(): bool
    {
        return $this->model !== null;
    }
}
