<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use NyonCode\WireModuleAuth\Contracts\OneTimeCodes;
use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\ValueObjects\OneTimeCode;
use stdClass;

/**
 * Codes in a table, hashed, expiring, and counted down.
 *
 * Modelled on Laravel's own `DatabaseTokenRepository` — same hashing, same
 * "one live row per identifier", same expiry read from config — with the two
 * things a six-digit code needs that a forty-character token does not:
 *
 *  - **an attempt counter on the row.** A token nobody can guess needs no such
 *    thing; a code with a million possibilities needs it more than it needs the
 *    route throttle, because the route can be approached from a hundred
 *    addresses and the row cannot.
 *  - **a resend window.** Issuing replaces, so the code in the last mail stops
 *    working the moment another is sent — {@see recentlyIssued()} is what keeps a
 *    leaned-on button from mailing five codes of which four are already dead.
 *
 * Note what is *not* here: no "why did it fail". Wrong, expired, never issued and
 * out of attempts all return `null`, because the difference is only ever useful
 * to somebody guessing.
 */
final class DatabaseOneTimeCodes implements OneTimeCodes
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    /**
     * Replace first, then insert.
     *
     * In that order rather than as an upsert, because the unique index is on the
     * pair and an update would keep the attempt counter of the code being
     * replaced — a person who asked for a second code would inherit their own
     * wrong guesses at the first.
     */
    public function issue(CodePurpose $purpose, string $identifier, array $payload = []): OneTimeCode
    {
        $code = $this->generate();
        $expiresAt = Carbon::now()->addMinutes($this->minutes());

        $this->invalidate($purpose, $identifier);

        $this->table()->insert([
            'purpose' => $purpose->value,
            'identifier' => $identifier,
            'code' => Hash::make($code),
            'payload' => $payload === [] ? null : json_encode($payload, JSON_THROW_ON_ERROR),
            'attempts' => 0,
            'expires_at' => $expiresAt,
            'created_at' => Carbon::now(),
        ]);

        return new OneTimeCode($purpose, $identifier, $code, $expiresAt, $payload);
    }

    /**
     * Expiry, then the hash, then consume — and `null` for every kind of no.
     *
     * The order is the interesting part: checking the hash first would spend a
     * `Hash::check()` on a code that is already dead, and counting an attempt
     * against it would let an expired code lock out the one that replaces it.
     */
    public function verify(CodePurpose $purpose, string $identifier, string $code): ?OneTimeCode
    {
        $record = $this->find($purpose, $identifier);

        if ($record === null) {
            return null;
        }

        // Expiry first, and it consumes: leaving a stale row behind means the
        // next `issue()` is the only thing that ever clears it, and a person who
        // walks away mid-flow leaves a hash sitting in the table for as long as
        // the account exists.
        if (Carbon::parse($record->expires_at)->isPast()) {
            $this->invalidate($purpose, $identifier);

            return null;
        }

        if (! Hash::check($code, (string) $record->code)) {
            $this->recordAttempt($purpose, $identifier, (int) $record->attempts + 1);

            return null;
        }

        $this->invalidate($purpose, $identifier);

        return new OneTimeCode(
            $purpose,
            $identifier,
            code: '',
            expiresAt: Carbon::parse($record->expires_at),
            payload: $this->payload($record),
        );
    }

    public function recentlyIssued(CodePurpose $purpose, string $identifier): bool
    {
        $record = $this->find($purpose, $identifier);

        if ($record === null) {
            return false;
        }

        $seconds = (int) config('wire-module-auth.codes.resend_after', 60);

        return Carbon::parse($record->created_at)->addSeconds($seconds)->isFuture();
    }

    public function invalidate(CodePurpose $purpose, string $identifier): void
    {
        $this->table()
            ->where('purpose', $purpose->value)
            ->where('identifier', $identifier)
            ->delete();
    }

    /**
     * The row for this purpose and identifier, if there is one.
     *
     * `issue()` replaces rather than appends, so there is at most one — the
     * ordering is belt and braces for a store that was written to by hand.
     */
    private function find(CodePurpose $purpose, string $identifier): ?stdClass
    {
        /** @var stdClass|null $record */
        $record = $this->table()
            ->where('purpose', $purpose->value)
            ->where('identifier', $identifier)
            ->orderByDesc('created_at')
            ->first();

        return $record;
    }

    /**
     * Count a wrong guess, and burn the code when there have been too many.
     *
     * The row goes rather than the counter stopping, because a code left in the
     * table after its last attempt is a code a slow attacker can come back to
     * once the route throttle has forgotten them.
     */
    private function recordAttempt(CodePurpose $purpose, string $identifier, int $attempts): void
    {
        if ($attempts >= (int) config('wire-module-auth.codes.attempts', 5)) {
            $this->invalidate($purpose, $identifier);

            return;
        }

        $this->table()
            ->where('purpose', $purpose->value)
            ->where('identifier', $identifier)
            ->update(['attempts' => $attempts]);
    }

    /**
     * The digits.
     *
     * `Str::random()` is not an option and neither is `rand()`: this is a
     * credential, so the source has to be the CSPRNG. `random_int` is that, and
     * padding rather than a range starting at 100000 keeps every code the
     * configured length — a code that is sometimes five digits long looks like a
     * typo in the mail.
     */
    private function generate(): string
    {
        $length = max(4, (int) config('wire-module-auth.codes.length', 6));

        return Str::padLeft((string) random_int(0, (10 ** $length) - 1), $length, '0');
    }

    /** @return array<string, mixed> */
    private function payload(stdClass $record): array
    {
        if (! is_string($record->payload) || $record->payload === '') {
            return [];
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($record->payload, true, flags: JSON_THROW_ON_ERROR);

        return $payload;
    }

    /** How long a code is worth anything, never less than a minute. */
    private function minutes(): int
    {
        return max(1, (int) config('wire-module-auth.codes.expires', 10));
    }

    /** The configured table, on the connection this store was handed. */
    private function table(): Builder
    {
        return $this->connection->table((string) config('wire-module-auth.codes.table', 'wire_auth_one_time_codes'));
    }
}
