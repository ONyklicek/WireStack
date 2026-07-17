<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Exceptions\UnsafeSqlException;
use NyonCode\WireCore\Foundation\Contracts\WireException;
use NyonCode\WireForms\Exceptions\FormConfigurationException;
use NyonCode\WireForms\Forms\Runtime\StaleModelException;
use NyonCode\WireTable\Exceptions\ImportException;
use NyonCode\WireTable\Exceptions\RelationManagerException;
use NyonCode\WireTable\Exceptions\TableConfigurationException;
use NyonCode\WireTable\Exceptions\TableHasNoDataSourceException;
use NyonCode\WireTable\Table;

it('lets one clause catch a failure from anywhere in the stack', function (Throwable $e) {
    expect($e)->toBeInstanceOf(WireException::class);
})->with([
    'core' => fn () => UnsafeSqlException::emptyIdentifier(),
    'forms' => fn () => FormConfigurationException::noModel(),
    // Predates the Exceptions/ convention and stays at its documented FQCN, but
    // is catchable with the rest of the stack all the same (ADR 0022).
    'forms (pre-existing)' => fn () => new StaleModelException(new class extends Model {}, 'updated_at'),
    'table' => fn () => TableHasNoDataSourceException::make(),
]);

it('keeps the SPL base each site has always thrown', function () {
    // This is the backwards-compatibility guarantee: an application already
    // catching the SPL class is unaffected by the move to a domain class. The
    // two bases are siblings, so getting this wrong breaks every caller.
    expect(TableHasNoDataSourceException::make())->toBeInstanceOf(RuntimeException::class)
        ->and(RelationManagerException::missingOwnerRecord('X'))->toBeInstanceOf(RuntimeException::class)
        ->and(ImportException::noModelOrHandler())->toBeInstanceOf(RuntimeException::class)
        ->and(TableConfigurationException::invalidPollInterval())->toBeInstanceOf(InvalidArgumentException::class);
});

it('is still catchable as the SPL class the docs and tests rely on', function () {
    // The real proof: the throw site changed, this catch did not.
    try {
        Table::make()->getQuery();
        $this->fail('Expected a table with no data source to fail.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('No model or query defined for table.')
            ->and($e)->toBeInstanceOf(TableHasNoDataSourceException::class)
            ->and($e)->toBeInstanceOf(WireException::class);
    }
});

it('can be caught narrowly by its own class', function () {
    Table::make()->getQuery();
})->throws(TableHasNoDataSourceException::class);

it('rejects a poll interval that is not a Livewire duration', function () {
    Table::make()->poll('banana');
})->throws(TableConfigurationException::class, 'Interval must be like');

it('still rejects a bad poll interval as an InvalidArgumentException', function () {
    Table::make()->poll('banana');
})->throws(InvalidArgumentException::class);
