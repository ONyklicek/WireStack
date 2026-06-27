<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Support\SqlSafety;

// ── Valid identifiers ─────────────────────────────────────────

it('accepts simple column names', function () {
    SqlSafety::assertValidIdentifier('name');
    SqlSafety::assertValidIdentifier('user_name');
    SqlSafety::assertValidIdentifier('_private');

    expect(true)->toBeTrue();
});

it('accepts dotted column references', function () {
    SqlSafety::assertValidIdentifier('users.name');
    SqlSafety::assertValidIdentifier('users.company_id');

    expect(true)->toBeTrue();
});

it('accepts identifiers starting with underscore', function () {
    expect(SqlSafety::isValidIdentifier('_id'))->toBeTrue();
});

// ── Invalid identifiers ──────────────────────────────────────

it('rejects empty identifiers', function () {
    SqlSafety::assertValidIdentifier('');
})->throws(InvalidArgumentException::class);

it('rejects identifiers with spaces', function () {
    SqlSafety::assertValidIdentifier('user name');
})->throws(InvalidArgumentException::class);

it('rejects identifiers with special characters', function () {
    SqlSafety::assertValidIdentifier('name;DROP TABLE users');
})->throws(InvalidArgumentException::class);

it('rejects identifiers starting with numbers', function () {
    SqlSafety::assertValidIdentifier('1column');
})->throws(InvalidArgumentException::class);

it('rejects identifiers with parentheses', function () {
    expect(SqlSafety::isValidIdentifier('COUNT(*)'))->toBeFalse();
});

// ── Direction validation ─────────────────────────────────────

it('accepts valid sort directions', function () {
    SqlSafety::assertValidDirection('asc');
    SqlSafety::assertValidDirection('desc');
    SqlSafety::assertValidDirection('ASC');
    SqlSafety::assertValidDirection('DESC');

    expect(true)->toBeTrue();
});

it('rejects invalid sort directions', function () {
    SqlSafety::assertValidDirection('up');
})->throws(InvalidArgumentException::class);

// ── Operator validation ──────────────────────────────────────

it('accepts valid operators', function () {
    $valid = ['=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'IS NULL', 'IS NOT NULL'];

    foreach ($valid as $op) {
        SqlSafety::assertValidOperator($op);
    }

    expect(true)->toBeTrue();
});

it('rejects invalid operators', function () {
    SqlSafety::assertValidOperator('DROP');
})->throws(InvalidArgumentException::class);

// ── Qualified column validation ──────────────────────────────

it('accepts raw SQL expressions in qualified column check', function () {
    SqlSafety::assertValidQualifiedColumn('COUNT(*)');
    SqlSafety::assertValidQualifiedColumn("CONCAT(first_name, ' ', last_name)");

    expect(true)->toBeTrue();
});

it('accepts simple qualified columns', function () {
    SqlSafety::assertValidQualifiedColumn('users.name');
    SqlSafety::assertValidQualifiedColumn('name');

    expect(true)->toBeTrue();
});

// ── Direction normalisation ──────────────────────────────────

it('normalises sort direction to a safe keyword', function () {
    expect(SqlSafety::normalizeDirection('desc'))->toBe('desc')
        ->and(SqlSafety::normalizeDirection('DESC'))->toBe('desc')
        ->and(SqlSafety::normalizeDirection('asc'))->toBe('asc')
        ->and(SqlSafety::normalizeDirection('ASC'))->toBe('asc')
        ->and(SqlSafety::normalizeDirection(''))->toBe('asc')
        ->and(SqlSafety::normalizeDirection('; DROP TABLE users'))->toBe('asc');
});

// ── NULLS position normalisation ─────────────────────────────

it('normalises NULLS position to a safe keyword or null', function () {
    expect(SqlSafety::normalizeNullsPosition('FIRST'))->toBe('FIRST')
        ->and(SqlSafety::normalizeNullsPosition('last'))->toBe('LAST')
        ->and(SqlSafety::normalizeNullsPosition('NULLS LAST'))->toBe('LAST')
        ->and(SqlSafety::normalizeNullsPosition('  nulls first '))->toBe('FIRST')
        ->and(SqlSafety::normalizeNullsPosition(null))->toBeNull()
        ->and(SqlSafety::normalizeNullsPosition('garbage'))->toBeNull();
});
