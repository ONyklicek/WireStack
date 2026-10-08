<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use NyonCode\WireCore\Foundation\Routing\ClientNavigation;

/**
 * A link's own say in whether it is followed with `wire:navigate`.
 *
 * For anything that renders a url — an action, a table column, a table's
 * record link. Unset, the link follows `wire-core.navigate`; the decision
 * itself is {@see ClientNavigation}'s, and this only carries the preference to
 * it.
 */
trait InteractsWithClientNavigation
{
    protected ?bool $navigate = null;

    /** Follow this link with `wire:navigate` (true) or as a full page load (false), whatever `wire-core.navigate` says. */
    public function navigate(?bool $condition = true): static
    {
        $this->navigate = $condition;

        return $this;
    }

    /** The link's own preference, or null when it follows `wire-core.navigate`. */
    public function getNavigatePreference(): ?bool
    {
        return $this->navigate;
    }

    /** Whether a link to `$url` from this object is followed with `wire:navigate`. */
    public function shouldNavigateTo(?string $url, bool $openInNewTab = false): bool
    {
        return ! $openInNewTab
            && app(ClientNavigation::class)->shouldNavigate($url, $this->navigate);
    }
}
