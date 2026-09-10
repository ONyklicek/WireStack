<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Tests\Fixtures;

use NyonCode\WireModuleAuth\Contracts\OneTimeCodes;
use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\ValueObjects\OneTimeCode;

/**
 * The real store, with a note of the last code it minted.
 *
 * The test's stand-in for an inbox. It is needed for the reset flow above all:
 * that code is minted *inside* the mail Laravel's broker sends, so there is no
 * notification object to read it off and the rendered MIME is quoted-printable
 * — a regex over it matches a message-id as readily as a code.
 *
 * A decorator rather than a fake: every one of these tests still runs against
 * `DatabaseOneTimeCodes`, hashing and expiry and attempt counting included. All
 * this adds is the one thing a test cannot otherwise see, which is the same
 * thing a person sees and the database deliberately does not keep.
 */
final class RecordingCodes implements OneTimeCodes
{
    public ?OneTimeCode $last = null;

    public function __construct(private readonly OneTimeCodes $store) {}

    public function issue(CodePurpose $purpose, string $identifier, array $payload = []): OneTimeCode
    {
        return $this->last = $this->store->issue($purpose, $identifier, $payload);
    }

    public function verify(CodePurpose $purpose, string $identifier, string $code): ?OneTimeCode
    {
        return $this->store->verify($purpose, $identifier, $code);
    }

    public function recentlyIssued(CodePurpose $purpose, string $identifier): bool
    {
        return $this->store->recentlyIssued($purpose, $identifier);
    }

    public function invalidate(CodePurpose $purpose, string $identifier): void
    {
        $this->store->invalidate($purpose, $identifier);
    }
}
