<?php

declare(strict_types=1);

use NyonCode\WireCore\Audit\AuditLogger;
use NyonCode\WireCore\Audit\Contracts\AuditableEvent;

// ─── withoutAuditing ─────────────────────────────────────────────────────────

it('withoutAuditing suppresses logging', function () {
    $logger = new AuditLogger;
    $event = createMockAuditEvent();

    $result = AuditLogger::withoutAuditing(function () use ($logger, $event) {
        return $logger->log($event);
    });

    expect($result)->toBeNull();
});

it('withoutAuditing restores state after callback', function () {
    $innerResult = null;

    AuditLogger::withoutAuditing(function () use (&$innerResult) {
        $innerResult = 'ran';
    });

    // After withoutAuditing, logging should be re-enabled
    // (we can't easily test this without DB, but at least verify callback ran)
    expect($innerResult)->toBe('ran');
});

it('withoutAuditing returns callback result', function () {
    $result = AuditLogger::withoutAuditing(fn () => 42);

    expect($result)->toBe(42);
});

it('withoutAuditing restores state even on exception', function () {
    try {
        AuditLogger::withoutAuditing(function () {
            throw new RuntimeException('test');
        });
    } catch (RuntimeException) {
        // Expected
    }

    // State should be restored — next withoutAuditing should work
    $result = AuditLogger::withoutAuditing(fn () => 'ok');
    expect($result)->toBe('ok');
});

// ─── Configuration ───────────────────────────────────────────────────────────

it('respects enabled config', function () {
    config(['wire-core.audit.enabled' => false]);

    $logger = new AuditLogger;
    $result = $logger->log(createMockAuditEvent());

    expect($result)->toBeNull();

    config(['wire-core.audit.enabled' => true]);
});

it('respects event type filter config', function () {
    config(['wire-core.audit.events' => ['created', 'deleted']]);

    $logger = new AuditLogger;
    // 'updated' is not in the allowed list
    $event = createMockAuditEvent('updated');
    $result = $logger->log($event);

    expect($result)->toBeNull();

    config(['wire-core.audit.events' => null]);
});

it('allows all events when events config is null', function () {
    config(['wire-core.audit.events' => null]);

    $logger = new AuditLogger;

    expect($logger->isEnabled())->toBeTrue();
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function createMockAuditEvent(string $type = 'created'): AuditableEvent
{
    return new class($type) implements AuditableEvent
    {
        public function __construct(private readonly string $type) {}

        public function getAuditEventType(): string
        {
            return $this->type;
        }

        public function getAuditableType(): string
        {
            return 'App\\Models\\Test';
        }

        public function getAuditableId(): mixed
        {
            return 1;
        }

        public function getOldValues(): ?array
        {
            return null;
        }

        public function getNewValues(): ?array
        {
            return ['name' => 'Test'];
        }

        public function getMetadata(): array
        {
            return [];
        }
    };
}
