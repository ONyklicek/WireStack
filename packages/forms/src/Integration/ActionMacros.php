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

        BaseAction::macro('form', function (array $schema): ActionMacros {
            /** @var BaseAction&object{formSchema?: array<int, mixed>} $this */
            $this->formSchema = $schema; // @phpstan-ignore-line

            return $this; // @phpstan-ignore-line
        });

        BaseAction::macro('fillFormUsing', function (Closure $fn): ActionMacros {
            /** @var BaseAction $this */
            $this->fillFormUsing = $fn;

            return $this; // @phpstan-ignore-line
        });

        BaseAction::macro('formValidation', function (array $rules, array $messages = []): ActionMacros {
            /** @var BaseAction $this */
            $this->formValidation = compact('rules', 'messages'); // @phpstan-ignore-line

            return $this; // @phpstan-ignore-line
        });

        /** @phpstan-ignore-next-line */
        BaseAction::macro('getFormSchema', function (): array {
            /** @var BaseAction $this */
            return $this->formSchema ?? []; // @phpstan-ignore-line
        });

        /** @phpstan-ignore-next-line */
        BaseAction::macro('hasFormSchema', function (): bool {
            /** @var BaseAction $this */
            return ! empty($this->formSchema); // @phpstan-ignore-line
        });
    }
}
