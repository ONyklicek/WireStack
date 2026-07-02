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
        // Idempotent: skip when a listener is already attached (e.g. an app that
        // kept its manual Event::subscribe from before the package self-registered),
        // so audit entries are never written twice.
        if ($events->hasListeners(AuditableEvent::class)) {
            return [];
        }

        return [
            AuditableEvent::class => 'handleAuditableEvent',
        ];
    }
}
