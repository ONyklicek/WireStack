<?php

declare(strict_types=1);

use Illuminate\Events\Dispatcher;
use NyonCode\WireCore\Audit\AuditEventSubscriber;
use NyonCode\WireCore\Audit\AuditLogger;
use NyonCode\WireCore\Audit\Contracts\AuditableEvent;

it('delegates auditable events to the logger', function () {
    $event = Mockery::mock(AuditableEvent::class);

    $logger = Mockery::mock(AuditLogger::class);
    $logger->shouldReceive('log')->once()->with($event);

    (new AuditEventSubscriber($logger))->handleAuditableEvent($event);
});

it('subscribes to the auditable event', function () {
    $subscriber = new AuditEventSubscriber(Mockery::mock(AuditLogger::class));

    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('hasListeners')->with(AuditableEvent::class)->andReturnFalse();

    $map = $subscriber->subscribe($dispatcher);

    expect($map)->toBe([
        AuditableEvent::class => 'handleAuditableEvent',
    ]);
});

it('is idempotent when a listener is already registered (manual pre-1.7.1 setup)', function () {
    $subscriber = new AuditEventSubscriber(Mockery::mock(AuditLogger::class));

    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('hasListeners')->with(AuditableEvent::class)->andReturnTrue();

    expect($subscriber->subscribe($dispatcher))->toBe([]);
});
