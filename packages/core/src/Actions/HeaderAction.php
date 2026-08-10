<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Concerns\HasBadge;
use NyonCode\WireCore\Actions\Contracts\RendersAsMenuItem;
use NyonCode\WireCore\Actions\Contracts\ResolvesActionClick;
use NyonCode\WireCore\Actions\Support\MountActionClickResolver;

/**
 * Class HeaderAction - Enhanced with lifecycle hooks, loading state, keyboard shortcuts.
 *
 * Now extends BaseAction which provides HasDynamicProperties (Closure support
 * on label, color, icon, tooltip, size), HasLifecycle, HasModal, etc.
 *
 * @author Ondřej Nyklíček
 */
class HeaderAction extends BaseAction implements RendersAsMenuItem
{
    use HasBadge;

    protected ?string $url = null;

    protected bool $openUrlInNewTab = false;

    /** Make the action navigate to a URL instead of running a callback. */
    public function url(?string $url, bool $openInNewTab = false): static
    {
        $this->url = $url;
        $this->openUrlInNewTab = $openInNewTab;

        return $this;
    }

    /**
     * A header action's URL is record-independent by definition; the optional
     * record only keeps the signature interchangeable with {@see Action::getUrl()},
     * so one shared view can render either kind of action as a menu row.
     */
    public function getUrl(?Model $record = null): ?string
    {
        return $this->url;
    }

    public function shouldOpenUrlInNewTab(): bool
    {
        return $this->openUrlInNewTab;
    }

    public function render(): string
    {
        if (! $this->canExecute()) {
            return '';
        }

        return view('wire-table::tables.actions.header-action', ['action' => $this])->render();
    }

    /**
     * Render this action as a row of a dropdown menu, through the same canonical
     * partial a row action uses — a header action folded into a group must not
     * look like a second kind of menu item.
     *
     * The host supplies the Livewire expression through the {@see ResolvesActionClick}
     * strategy (the table maps it to its record-less header methods); the button
     * view can hardcode those because wire-table owns it, but the menu row is
     * core's and stays host-agnostic.
     */
    public function renderForDropdown(?Model $record = null, ?ResolvesActionClick $click = null): string
    {
        if (! $this->canExecute()) {
            return '';
        }

        return view('wire-core::actions.dropdown-item', [
            'action' => $this,
            'record' => $record,
            'click' => $click ?? new MountActionClickResolver,
        ])->render();
    }

    public function toHtml(): string
    {
        return $this->render();
    }
}
