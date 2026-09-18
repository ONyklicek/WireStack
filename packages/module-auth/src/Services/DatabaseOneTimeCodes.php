<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
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
     * One statement that inserts the code or replaces the one already there.
     *
     * It used to be two — delete the pair, then insert — with nothing between
     * them. Two requests for the first code (a double click, a mail client
     * prefetching the POST) both deleted nothing and both inserted, and the
     * second insert hit the unique index: an uncaught 500 on the sign-in screen,
     * the one flow built to answer the same bland sentence whatever happens.
     *
     * An upsert has no such window on any driver. What kept it out before was
     * that an update would keep the attempt counter of the code being replaced,
     * so a person who asked for a second code would inherit their own wrong
     * guesses at the first — which naming `attempts` among the columns to
     * overwrite answers: it is reset along with the code.
     */
    public function issue(CodePurpose $purpose, string $identifier, array $payload = []): OneTimeCode
    {
        $code = $this->generate();
        $expiresAt = Carbon::now()->addMinutes($this->minutes());

        $this->table()->upsert(
            [[
                'purpose' => $purpose->value,
                'identifier' => $identifier,
                'code' => Hash::make($code),
                'payload' => $payload === [] ? null : json_encode($payload, JSON_THROW_ON_ERROR),
                'attempts' => 0,
                'expires_at' => $expiresAt,
                'created_at' => Carbon::now(),
            ]],
            ['purpose', 'identifier'],
            ['code', 'payload', 'attempts', 'expires_at', 'created_at'],
        );

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
            $this->consume($record);

            return null;
        }

        if (! Hash::check($code, (string) $record->code)) {
            $this->recordAttempt($purpose, $identifier);

            return null;
        }

        // Only the request that actually removes the row has spent the code.
        // Two requests carrying the same right digits both read the row and both
        // pass the hash; before, both then ran a delete that did not care whether
        // it removed anything, and both came back successful — one mailed code,
        // two sessions. The delete that finds nothing lost the race, and a lost
        // race is a no like any other.
        if (! $this->consume($record)) {
            return null;
        }

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
     * Remove exactly the row that was read, and say whether this call did.
     *
     * Named by its key *and* its hash, not by the pair: an `issue()` landing
     * between the read and this delete replaces the code in place — same row,
     * new hash — and a delete by the pair, or by the key alone, would then throw
     * away the code in the newest mail on the strength of an older one.
     */
    private function consume(stdClass $record): bool
    {
        return $this->table()
            ->where('id', $record->id)
            ->where('code', $record->code)
            ->delete() > 0;
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
    private function recordAttempt(CodePurpose $purpose, string $identifier): void
    {
        $row = fn () => $this->table()
            ->where('purpose', $purpose->value)
            ->where('identifier', $identifier);

        // The database does the addition, and that is the whole point. This used
        // to read `attempts`, add one in PHP and write the result back, which is
        // only correct when the guesses arrive one at a time: N requests that all
        // read 0 all write 1, and a burst of parallel guesses cost one attempt
        // instead of N. The route throttle does not cover that case — it is keyed
        // per IP, and this counter is what the design leans on precisely when the
        // guesses come from a hundred addresses at once.
        $row()->increment('attempts');

        // Read back rather than assume: another request may have incremented
        // between the two statements, and the limit is about the total.
        $attempts = (int) ($row()->value('attempts') ?? 0);

        if ($attempts >= (int) config('wire-module-auth.codes.attempts', 5)) {
            $this->invalidate($purpose, $identifier);
        }
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

        // One digit at a time rather than one number padded to width. The old
        // form drew `random_int(0, 10 ** $length - 1)`, and `max()` clamped only
        // the bottom: at 19 digits `10 ** $length` passes PHP_INT_MAX, becomes a
        // float, and `random_int()` rejects a float bound with a TypeError — an
        // uncaught fatal on whichever screen was minting the code. Per-digit has
        // no ceiling to fall off, needs no padding because every position is
        // drawn, and is the same uniform distribution over the same space.
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= random_int(0, 9);
        }

        return $code;
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
