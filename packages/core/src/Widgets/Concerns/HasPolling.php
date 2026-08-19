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
 *
 * The tick targets the widget rather than the component: see the expression in
 * {@see getPollingDirective()}, and {@see WithWidgets::refreshWidget()} for the
 * half that answers it.
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
            // A bare `wire:poll` is `$refresh`, which renders the whole host:
            // every other widget on the dashboard, and any table sharing the
            // page. Measured on a 12-widget grid, a tick cost 6.5 ms and 57 kB
            // to deliver one widget's 3.9 kB. With a key to address it by, the
            // tick calls the host and the response carries that widget alone.
            //
            // No key means no addressable region, so the directive stays bare
            // and the tick keeps working exactly as it did.
            expression: $this->getKey() === null
                ? null
                : "refreshWidget('".$this->getKey()."')",
        );
    }
}
