<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use NyonCode\WireCore\Actions\Concerns\HasBadge;

/**
 * Class HeaderAction - Enhanced with lifecycle hooks, loading state, keyboard shortcuts.
 *
 * Now extends BaseAction which provides HasDynamicProperties (Closure support
 * on label, color, icon, tooltip, size), HasLifecycle, HasModal, etc.
 *
 * @author Ondřej Nyklíček
 */
class HeaderAction extends BaseAction
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

    // Getters
    public function getUrl(): ?string
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

    public function toHtml(): string
    {
        return $this->render();
    }
}
