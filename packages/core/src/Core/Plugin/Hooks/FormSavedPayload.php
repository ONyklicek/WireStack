<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use NyonCode\WireCore\Core\Plugin\Contracts\FormConfigContract;
use NyonCode\WireCore\Core\Plugin\Contracts\HasHookTarget;
use NyonCode\WireCore\Core\Plugin\HookTarget;

/**
 * Typed payload for the 'form.saved' hook.
 *
 * Dispatched after the record has been persisted.
 * Plugins can observe the saved record (e.g. for audit logging).
 */
final class FormSavedPayload implements HasHookTarget
{
    /**
     * @param  FormConfigContract  $config  The immutable form configuration
     * @param  mixed  $record  The persisted record (Model instance or custom return)
     * @param  HookTarget|null  $target  Which component this came from, for scoped callbacks
     */
    public function __construct(
        public readonly FormConfigContract $config,
        public readonly mixed $record,
        public readonly ?HookTarget $target = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'config' => $this->config,
            'record' => $this->record,
        ];
    }

    public function hookTarget(): ?HookTarget
    {
        return $this->target;
    }
}
