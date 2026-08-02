<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use Closure;
use NyonCode\WireCore\Foundation\ValueObjects\PollDirective;
use NyonCode\WireTable\Exceptions\TableConfigurationException;
use NyonCode\WireTable\Table;

/**
 * How often a table re-reads, and under what conditions it bothers.
 *
 * Eight settings that only ever talk to each other: the interval, whether the
 * connection is kept alive, which method a tick calls, whether a hidden tab
 * keeps ticking, an optional condition, the change detector, and whether a
 * write is announced to other sessions. They lived as eight properties on
 * {@see Table} with the directive-building rule inlined
 * among them.
 *
 * Enablement is tracked separately from the interval on purpose: a table is
 * polling because it was told to poll, and {@see interval()} may still be null
 * — that is a bare `wire:poll` on Livewire's own default cadence. A widget
 * makes the opposite choice, which is why {@see PollDirective} owns the
 * attribute but neither owns the "is it on" question.
 */
final class PollingConfig
{
    private bool $enabled = false;

    private ?string $interval = null;

    private bool $keepAlive = false;

    /** 'refresh' (soft re-render) or 'reload' (full page load). */
    private string $method = 'refresh';

    private bool $onlyVisible = true;

    private ?Closure $condition = null;

    /**
     * false = always re-render, true = the built-in COUNT(*) + MAX(updated_at)
     * checksum, Closure = a custom checksum fn (Builder $query): string.
     */
    private bool|Closure $changeDetection = false;

    private bool $broadcast = false;

    /**
     * Turn polling on at this interval.
     *
     * @param  string  $interval  Livewire interval format — '750ms', '5s', '1m', '2h'
     *
     * @throws TableConfigurationException when the interval is not in that format
     */
    public function every(string $interval): self
    {
        if (! preg_match('/^\d+(ms|s|m|h)$/', $interval)) {
            throw TableConfigurationException::invalidPollInterval();
        }

        $this->enabled = true;
        $this->interval = $interval;

        return $this;
    }

    public function keepAlive(bool $keepAlive = true): self
    {
        $this->keepAlive = $keepAlive;

        return $this;
    }

    public function method(string $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function onlyVisible(bool $onlyVisible = true): self
    {
        $this->onlyVisible = $onlyVisible;

        return $this;
    }

    /** @param  Closure  $condition  receives the Livewire component, returns bool */
    public function when(Closure $condition): self
    {
        $this->condition = $condition;

        return $this;
    }

    public function detectChanges(bool|Closure $detector = true): self
    {
        $this->changeDetection = $detector;

        return $this;
    }

    public function broadcast(bool $broadcast = true): self
    {
        $this->broadcast = $broadcast;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function interval(): ?string
    {
        return $this->interval;
    }

    public function isKeepAlive(): bool
    {
        return $this->keepAlive;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function isOnlyVisible(): bool
    {
        return $this->onlyVisible;
    }

    public function getCondition(): ?Closure
    {
        return $this->condition;
    }

    public function getChangeDetection(): bool|Closure
    {
        return $this->changeDetection;
    }

    public function isBroadcasting(): bool
    {
        return $this->broadcast;
    }

    /** The `wire:poll` attribute, or null while polling is off. */
    public function directive(): ?string
    {
        if (! $this->enabled) {
            return null;
        }

        return (string) new PollDirective(
            interval: $this->interval,
            keepAlive: $this->keepAlive,
            onlyVisible: $this->onlyVisible,
        );
    }

    /**
     * The shape the table view and the Livewire host consume.
     *
     * @return array{enabled: bool, interval: string|null, keepAlive: bool, method: string, onlyVisible: bool, directive: string|null}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'interval' => $this->interval,
            'keepAlive' => $this->keepAlive,
            'method' => $this->method,
            'onlyVisible' => $this->onlyVisible,
            'directive' => $this->directive(),
        ];
    }
}
