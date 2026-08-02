<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\ValueObjects;

use Stringable;

/**
 * The `wire:poll` attribute vocabulary — the one owner of how an interval, a
 * keep-alive preference and a visibility preference become the string Livewire
 * reads.
 *
 * The modifier order is the contract (`wire:poll.10s.keep-alive.visible`), and
 * it was written twice before this: once for widgets, once for the table, with
 * the widget copy silently missing keep-alive.
 *
 * **Whether polling is on at all stays with the caller.** The two surfaces do
 * not agree on that question — a widget polls when it has an interval, a table
 * polls when it was told to and may carry no interval at all — so this object
 * answers only "what does the attribute say", never "should it be emitted".
 */
final class PollDirective implements Stringable
{
    public function __construct(
        public readonly ?string $interval = null,
        public readonly bool $keepAlive = false,
        public readonly bool $onlyVisible = true,
    ) {}

    public function __toString(): string
    {
        $directive = 'wire:poll';

        if ($this->interval !== null && $this->interval !== '') {
            $directive .= '.'.$this->interval;
        }

        if ($this->keepAlive) {
            $directive .= '.keep-alive';
        }

        if ($this->onlyVisible) {
            $directive .= '.visible';
        }

        return $directive;
    }
}
