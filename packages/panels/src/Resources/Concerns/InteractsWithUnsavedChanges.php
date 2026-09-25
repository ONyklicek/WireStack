<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Concerns;

/**
 * Whether a form page warns before its unsaved input is left behind.
 *
 * On by default for the create and edit pages: a form a person has typed into is
 * the one place in a panel where a stray click on the menu loses work. The
 * warning itself is the browser's — `wireUnsavedChanges` in the forms bundle,
 * answering a reload or a closed tab with `beforeunload` and a `wire:navigate`
 * link with a `confirm()`. This only says whether the page wants it, and on
 * which state path and save method, which are the page's to know.
 *
 * Turn it off per page:
 *
 *   protected function warnsAboutUnsavedChanges(): bool
 *   {
 *       return false;
 *   }
 */
trait InteractsWithUnsavedChanges
{
    protected function warnsAboutUnsavedChanges(): bool
    {
        return true;
    }

    /**
     * What the form element's controller is configured with, or null for none.
     *
     * The state path is the page's own `data`: it is the page that declares that
     * property and binds the form to it.
     *
     * @return array{path: string, method: string, message: string}|null
     */
    protected function unsavedChangesConfig(): ?array
    {
        if (! $this->warnsAboutUnsavedChanges()) {
            return null;
        }

        return [
            'path' => 'data',
            'method' => 'save',
            'message' => __('wire-panels::messages.unsaved_changes'),
        ];
    }
}
