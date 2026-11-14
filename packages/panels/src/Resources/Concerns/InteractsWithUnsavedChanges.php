<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;

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
     * Whether unsaved input is also kept in the browser, to be offered back
     * after a closed tab, a crash or an expired session.
     *
     * Off by default, because what is kept is the form's state in this
     * browser's `localStorage` — fine for an article, not for a form with a
     * secret in it. Turn it on per page:
     *
     *   protected function remembersDraft(): bool
     *   {
     *       return true;
     *   }
     */
    protected function remembersDraft(): bool
    {
        return false;
    }

    /**
     * The key a draft is kept under: this page, this record (or a new one),
     * this user — so two people on one computer never get each other's.
     */
    protected function draftKey(): string
    {
        // The edit page keeps its record as a key (ResolvesOneRecord); a model
        // is read by its own key, and a page without one edits something new.
        $record = property_exists($this, 'record') ? $this->record : null;
        $record = $record instanceof Model ? $record->getKey() : $record;
        $record = is_scalar($record) && $record !== '' ? (string) $record : 'new';

        return 'wire-draft:'.static::class.':'.$record.':'.(string) (auth()->id() ?? 'guest');
    }

    /**
     * What the form element's controller is configured with, or null for none.
     *
     * The state path is the page's own `data`: it is the page that declares that
     * property and binds the form to it.
     *
     * @return array{path: string, method: string, message: string, draftKey?: string}|null
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
        ] + ($this->remembersDraft() ? ['draftKey' => $this->draftKey()] : []);
    }
}
