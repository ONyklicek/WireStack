<?php

declare(strict_types=1);

use NyonCode\WireBoost\Exceptions\ComponentBuildFailedException;
use NyonCode\WireBoost\Exceptions\DocumentNotFoundException;
use NyonCode\WireBoost\Exceptions\UnresolvableComponentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

it('marks every boost failure as a wire failure', function (Throwable $e) {
    // The marker is what lets a consumer catch the whole stack in one clause.
    expect($e)->toBeInstanceOf(WireException::class);
})->with([
    fn () => UnresolvableComponentException::classMissing('App\\Nope'),
    fn () => DocumentNotFoundException::document('docs/nope.md'),
    fn () => ComponentBuildFailedException::make('App\\T', 'table', new RuntimeException('boom')),
]);

it('keeps the SPL base each failure is really about', function () {
    // A bad argument from the caller vs. a failure while running their code.
    expect(UnresolvableComponentException::classMissing('X'))->toBeInstanceOf(InvalidArgumentException::class)
        ->and(DocumentNotFoundException::section('x#y'))->toBeInstanceOf(InvalidArgumentException::class)
        ->and(ComponentBuildFailedException::make('X', 'table', new RuntimeException('b')))
        ->toBeInstanceOf(RuntimeException::class);
});

it('names the recovery move in every message', function () {
    // The reader is an agent: telling it which tool to call next is the point.
    expect(UnresolvableComponentException::classMissing('App\\Nope')->getMessage())
        ->toContain('list-wire-components')
        ->and(UnresolvableComponentException::unknownType('nope')->getMessage())
        ->toContain('list-component-types')
        ->and(DocumentNotFoundException::document('docs/nope.md')->getMessage())
        ->toContain('search-wire-docs')
        ->and(DocumentNotFoundException::section('docs/x.md#nope')->getMessage())
        ->toContain('search-wire-docs');
});

it('reports a missing wire package by the builder that is absent', function () {
    // Only reachable in a host app that installs wire-boost without wire-table.
    expect(UnresolvableComponentException::packageMissing('NyonCode\\WireTable\\Table')->getMessage())
        ->toContain('NyonCode\\WireTable\\Table')
        ->toContain('not installed');
});

it('lists the valid categories when one is unknown', function () {
    expect(UnresolvableComponentException::unknownCategory('bogus', ['columns', 'filters'])->getMessage())
        ->toContain('[bogus]')
        ->toContain('columns, filters');
});

it('keeps the original failure whole', function () {
    $original = new RuntimeException('boom');
    $wrapped = ComponentBuildFailedException::make('App\\UsersTable', 'table', $original);

    expect($wrapped->getPrevious())->toBe($original)
        ->and($wrapped->component)->toBe('App\\UsersTable')
        ->and($wrapped->builderMethod)->toBe('table')
        ->and($wrapped->getMessage())->toContain('App\\UsersTable::table()')
        ->toContain('boom');
});
