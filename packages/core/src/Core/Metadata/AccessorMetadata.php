<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Metadata;

/**
 * Immutable metadata about a model accessor.
 *
 * SQL expression must be explicitly set by the user — no source code parsing.
 */
final readonly class AccessorMetadata
{
    public function __construct(
        public string $name,
        public ?string $sqlExpression,
        public bool $runtimeOnly,
    ) {}

    public static function runtimeOnly(string $name): self
    {
        return new self(
            name: $name,
            sqlExpression: null,
            runtimeOnly: true,
        );
    }

    public static function withSqlExpression(string $name, string $sqlExpression): self
    {
        return new self(
            name: $name,
            sqlExpression: $sqlExpression,
            runtimeOnly: false,
        );
    }

    public function isSqlCompatible(): bool
    {
        return $this->sqlExpression !== null;
    }
}
