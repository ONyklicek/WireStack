<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Metadata;

/**
 * Immutable metadata about a database column or computed attribute.
 */
final readonly class ColumnMetadata
{
    public function __construct(
        public string $name,
        public ?string $dbColumn,
        public ?string $dbType,
        public bool $isAccessor,
        public bool $isComputed,
        public ?string $sqlExpression,
        public bool $nullable,
    ) {}

    public static function forDatabaseColumn(
        string $name,
        ?string $dbType = null,
        bool $nullable = false,
    ): self {
        return new self(
            name: $name,
            dbColumn: $name,
            dbType: $dbType,
            isAccessor: false,
            isComputed: false,
            sqlExpression: null,
            nullable: $nullable,
        );
    }

    public static function forAccessor(
        string $name,
        ?string $sqlExpression = null,
    ): self {
        return new self(
            name: $name,
            dbColumn: null,
            dbType: null,
            isAccessor: true,
            isComputed: $sqlExpression !== null,
            sqlExpression: $sqlExpression,
            nullable: false,
        );
    }

    public static function forComputed(
        string $name,
        string $sqlExpression,
    ): self {
        return new self(
            name: $name,
            dbColumn: null,
            dbType: null,
            isAccessor: false,
            isComputed: true,
            sqlExpression: $sqlExpression,
            nullable: false,
        );
    }

    public function isSqlCompatible(): bool
    {
        return $this->dbColumn !== null || $this->sqlExpression !== null;
    }

    public function isRuntimeOnly(): bool
    {
        return $this->isAccessor && $this->sqlExpression === null;
    }

    public function getSqlReference(): ?string
    {
        return $this->sqlExpression ?? $this->dbColumn;
    }
}
