<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Query\ColumnReference;
use NyonCode\WireCore\Core\Query\FilterClause;
use NyonCode\WireCore\Core\Query\SearchClause;
use NyonCode\WireCore\Core\Query\SortClause;

it('lets a raw expression stand for the column, alias and all', function () {
    // The expression replaces both, and it wins even against an alias — a
    // clause that carries one has already put the qualification in the SQL.
    expect(ColumnReference::qualify('total', 'orders', 'SUM(items.price)'))
        ->toBe('SUM(items.price)');
});

it('qualifies the column with the alias the clause joined through', function () {
    expect(ColumnReference::qualify('name', 'authors'))->toBe('authors.name');
});

it('leaves a column on the base table alone', function () {
    expect(ColumnReference::qualify('name'))->toBe('name');
});

it('is the one rule all three clause kinds answer with', function () {
    // Sorting, filtering and searching each carried their own copy of it.
    $expected = ColumnReference::qualify('name', 'authors');

    expect((new SortClause('name', tableAlias: 'authors'))->getQualifiedColumn())->toBe($expected)
        ->and((new FilterClause('name', tableAlias: 'authors'))->getQualifiedColumn())->toBe($expected)
        ->and((new SearchClause('name', tableAlias: 'authors'))->getQualifiedColumn())->toBe($expected);
});
