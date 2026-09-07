<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\ValueObjects;

/**
 * A before-and-after diff, in the shape a screen can render.
 *
 * The rule this owns is small and was written twice: **what a stored value looks
 * like once it is a change**. An audit entry holds JSON, so a value that moved
 * can be a string, an int, a bool, an array or null — and the two places that
 * drew a diff answered that differently. The trail slide-over printed a boolean
 * `true` as `1` and formatted arrays inline in Blade; the audit module's screen
 * had its own `display()` with the boolean case in it. Two rules, one question,
 * and the one in Blade was the one nobody could test.
 *
 * It is in `Foundation` rather than beside either of them because both are L2
 * (`Audit` and `Infolists`) and may not see each other — a shared answer has to
 * live below both (ADR 0025).
 *
 * **Null is preserved, never turned into `''`.** A field that went from a value
 * to nothing and a field that went from nothing to a value are the two most
 * readable rows in a diff, and both depend on the renderer being able to tell
 * "empty" from "an empty string".
 */
final readonly class ChangeSet
{
    /** @param array<int, array{field: string, before: string|null, after: string|null}> $rows */
    private function __construct(public array $rows) {}

    /**
     * Build from the `{old, new}` pairs an audit entry produces.
     *
     * The pairing and the dropping of keys that did not move stay with whoever
     * produced the diff — this adds no second opinion about what changed, only
     * about how it reads.
     *
     * @param  array<string, array{old: mixed, new: mixed}>  $diff
     */
    public static function fromDiff(array $diff): self
    {
        $rows = [];

        foreach ($diff as $field => $change) {
            $rows[] = [
                'field' => (string) $field,
                'before' => self::display($change['old'] ?? null),
                'after' => self::display($change['new'] ?? null),
            ];
        }

        return new self($rows);
    }

    /** Nothing moved. */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Just the names — the column that answers "what did they touch?".
     *
     * @return array<int, string>
     */
    public function fields(): array
    {
        return array_column($this->rows, 'field');
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /**
     * One value as text, or null when there was none.
     *
     * `true`/`false` rather than `1`/`""`, because a boolean printed as `1` is
     * indistinguishable from the integer that means something else, and printed
     * as `""` is indistinguishable from empty — which is the one thing the
     * placeholder is for.
     */
    private static function display(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // What is left is what a JSON cast can still hold — a string, an int, a
        // float. An object is not one of them: a cast produces arrays.
        return is_scalar($value) ? (string) $value : (string) json_encode($value);
    }
}
