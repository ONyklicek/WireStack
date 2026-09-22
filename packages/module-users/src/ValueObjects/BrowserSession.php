<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * One row of the `sessions` table, as the browser-sessions card shows it.
 *
 * The payload is never read. It holds whatever the application put into the
 * session, and a card that only has to say "where and when" has no business
 * decoding it.
 */
final readonly class BrowserSession
{
    public function __construct(
        public string $id,
        public ?string $ipAddress,
        public UserAgent $agent,
        public CarbonImmutable $lastActive,
        public bool $current,
    ) {}

    /**
     * @param  object{id: string, ip_address?: string|null, user_agent?: string|null, last_activity: int|string}  $row
     */
    public static function fromRow(object $row, string $currentId): self
    {
        return new self(
            id: (string) $row->id,
            ipAddress: $row->ip_address ?? null,
            agent: UserAgent::parse($row->user_agent ?? null),
            lastActive: CarbonImmutable::createFromTimestamp((int) $row->last_activity),
            current: $row->id === $currentId,
        );
    }
}
