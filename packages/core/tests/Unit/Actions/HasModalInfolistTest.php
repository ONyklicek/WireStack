<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ViewAction;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Infolist;

// ─── infolist() setter ───────────────────────────────────────────────────────

it('has no infolist modal by default', function () {
    expect(Action::make('view')->hasInfolistModal())->toBeFalse()
        ->and(Action::make('view')->hasInfolistInstance())->toBeFalse();
});

it('enables a modal when an infolist is set from an array of entries', function () {
    $action = ViewAction::make()->infolist([TextEntry::make('name')]);

    expect($action->hasModal())->toBeTrue()
        ->and($action->hasInfolistModal())->toBeTrue()
        ->and($action->hasInfolistInstance())->toBeTrue();
});

it('accepts an Infolist instance', function () {
    $action = ViewAction::make()->infolist(
        Infolist::make()->schema([TextEntry::make('name')])
    );

    expect($action->hasInfolistModal())->toBeTrue();
});

// ─── Not a confirmation ──────────────────────────────────────────────────────

it('does not require confirmation when an infolist is set', function () {
    $action = ViewAction::make()->infolist([TextEntry::make('name')]);

    expect($action->doesRequireConfirmation())->toBeFalse();
});

it('reports hasInfolist in the modal config', function () {
    $config = ViewAction::make()->infolist([TextEntry::make('name')])->getModalConfig();

    expect($config['hasInfolist'])->toBeTrue()
        ->and($config['isConfirmation'])->toBeFalse()
        ->and($config['hasForm'])->toBeFalse();
});

// ─── Record binding ──────────────────────────────────────────────────────────

it('binds the action record to the resolved infolist', function () {
    $action = ViewAction::make()->infolist([TextEntry::make('name')]);

    $infolist = $action->getInfolistInstance(['name' => 'Ada']);

    expect($infolist)->toBeInstanceOf(Infolist::class)
        ->and($infolist->getRecord())->toBe(['name' => 'Ada'])
        ->and($infolist->getSchema()[0]->record(['name' => 'Ada'])->getState())->toBe('Ada');
});

it('resolves an infolist from a closure with the record', function () {
    $action = ViewAction::make()->infolist(
        fn ($record) => Infolist::make()->schema([TextEntry::make('name')])
    );

    $infolist = $action->getInfolistInstance(['name' => 'Grace']);

    expect($infolist)->toBeInstanceOf(Infolist::class)
        ->and($infolist->getRecord())->toBe(['name' => 'Grace']);
});

it('resolves an infolist from a closure returning an array of entries', function () {
    $action = ViewAction::make()->infolist(
        fn ($record) => [TextEntry::make('name')]
    );

    $infolist = $action->getInfolistInstance(['name' => 'Linus']);

    expect($infolist)->toBeInstanceOf(Infolist::class)
        ->and($infolist->getRecord())->toBe(['name' => 'Linus']);
});

it('returns null infolist instance when none configured', function () {
    expect(ViewAction::make()->getInfolistInstance(['x' => 1]))->toBeNull();
});

// ─── Rendering inside the modal body ─────────────────────────────────────────

it('renders the bound record values', function () {
    $html = ViewAction::make()
        ->infolist([TextEntry::make('name')->label('Full name')])
        ->getInfolistInstance(['name' => 'Ada Lovelace'])
        ->toHtml();

    expect($html)->toContain('Full name')
        ->and($html)->toContain('Ada Lovelace');
});
