<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Pages\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Actions\Contracts\ResolvesActionClick;
use NyonCode\WirePanels\Resources\Concerns\ResolvesOneRecord;

/**
 * The actions a page draws beside its heading: declared, found by name, drawn.
 *
 * Running them is not this trait's business. A page already has an action engine
 * or it gets one — the list page has `WithTable`'s, every other page composes
 * {@see HostsPageActions} — and a second engine beside the first would be two
 * modal stacks on one component. So what differs per page is only the click:
 * {@see headerActionClick()} names the method that engine answers to, and the
 * button is `Action::render()` either way.
 */
trait InteractsWithHeaderActions
{
    /**
     * Per request, not per component: a header action closes over the page and
     * its record, and a Livewire round trip rebuilds both. Private, so the
     * snapshot never carries it.
     *
     * @var array<int, Action|ActionGroup>|null
     */
    private ?array $resolvedHeaderActions = null;

    /**
     * Declare the page's header actions. Override, and merge `parent::headerActions()`
     * to keep the page's own.
     *
     * @return array<int, Action|ActionGroup|null>
     */
    protected function headerActions(): array
    {
        return [];
    }

    /**
     * The declared actions, with the `null`s a conditional declaration leaves.
     *
     * @return array<int, Action|ActionGroup>
     */
    public function getHeaderActions(): array
    {
        return $this->resolvedHeaderActions ??= array_values(array_filter(
            $this->headerActions(),
            static fn (mixed $action): bool => $action instanceof Action || $action instanceof ActionGroup,
        ));
    }

    /**
     * The header action a click named, including one folded into a group.
     */
    protected function findPageHeaderAction(string $name): ?BaseAction
    {
        foreach ($this->getHeaderActions() as $action) {
            $candidates = $action instanceof ActionGroup ? $action->getActions() : [$action];

            foreach ($candidates as $candidate) {
                if ($candidate instanceof BaseAction && ($found = $this->matchRegisteredAction($candidate, $name)) !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * The record the header actions are about, or none.
     *
     * A record page answers with its record ({@see ResolvesOneRecord}),
     * so `visible(fn ($record) => …)` and a record-aware `authorizeUsing()` see
     * the same record on the button that they see when it is clicked. A page
     * about no record answers `null`.
     */
    abstract protected function headerActionRecord(): ?Model;

    /** The Livewire method a header action's click lands in on this page. */
    abstract protected function headerActionClick(): ResolvesActionClick;

    /**
     * Each visible header action, drawn.
     *
     * An action that may not run renders an empty string and is dropped here, so
     * a heading whose every action is withheld draws no empty toolbar beside it.
     *
     * @return array<int, HtmlString>
     */
    protected function renderedHeaderActions(): array
    {
        $record = $this->headerActionRecord();
        $click = $this->headerActionClick();

        $rendered = [];

        foreach ($this->getHeaderActions() as $action) {
            $html = $action->render($record, $click);

            if (trim($html) !== '') {
                $rendered[] = new HtmlString($html);
            }
        }

        return $rendered;
    }

    /** Provided by the page's action engine — `InteractsWithActions`. */
    abstract protected function matchRegisteredAction(BaseAction $action, string $name): ?BaseAction;
}
