<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Audit;

use Illuminate\Events\Dispatcher;
use NyonCode\WireCore\Audit\Contracts\AuditableEvent;

/**
 * Laravel event subscriber that listens for all AuditableEvent implementations
 * and delegates to AuditLogger.
 *
 * Registered automatically by WireCoreServiceProvider; the AuditLogger gates on
 * `wire-core.audit.enabled`, so no events persist unless auditing is on. The
 * subscription is idempotent — an application that also registers it manually
 * (the pre-1.7.1 setup) still ends up with a single listener.
 */
class AuditEventSubscriber
{
    public function __construct(
        protected AuditLogger $logger,
    ) {}

    /**
     * Handle any AuditableEvent.
     */
    public function handleAuditableEvent(AuditableEvent $event): void
    {
        $this->logger->log($event);
    }

    /**
     * Register listeners for the subscriber.
     *
     * @return array<string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        // Idempotent: skip when *this* subscriber is already attached (e.g. an app
        // that kept its manual Event::subscribe from before the package
        // self-registered), so audit entries are never written twice.
        if ($this->isAlreadySubscribed($events)) {
            return [];
        }

        return [
            AuditableEvent::class => 'handleAuditableEvent',
        ];
    }

    /**
     * Is this subscriber already listening for AuditableEvent?
     *
     * Deliberately inspects the raw listener list instead of asking
     * `hasListeners()`: that helper also reports true for *wildcard* listeners
     * matching the name, so a single `Event::listen('*', …)` anywhere in the app
     * (Telescope's event watcher, Pulse, debugbar, a custom event log) made this
     * guard swallow the real registration and silently disable auditing.
     */
    protected function isAlreadySubscribed(Dispatcher $events): bool
    {
        $listeners = $events->getRawListeners()[AuditableEvent::class] ?? [];

        foreach ($listeners as $listener) {
            if (is_string($listener) && str_contains($listener, '@')) {
                $listener = explode('@', $listener, 2);
            }

            if (! is_array($listener) || ($listener[1] ?? null) !== 'handleAuditableEvent') {
                continue;
            }

            $target = $listener[0] ?? null;

            if ($target instanceof self || (is_string($target) && is_a($target, self::class, true))) {
                return true;
            }
        }

        return false;
    }
}
