<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use NyonCode\WireCore\Foundation\Colors\Color;

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
    protected ?string $url = null;

    protected bool $openUrlInNewTab = false;

    // Badge
    protected int|Closure|null $badge = null;

    protected ?string $badgeColor = null;

    /** Make the action navigate to a URL instead of running a callback. */
    public function url(?string $url, bool $openInNewTab = false): static
    {
        $this->url = $url;
        $this->openUrlInNewTab = $openInNewTab;

        return $this;
    }

    /**
     * Set a badge count on the action button.
     */
    public function badge(int|Closure|null $count): static
    {
        $this->badge = $count;

        return $this;
    }

    /** Set the badge color. */
    public function badgeColor(string|Color|null $color): static
    {
        $this->badgeColor = $color instanceof Color ? $color->value : $color;

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

    public function getBadgeCount(): ?int
    {
        if ($this->badge === null) {
            return null;
        }

        return $this->badge instanceof Closure
            ? ($this->badge)()
            : $this->badge;
    }

    public function getBadgeColor(): string
    {
        return $this->badgeColor ?? Color::Danger->value;
    }

    public function hasBadge(): bool
    {
        $count = $this->getBadgeCount();

        return $count !== null && $count > 0;
    }

    public function getBadgeHtml(): Htmlable
    {
        if (! $this->hasBadge()) {
            return new HtmlString('');
        }

        return new HtmlString(view('wire-core::actions.partials.badge', [
            'count' => $this->getBadgeCount(),
            'color' => $this->getBadgeColor(),
        ])->render());
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
