<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Concerns;

use NyonCode\WireCore\Foundation\ValueObjects\PollDirective;

/**
 * A widget refreshes itself on an interval.
 *
 * A widget polls exactly when it has an interval — that rule is this trait's,
 * and it is not the table's. The attribute itself comes from the canonical
 * {@see PollDirective}.
 */
trait HasPolling
{
    protected ?string $pollingInterval = null;

    protected bool $pollingOnlyVisible = true;

    /** Refresh the widget on an interval (e.g. "10s", "1m"); null disables polling. */
    public function pollingInterval(?string $interval): static
    {
        $this->pollingInterval = $interval;

        return $this;
    }

    public function getPollingInterval(): ?string
    {
        return $this->pollingInterval;
    }

    public function isPolling(): bool
    {
        return $this->pollingInterval !== null;
    }

    /** Poll only while the widget is scrolled into view (default true). */
    public function pollingOnlyVisible(bool $onlyVisible = true): static
    {
        $this->pollingOnlyVisible = $onlyVisible;

        return $this;
    }

    public function isPollingOnlyVisible(): bool
    {
        return $this->pollingOnlyVisible;
    }

    public function getPollingDirective(): ?string
    {
        if (! $this->isPolling()) {
            return null;
        }

        return (string) new PollDirective(
            interval: $this->pollingInterval,
            onlyVisible: $this->pollingOnlyVisible,
        );
    }
}
