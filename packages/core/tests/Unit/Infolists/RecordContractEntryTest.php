<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Data\ArrayRecord;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Infolist;

/*
 * An infolist over a record that is not a model.
 *
 * `RecordContract` has existed since V2.0.b and nothing displayed through it:
 * `Entry::getState()` reached for the record with `data_get()`, which reads
 * properties and array keys — and a contract has neither. Every entry over a
 * read model, a DTO or an API row rendered empty, which reads as "no data"
 * rather than as two layers that never met.
 */

it('asks a record contract for its state instead of reaching into it', function () {
    $entry = TextEntry::make('title');
    $entry->record(new ArrayRecord(['id' => 7, 'title' => 'Quarterly close'], 'id'));

    expect($entry->getState())->toBe('Quarterly close');
});

it('reads a dot path through the contract', function () {
    // Spanning relations is the contract's business, not the entry's — this only
    // pins that the entry hands the whole path over rather than splitting it.
    $entry = TextEntry::make('customer.name');
    $entry->record(new ArrayRecord(['customer' => ['name' => 'Acme']], 'id'));

    expect($entry->getState())->toBe('Acme');
});

it('still reads a model or an array the way it always did', function () {
    $fromArray = TextEntry::make('title');
    $fromArray->record(['title' => 'From an array']);

    expect($fromArray->getState())->toBe('From an array');
});

it('lets an explicit state callback win over the contract', function () {
    $entry = TextEntry::make('title')->getStateUsing(fn (mixed $record): string => 'computed');
    $entry->record(new ArrayRecord(['title' => 'Quarterly close'], 'id'));

    expect($entry->getState())->toBe('computed');
});

it('renders an infolist over a record contract', function () {
    $infolist = Infolist::make()
        ->record(new ArrayRecord(['id' => 7, 'title' => 'Quarterly close'], 'id'))
        ->schema([TextEntry::make('title')]);

    expect((string) $infolist->toHtml())->toContain('Quarterly close');
});
