<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\ValueObjects;

use Carbon\CarbonInterface;
use NyonCode\WireModuleAuth\Contracts\OneTimeCodes;
use NyonCode\WireModuleAuth\Enums\CodePurpose;

/**
 * A code, in the one moment it is readable.
 *
 * Issuing returns this; verifying returns it again with `$code` empty, because
 * by then the plain digits are the thing the caller typed and the row holds only
 * a hash. What survives the round trip is the `payload` — for the reset flow,
 * the broker token the code stands in for (ADR 0037 §2).
 *
 * Readonly because a code that can be edited after it is issued is a code the
 * notification and the row can disagree about.
 */
final readonly class OneTimeCode
{
    /**
     * @param  string  $code  The digits, as the person will read them. Empty on
     *                        the way back out of {@see OneTimeCodes::verify()}.
     * @param  array<string, mixed>  $payload  Whatever the flow has to carry
     *                                         from issuing to verifying.
     */
    public function __construct(
        public CodePurpose $purpose,
        public string $identifier,
        public string $code,
        public CarbonInterface $expiresAt,
        public array $payload = [],
    ) {}

    /**
     * One value out of the payload.
     *
     * A method rather than array access at every call site: the reset flow asks
     * for `token` in one place and gets `null` rather than a notice if the row
     * was written by an older release that did not carry one.
     */
    public function payload(string $key): mixed
    {
        return $this->payload[$key] ?? null;
    }
}
