<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Pages\Concerns;

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\Contracts\ResolvesActionClick;
use NyonCode\WireCore\Actions\Support\MountActionClickResolver;
use NyonCode\WireForms\Concerns\WithActions;

/**
 * The action engine for a page that has no table to borrow one from.
 *
 * `WithActions` is the whole of it — modals, slide-overs, wizards, confirmation,
 * halts — and this adds the two things a page means by "its actions":
 *
 * - the header actions are found by name, before anything `actions()` declares,
 *   so a page's own extra actions (a card's buttons on a board) and its header
 *   share one engine without either having to list the other;
 * - a record page mounts every action against its record, so a click carries
 *   the same `$record` the button was drawn with. Without it the button would ask
 *   `visible(fn ($record))` about the record and the click would ask about none.
 *
 * The view renders `wire-core::actions.modal-host` once; that is where whatever
 * these actions open appears.
 */
trait HostsPageActions
{
    use InteractsWithHeaderActions;
    use WithActions {
        mountAction as protected mountActionOnHost;
        resolveAction as protected resolveDeclaredAction;
    }

    /**
     * Open the action the command palette sent this page here to open.
     *
     * The palette cannot host a modal — it sits in a module that may not import
     * the Actions one — so an action that has to ask something is answered by
     * navigating to the page that owns the record, with the action named in the
     * query string. This is the far end of that.
     *
     * A mount hook, so it runs after the page's own `mount()`: Livewire calls
     * that first, and by now a record page has resolved its record and the edit
     * page has seeded its form, which is what `canExecute($record)` needs to
     * answer about anything at all. An unknown name resolves nothing and leaves
     * the user on the page they asked for.
     */
    public function mountHostsPageActions(): void
    {
        $name = request()->query('action');

        if (is_string($name) && $name !== '') {
            $this->mountAction($name);
        }
    }

    /**
     * Mount an action, against the page's record when it has one and the caller
     * named none.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function mountAction(string $name, array $arguments = []): void
    {
        if (! array_key_exists('record', $arguments) && ($record = $this->headerActionRecord()) !== null) {
            $arguments['record'] = $record;
        }

        $this->mountActionOnHost($name, $arguments);
    }

    /** A header action first, then whatever the page's `actions()` declares. */
    protected function resolveAction(string $name): ?Action
    {
        $found = $this->findPageHeaderAction($name);

        return $found instanceof Action ? $found : $this->resolveDeclaredAction($name);
    }

    protected function headerActionClick(): ResolvesActionClick
    {
        return new MountActionClickResolver;
    }
}
