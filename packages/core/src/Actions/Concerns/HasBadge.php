<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use NyonCode\WireCore\Foundation\Colors\Color;

/**
 * Notification-style count badge for an action trigger.
 *
 * Shared by the header-action button and the action-group dropdown trigger. The
 * colour resolves through the canonical soft badge palette (partial
 * `wire-core::actions.partials.badge`) so every trigger badge matches every
 * other pill.
 */
trait HasBadge
{
    protected int|Closure|null $badge = null;

    protected ?string $badgeColor = null;

    /** Set a badge count on the action trigger. */
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
}
