<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Relations\RelationPath;
use NyonCode\WireCore\Core\Support\SqlSafety;
use NyonCode\WireCore\Exceptions\InvalidRelationPathException;
use NyonCode\WireCore\Exceptions\UnsafeSqlException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

it('rejects a reserved word used as a bare identifier', function () {
    // A column literally named "select" would produce broken SQL if interpolated.
    SqlSafety::assertValidIdentifier('SELECT');
})->throws(UnsafeSqlException::class, 'reserved word');

it('still rejects an unsafe identifier as an InvalidArgumentException', function () {
    // The published SPL base, unchanged by the move to a domain class.
    SqlSafety::assertValidIdentifier('drop; --');
})->throws(InvalidArgumentException::class);

it('marks an unsafe SQL failure as a wire failure', function () {
    try {
        SqlSafety::assertValidOperator('; DROP TABLE users');
        $this->fail('Expected an unsafe operator to be rejected.');
    } catch (UnsafeSqlException $e) {
        expect($e)->toBeInstanceOf(WireException::class)
            ->and($e->getMessage())->toContain('Invalid SQL operator');
    }
});

it('rejects an empty relation path', function () {
    RelationPath::parse('');
})->throws(InvalidRelationPathException::class, 'cannot be empty');

it('rejects malformed aggregate syntax', function () {
    RelationPath::parse('posts->count(');
})->throws(InvalidRelationPathException::class, 'Invalid aggregate syntax');

it('guards the segment invariant it can never reach through parse()', function () {
    // parse() always yields at least one segment, so this protects the private
    // constructor against a future caller rather than any path that exists now.
    expect(InvalidRelationPathException::noSegments())
        ->toBeInstanceOf(InvalidRelationPathException::class)
        ->and(InvalidRelationPathException::noSegments()->getMessage())
        ->toContain('at least one segment');
});
