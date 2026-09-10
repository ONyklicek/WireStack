<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Concerns;

use Livewire\Component;
use NyonCode\WireForms\Forms\WithForms;

/**
 * The half of a form page that is about *where a successful save lands*.
 *
 * Saving and going somewhere are two decisions, and only the second one differs
 * between the two form pages: a create has nowhere to stay — the record it was
 * filling in now exists, and pressing the button again would file a second one —
 * while an edit is already on its record's own page, so staying is the answer.
 * The wiring is therefore here and the destination is each page's
 * {@see getRedirectUrl()}, which is also the one method an application overrides
 * to land somewhere else:
 *
 *   protected function getRedirectUrl(mixed $record): ?string
 *   {
 *       return $this->pageUrl('index');
 *   }
 *
 * Null means stay, and it is a real answer rather than a failure: an unrouted
 * resource — one mounted by hand, or rendered inside something else — has no URL
 * to go to, and the page it is already on is still the right one.
 *
 * Persistence stays the form's. This adds nothing to the save lifecycle and
 * catches nothing it throws: a rejected schema never reaches the redirect,
 * because {@see WithForms} never returns from the save at all.
 *
 * @phpstan-require-extends Component
 */
trait RedirectsAfterSave
{
    public function save(): mixed
    {
        /** @var mixed $record */
        $record = $this->form->save();

        $url = $this->getRedirectUrl($record);

        if ($url !== null && $url !== '') {
            // `navigate`, because every other link in a panel navigates: a save
            // that reloaded the document would drop the sidebar's own state and
            // re-evaluate assets the rest of the shell keeps across pages.
            $this->redirect($url, navigate: true);
        }

        return $record;
    }

    /**
     * Where a successful save lands, or null to stay on this page.
     *
     * @param  mixed  $record  Whatever the form's save returned — a model for the
     *                         ordinary Eloquent case, and whatever `Form::using()`
     *                         answered for a resource over anything else.
     */
    abstract protected function getRedirectUrl(mixed $record): ?string;
}
