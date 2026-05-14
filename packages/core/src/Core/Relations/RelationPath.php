<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Relations;

use InvalidArgumentException;
use NyonCode\WireCore\Core\Relations\Contracts\Segment;

/**
 * Parsed relation path — structured AST replacing explode('.', $path).
 *
 * Supports:
 * - Simple: "email" → [ColumnSegment('email')]
 * - Relation: "user.email" → [RelationSegment('user'), ColumnSegment('email')]
 * - Nested: "user.company.name" → [RelationSegment('user'), RelationSegment('company'), ColumnSegment('name')]
 * - Pivot: "roles.pivot.created_at" → [RelationSegment('roles'), PivotSegment('created_at')]
 * - Aggregate: "orders->count()" → [AggregateSegment('orders', 'count')]
 * - Aggregate with column: "orders->sum(total)" → [AggregateSegment('orders', 'sum', 'total')]
 */
final readonly class RelationPath
{
    /** @var array<int, Segment> */
    public array $segments;

    /**
     * @param  array<int, Segment>  $segments
     */
    private function __construct(array $segments)
    {
        if ($segments === []) {
            throw new InvalidArgumentException('RelationPath must have at least one segment.');
        }

        $this->segments = $segments;
    }

    public static function parse(string $path): self
    {
        if ($path === '') {
            throw new InvalidArgumentException('RelationPath cannot be empty.');
        }

        // Check for aggregate syntax: "relation->function(column)"
        if (str_contains($path, '->') && str_contains($path, '(')) {
            return self::parseAggregate($path);
        }

        $parts = explode('.', $path);
        $segments = [];

        foreach ($parts as $index => $part) {
            $isLast = $index === count($parts) - 1;

            // Pivot detection: previous part is a relation, current is "pivot", next is attribute
            if ($part === 'pivot' && $index > 0 && isset($parts[$index + 1])) {
                $segments[] = new PivotSegment($parts[$index + 1]);
                break;
            }

            // Skip if we already consumed this part as pivot attribute
            if ($index > 0 && $parts[$index - 1] === 'pivot') {
                continue;
            }

            if ($isLast) {
                $segments[] = new ColumnSegment($part);
            } else {
                $segments[] = new RelationSegment($part);
            }
        }

        return new self($segments);
    }

    private static function parseAggregate(string $path): self
    {
        // Format: "relation->function(column)" or "relation->function()"
        if (! preg_match('/^(.+)->(\w+)\((\w*)\)$/', $path, $matches)) {
            throw new InvalidArgumentException("Invalid aggregate syntax: {$path}");
        }

        $relationParts = explode('.', $matches[1]);
        $function = $matches[2];
        $column = $matches[3] !== '' ? $matches[3] : null;

        $segments = [];
        $lastRelation = array_pop($relationParts);

        foreach ($relationParts as $part) {
            $segments[] = new RelationSegment($part);
        }

        $segments[] = new AggregateSegment(
            relation: $lastRelation,
            function: $function,
            column: $column,
        );

        return new self($segments);
    }

    /**
     * @param  array<int, Segment>  $segments
     */
    public static function fromSegments(array $segments): self
    {
        return new self($segments);
    }

    public function isSimple(): bool
    {
        return count($this->segments) === 1;
    }

    public function hasRelation(): bool
    {
        return count($this->segments) > 1
            || $this->segments[0] instanceof AggregateSegment;
    }

    public function isAggregate(): bool
    {
        return $this->getTerminal() instanceof AggregateSegment;
    }

    public function isPivot(): bool
    {
        return $this->getTerminal() instanceof PivotSegment;
    }

    public function getTerminal(): Segment
    {
        return $this->segments[count($this->segments) - 1];
    }

    /**
     * Get relation segments (all non-terminal segments).
     *
     * @return array<int, RelationSegment|MorphSegment>
     */
    public function getRelationSegments(): array
    {
        return array_values(array_filter(
            $this->segments,
            fn (Segment $s) => $s instanceof RelationSegment || $s instanceof MorphSegment,
        ));
    }

    /**
     * Get the dot-notation relation path (without the terminal column).
     */
    public function getRelationPath(): ?string
    {
        $relations = $this->getRelationSegments();
        if ($relations === []) {
            return null;
        }

        return implode('.', array_map(fn (Segment $s) => $s->getName(), $relations));
    }

    /**
     * Get the terminal column/attribute name.
     */
    public function getColumnName(): string
    {
        return $this->getTerminal()->getName();
    }

    /**
     * Get the full dot-notation path.
     */
    public function toString(): string
    {
        return implode('.', array_map(fn (Segment $s) => $s->getName(), $this->segments));
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function depth(): int
    {
        return count($this->segments);
    }
}
