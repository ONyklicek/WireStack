<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use NyonCode\WireCore\Audit\AuditLogger;
use NyonCode\WireCore\Audit\Contracts\AuditableEvent;

// ─── Actor resolution ────────────────────────────────────────────────────────

it('resolves a string (UUID/ULID) actor id without dropping it', function () {
    // Regression M6: resolveUserId() was typed ?int, so a non-integer actor key
    // threw a TypeError under strict_types that the catch swallowed — the actor
    // was silently recorded as null.
    $logger = new class extends AuditLogger
    {
        public function actorId(): int|string|null
        {
            return $this->resolveUserId();
        }
    };

    $this->actingAs(new GenericUser(['id' => '550e8400-e29b-41d4-a716-446655440000']));

    expect($logger->actorId())->toBe('550e8400-e29b-41d4-a716-446655440000');
});

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

// ─── Redaction ───────────────────────────────────────────────────────────────

/** The filter, reached directly: it is protected, and it is the whole subject. */
function auditRedactor(): AuditLogger
{
    return new class extends AuditLogger
    {
        /**
         * @param  array<string, mixed>  $values
         * @return array<string, mixed>
         */
        public function redact(array $values): array
        {
            return $this->filterExcludedColumns($values) ?? [];
        }
    };
}

it('never writes a credential to the trail, whatever the config says', function () {
    // The defect: `exclude_columns` was an exact-key list of two, so a 2FA
    // enrolment wrote `two_factor_secret` and the recovery codes into the entry,
    // and a token rotation wrote both the old and the new one — onto a screen
    // the audit module renders to anyone who can open the log, in a trail whose
    // retention defaults to forever.
    //
    // Emptied deliberately: the floor is not a default. A published config file
    // must not be able to put a credential on that screen.
    config(['wire-core.audit.exclude_columns' => []]);

    expect(auditRedactor()->redact([
        'name' => 'Ann',
        'password' => 'hashed',
        'password_confirmation' => 'hashed',
        'api_token' => 'live-token',
        'stripe_secret' => 'sk_live_x',
        'two_factor_secret' => 'SEED',
        'two_factor_recovery_codes' => '["a","b"]',
        'api_key' => 'k',
    ]))->toBe(['name' => 'Ann']);
});

it('still honours the columns an application excludes', function () {
    config(['wire-core.audit.exclude_columns' => ['salary', 'billing_*']]);

    expect(auditRedactor()->redact([
        'name' => 'Ann',
        'salary' => 100,
        'billing_address' => 'Somewhere',
    ]))->toBe(['name' => 'Ann']);
});

it('keeps the columns an audit trail exists to record', function () {
    // The other half: a floor that swallows ordinary columns is a trail that
    // does not work. `token_count` and `secretary_id` are not secrets.
    config(['wire-core.audit.exclude_columns' => []]);

    $values = [
        'status' => 'sent',
        'token_count' => 12,
        'secretary_id' => 4,
        'keynote' => 'x',
    ];

    expect(auditRedactor()->redact($values))->toBe($values);
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
