<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Support\Validation;

/**
 * One problem found in a wire component, phrased so an agent can act on it.
 */
class Diagnostic
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    /**
     * @param  string  $severity  self::ERROR (certainly wrong) or self::WARNING (probably wrong).
     * @param  string  $rule  Machine-readable rule id, e.g. "unknown-color".
     * @param  string  $target  What is wrong, e.g. "columns.status".
     * @param  array<int, string>  $suggestions  Near-miss candidates, best first.
     */
    public function __construct(
        public readonly string $severity,
        public readonly string $rule,
        public readonly string $target,
        public readonly string $message,
        public readonly array $suggestions = [],
    ) {}

    /**
     * @param  array<int, string>  $suggestions
     */
    public static function error(string $rule, string $target, string $message, array $suggestions = []): self
    {
        return new self(self::ERROR, $rule, $target, $message, $suggestions);
    }

    /**
     * @param  array<int, string>  $suggestions
     */
    public static function warning(string $rule, string $target, string $message, array $suggestions = []): self
    {
        return new self(self::WARNING, $rule, $target, $message, $suggestions);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'severity' => $this->severity,
            'rule' => $this->rule,
            'target' => $this->target,
            'message' => $this->message,
            'suggestions' => $this->suggestions,
        ], static fn (mixed $value): bool => $value !== []);
    }
}
