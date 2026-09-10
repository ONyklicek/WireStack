<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Contracts;

use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\ValueObjects\OneTimeCode;

/**
 * Where a one-time code comes from, and what makes it true.
 *
 * The whole of the security surface ADR 0037 admits into this repository, behind
 * one interface so an application can put its own store in front of it — codes
 * in Redis with a TTL, codes from a gateway that also sends the SMS — without
 * any of the four flows knowing.
 *
 * Four rules the flows depend on, and an implementation that breaks one breaks
 * the flow rather than this interface:
 *
 *  - **the plain code exists once**, in the value {@see issue()} returns. What is
 *    stored is a hash;
 *  - **a code is scoped to its purpose and its identifier**, so a code mailed to
 *    confirm an address is not a sign-in;
 *  - **verifying consumes**, whether it succeeded or ran out of attempts. A code
 *    that can be tried twice is a code that can be tried a thousand times;
 *  - **an expired code is a wrong code.** Never an error of its own — the reply
 *    is the same either way, because the difference is worth knowing only to
 *    somebody guessing.
 */
interface OneTimeCodes
{
    /**
     * Mint a code for this purpose and this identifier, replacing any it has.
     *
     * @param  array<string, mixed>  $payload  Carried on the row and handed back
     *                                         by {@see verify()} — the reset
     *                                         flow's broker token rides here.
     */
    public function issue(CodePurpose $purpose, string $identifier, array $payload = []): OneTimeCode;

    /**
     * Whether the digits are the ones that were issued, and still valid.
     *
     * Returns the code — payload and all, with the digits blank — on success and
     * `null` for every kind of no: wrong, expired, never issued, or one guess too
     * many.
     */
    public function verify(CodePurpose $purpose, string $identifier, string $code): ?OneTimeCode;

    /**
     * Whether one was sent so recently that another would be noise.
     *
     * What the "resend" button asks before it does anything, so a person leaning
     * on it does not mail themselves five codes of which only the last works.
     */
    public function recentlyIssued(CodePurpose $purpose, string $identifier): bool;

    /** Throw away whatever is outstanding, on the way out of a finished flow. */
    public function invalidate(CodePurpose $purpose, string $identifier): void;
}
