<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Contracts\HasStateUpdatedCallback;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * Routes a Livewire state-update notification to the matching form field's
 * `afterStateUpdated()` callback.
 *
 * Both hosts that embed forms — standalone {@see WithForms}
 * components and table action modals — receive Livewire `updated` hooks carrying
 * the changed dot-path. This trait is the single canonical place that maps that
 * absolute path to a prepared field and fires its reactive callback, so the two
 * hosts do not each re-implement the lookup.
 *
 * @phpstan-require-extends \Livewire\Component
 */
trait DispatchesStateUpdates
{
    /**
     * @param  iterable<mixed>  $forms
     */
    protected function dispatchAfterStateUpdated(iterable $forms, string $absolutePath, mixed $old): bool
    {
        foreach ($forms as $form) {
            if (! $form instanceof Form) {
                continue;
            }

            foreach ($form->getFlatComponents() as $field) {
                if ($field instanceof Component
                    && $field instanceof HasStateUpdatedCallback
                    && $field->hasAfterStateUpdated()
                    && $field->getStatePath() === $absolutePath
                ) {
                    $field->runAfterStateUpdated($old);

                    return true;
                }
            }
        }

        return false;
    }
}
