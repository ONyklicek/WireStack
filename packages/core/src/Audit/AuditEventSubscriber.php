<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Audit;

use Illuminate\Events\Dispatcher;
use NyonCode\WireCore\Audit\Contracts\AuditableEvent;

/**
 * Laravel event subscriber that listens for all AuditableEvent implementations
 * and delegates to AuditLogger.
 *
 * Register in a service provider:
 *   Event::subscribe(AuditEventSubscriber::class);
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
        return [
            AuditableEvent::class => 'handleAuditableEvent',
        ];
    }
}
