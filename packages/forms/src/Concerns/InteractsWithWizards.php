<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use Illuminate\Validation\ValidationException;
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Wizard;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * Livewire endpoint backing per-step validation of the standalone
 * {@see Wizard} layout.
 *
 * The wizard's "Next" button calls {@see validateWizardStep()} with the current
 * (zero-based, visible-steps) index; only when the step's fields pass does the
 * client advance. Errors are surfaced on the host's error bag — the Livewire
 * call itself must not abort, so the client can stay on the step — and stale
 * entries for the step's fields are cleared first, so fixed failures don't
 * linger.
 *
 * Works for both standalone form hosts ({@see WithForms})
 * and table action modals, via the same host-form enumeration as field actions.
 *
 * @phpstan-require-extends Component
 */
trait InteractsWithWizards
{
    /**
     * Validate the fields of one wizard step. Returns true when the step passes
     * (or no matching wizard/step exists — nothing to gate on), false when
     * validation failed and errors were written to the error bag.
     */
    public function validateWizardStep(int $stepIndex, ?string $wizard = null): bool
    {
        foreach ($this->fieldActionForms() as $form) {
            if (! $form instanceof Form || ! $form->hasWizard($wizard)) {
                continue;
            }

            $this->resetErrorBagForPaths($form->getWizardStepFieldPaths($stepIndex, $wizard));

            try {
                $form->validateWizardStep($stepIndex, $wizard);

                return true;
            } catch (ValidationException $e) {
                // Livewire only fills the error bag when the exception reaches its
                // own handler; swallowing it here means writing the errors ourselves.
                foreach ($e->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $this->addError($key, $message);
                    }
                }

                return false;
            }
        }

        return true;
    }

    /**
     * Clear error-bag entries owned by the given field paths. A trailing `.*`
     * wildcard (repeater subtrees) matches by prefix.
     *
     * @param  array<int, string>  $paths
     */
    private function resetErrorBagForPaths(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        $exact = [];
        $prefixes = [];

        foreach ($paths as $path) {
            if (str_ends_with($path, '.*')) {
                $prefixes[] = substr($path, 0, -1); // keep the trailing dot
            } else {
                $exact[] = $path;
            }
        }

        $stale = [];

        foreach (array_keys($this->getErrorBag()->messages()) as $key) {
            if (in_array($key, $exact, true)) {
                $stale[] = $key;

                continue;
            }

            foreach ($prefixes as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    $stale[] = $key;

                    break;
                }
            }
        }

        if ($stale !== []) {
            $this->resetErrorBag($stale);
        }
    }

    /**
     * Provided by {@see InteractsWithFieldActions}, which every wizard-capable
     * host also composes. Declared abstract so this trait resolves in isolation.
     *
     * @return array<int, Form>
     */
    abstract protected function fieldActionForms(): array;
}
