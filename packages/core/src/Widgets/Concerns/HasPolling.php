<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Concerns;

trait HasPolling
{
    protected ?string $pollingInterval = null;

    protected bool $pollingOnlyVisible = true;

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

        $directive = 'wire:poll.'.$this->pollingInterval;

        if ($this->pollingOnlyVisible) {
            $directive .= '.visible';
        }

        return $directive;
    }
}
