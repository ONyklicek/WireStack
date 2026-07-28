<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use NyonCode\WireCore\Core\Plugin\Contracts\FormConfigContract;

/**
 * Typed payload for the 'form.saving' hook.
 *
 * Dispatched before form data is persisted.
 * Plugins can modify the data before it reaches the database.
 */
final class FormSavingPayload
{
    /**
     * @param  FormConfigContract  $config  The immutable form configuration
     * @param  array<string, mixed>  $data  The validated form data (modifiable)
     */
    public function __construct(
        public readonly FormConfigContract $config,
        public array $data,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'config' => $this->config,
            'data' => $this->data,
        ];
    }
}
