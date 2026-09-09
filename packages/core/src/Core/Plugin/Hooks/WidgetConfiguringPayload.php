<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use NyonCode\WireCore\Core\Plugin\Contracts\HasHookTarget;
use NyonCode\WireCore\Core\Plugin\HookTarget;

/**
 * Typed payload for the 'widget.configuring' hook.
 *
 * Dispatched on the declared list, **before keys are stamped and before
 * visibility filters it**. That ordering is the whole of what this class has to
 * get right: keys are derived from a widget's position in the unfiltered list,
 * so a widget appended after stamping would either carry no key or collide with
 * one — and a widget appended after filtering would skip its own `visible()`.
 *
 * ```php
 * $payload->widgets = [...$payload->widgets, StatsOverviewWidget::make()->stats([…])];
 * ```
 *
 * Typed only.
 */
final class WidgetConfiguringPayload implements HasHookTarget
{
    /**
     * @param  object  $host  The component the widgets are laid out on
     * @param  array<int, mixed>  $widgets  The declared widgets, in layout order (modifiable)
     * @param  HookTarget|null  $target  Which component this came from, for scoped callbacks
     */
    public function __construct(
        public readonly object $host,
        public array $widgets,
        public readonly ?HookTarget $target = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'host' => $this->host,
            'widgets' => $this->widgets,
        ];
    }

    public function hookTarget(): ?HookTarget
    {
        return $this->target;
    }
}
