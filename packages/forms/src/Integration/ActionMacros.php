<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Integration;

use Closure;
use NyonCode\WireCore\Actions\BaseAction;

/**
 * Registers Action macros for form integration.
 *
 * Actions module in core does not know about Forms.
 * This class injects form capabilities via macros at runtime.
 */
final class ActionMacros
{
    public static function register(): void
    {
        if (! class_exists(BaseAction::class)) {
            return;
        }

        BaseAction::macro('form', function (array $schema): static {
            /** @var BaseAction $this */
            $this->formSchema = $schema;

            return $this;
        });

        BaseAction::macro('fillFormUsing', function (Closure $fn): static {
            /** @var BaseAction $this */
            $this->fillFormUsing = $fn;

            return $this;
        });

        BaseAction::macro('formValidation', function (array $rules, array $messages = []): static {
            /** @var BaseAction $this */
            $this->formValidation = compact('rules', 'messages');

            return $this;
        });

        BaseAction::macro('getFormSchema', function (): array {
            /** @var BaseAction $this */
            return $this->formSchema ?? [];
        });

        BaseAction::macro('hasFormSchema', function (): bool {
            /** @var BaseAction $this */
            return ! empty($this->formSchema);
        });
    }
}
