<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use NyonCode\WireForms\Forms\Config\FormConfig;

/**
 * Typed payload for the 'form.saved' hook.
 *
 * Dispatched after the record has been persisted.
 * Plugins can observe the saved record (e.g. for audit logging).
 */
final class FormSavedPayload
{
    /**
     * @param  FormConfig  $config  The immutable form configuration
     * @param  mixed  $record  The persisted record (Model instance or custom return)
     */
    public function __construct(
        public readonly FormConfig $config,
        public readonly mixed $record,
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
}
