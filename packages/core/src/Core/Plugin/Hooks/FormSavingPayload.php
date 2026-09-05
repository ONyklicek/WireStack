<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use NyonCode\WireCore\Core\Plugin\Contracts\FormConfigContract;
use NyonCode\WireCore\Core\Plugin\Contracts\HasHookTarget;
use NyonCode\WireCore\Core\Plugin\HookTarget;

/**
 * Typed payload for the 'form.saving' hook.
 *
 * Dispatched before form data is persisted.
 * Plugins can modify the data before it reaches the database.
 */
final class FormSavingPayload implements HasHookTarget
{
    /**
     * @param  FormConfigContract  $config  The immutable form configuration
     * @param  array<string, mixed>  $data  The validated form data (modifiable)
     * @param  HookTarget|null  $target  Which component this came from, for scoped callbacks
     */
    public function __construct(
        public readonly FormConfigContract $config,
        public array $data,
        public readonly ?HookTarget $target = null,
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

    public function hookTarget(): ?HookTarget
    {
        return $this->target;
    }
}
