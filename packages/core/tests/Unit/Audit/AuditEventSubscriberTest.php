<?php

declare(strict_types=1);

use Illuminate\Events\Dispatcher;
use NyonCode\WireCore\Audit\AuditEventSubscriber;
use NyonCode\WireCore\Audit\AuditLogger;
use NyonCode\WireCore\Audit\Contracts\AuditableEvent;

function auditSubscriber(): AuditEventSubscriber
{
    return new AuditEventSubscriber(Mockery::mock(AuditLogger::class));
}

it('delegates auditable events to the logger', function () {
    $event = Mockery::mock(AuditableEvent::class);

    $logger = Mockery::mock(AuditLogger::class);
    $logger->shouldReceive('log')->once()->with($event);

    (new AuditEventSubscriber($logger))->handleAuditableEvent($event);
});

it('subscribes to the auditable event', function () {
    $map = auditSubscriber()->subscribe(new Dispatcher);

    expect($map)->toBe([
        AuditableEvent::class => 'handleAuditableEvent',
    ]);
});

it('is idempotent when a listener is already registered (manual pre-1.7.1 setup)', function () {
    $dispatcher = new Dispatcher;
    $dispatcher->subscribe(auditSubscriber());

    expect(auditSubscriber()->subscribe($dispatcher))->toBe([]);
});

it('recognises its own handler registered as a class-string tuple', function () {
    $dispatcher = new Dispatcher;
    $dispatcher->listen(AuditableEvent::class, [AuditEventSubscriber::class, 'handleAuditableEvent']);

    expect(auditSubscriber()->subscribe($dispatcher))->toBe([]);
});

it('recognises its own handler registered as a Class@method string', function () {
    $dispatcher = new Dispatcher;
    $dispatcher->listen(AuditableEvent::class, AuditEventSubscriber::class.'@handleAuditableEvent');

    expect(auditSubscriber()->subscribe($dispatcher))->toBe([]);
});

it('recognises its own handler registered as an object tuple', function () {
    $dispatcher = new Dispatcher;
    $dispatcher->listen(AuditableEvent::class, [auditSubscriber(), 'handleAuditableEvent']);

    expect(auditSubscriber()->subscribe($dispatcher))->toBe([]);
});

it('recognises a subclass of itself as already subscribed', function () {
    $dispatcher = new Dispatcher;
    $dispatcher->listen(AuditableEvent::class, [AuditSubscriberSubclass::class, 'handleAuditableEvent']);

    expect(auditSubscriber()->subscribe($dispatcher))->toBe([]);
});

/**
 * Regression: the guard used to ask `hasListeners()`, which also reports true for
 * *wildcard* listeners matching the event name. A single `Event::listen('*', …)`
 * anywhere in the app — Telescope's event watcher, Pulse, debugbar, a custom event
 * log — therefore made the subscriber skip its own registration, and auditing was
 * silently dead while `getListeners()` still showed (wildcard) listeners.
 */
it('still subscribes when unrelated wildcard listeners exist', function (string $pattern) {
    $dispatcher = new Dispatcher;
    $dispatcher->listen($pattern, fn () => null);

    expect(auditSubscriber()->subscribe($dispatcher))->toBe([
        AuditableEvent::class => 'handleAuditableEvent',
    ]);
})->with([
    'catch-all' => ['*'],
    'namespace prefix' => ['NyonCode\WireCore\Audit\*'],
]);

it('still subscribes when an unrelated concrete listener exists', function () {
    $dispatcher = new Dispatcher;
    $dispatcher->listen(AuditableEvent::class, fn () => null);
    $dispatcher->listen(AuditableEvent::class, [UnrelatedAuditListener::class, 'handleAuditableEvent']);

    expect(auditSubscriber()->subscribe($dispatcher))->toBe([
        AuditableEvent::class => 'handleAuditableEvent',
    ]);
});

it('still subscribes when its class is listened to under a different method', function () {
    $dispatcher = new Dispatcher;
    $dispatcher->listen(AuditableEvent::class, [AuditEventSubscriber::class, 'somethingElse']);

    expect(auditSubscriber()->subscribe($dispatcher))->toBe([
        AuditableEvent::class => 'handleAuditableEvent',
    ]);
});

class AuditSubscriberSubclass extends AuditEventSubscriber {}

class UnrelatedAuditListener
{
    public function handleAuditableEvent(AuditableEvent $event): void {}
}
