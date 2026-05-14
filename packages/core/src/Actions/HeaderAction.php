<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use Closure;

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

    public function badgeColor(?string $color): static
    {
        $this->badgeColor = $color;

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
            ? call_user_func($this->badge)
            : $this->badge;
    }

    public function getBadgeColor(): string
    {
        return $this->badgeColor ?? 'danger';
    }

    public function hasBadge(): bool
    {
        $count = $this->getBadgeCount();

        return $count !== null && $count > 0;
    }

    public function getBadgeHtml(): string
    {
        if (! $this->hasBadge()) {
            return '';
        }

        $count = $this->getBadgeCount();
        $colorClasses = self::getBadgeColorClasses($this->getBadgeColor());

        return '<span class="absolute -top-1.5 -right-1.5 inline-flex items-center justify-center min-w-[1.1rem] h-[1.1rem] px-1 text-[10px] font-bold rounded-full '.$colorClasses.'">'
            .($count > 99 ? '99+' : $count)
            .'</span>';
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
