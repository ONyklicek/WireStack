<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\Drivers;

use Livewire\LivewireManager;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Notification;

/**
 * Notification driver decorator that auto-resolves the currently rendering
 * Livewire component.
 *
 * Call-sites no longer need to thread `$this` through every
 * NotificationManager::send() call: when no component is passed, this driver
 * asks Livewire for the active component via {@see LivewireManager::current()}
 * and delegates the actual delivery to the wrapped driver (SessionDriver by
 * default, keeping the historical session-flash + dispatch behaviour).
 *
 * Register globally (usually the built-in default):
 *   NotificationManager::setDefaultDriver(
 *       new CurrentComponentDriver(new LivewireEventDriver('toast'))
 *   );
 */
final class CurrentComponentDriver implements NotificationDriver
{
    public function __construct(
        private readonly NotificationDriver $driver = new SessionDriver,
    ) {}

    public function send(Notification $notification, mixed $livewireComponent = null): void
    {
        $this->driver->send(
            $notification,
            $livewireComponent ?? $this->resolveCurrentComponent(),
        );
    }

    /**
     * Resolve the component Livewire is currently rendering, if any.
     */
    private function resolveCurrentComponent(): mixed
    {
        // Livewire is a hard dependency of wire-core, so the manager is always
        // bound; current() returns the active component or a falsy stack tail.
        $component = app(LivewireManager::class)->current();

        return is_object($component) ? $component : null;
    }
}
