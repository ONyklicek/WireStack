<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Schema\Grid;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Infolist;

// ─── Factory & schema ────────────────────────────────────────────────────────

it('can be created via static make()', function () {
    expect(Infolist::make())->toBeInstanceOf(Infolist::class);
});

it('has sensible defaults', function () {
    $infolist = Infolist::make();

    expect($infolist->getRecord())->toBeNull()
        ->and($infolist->getSchema())->toBe([])
        ->and($infolist->getColumns())->toBe(1);
});

it('stores schema, record and columns', function () {
    $record = ['name' => 'Ada'];
    $entry = TextEntry::make('name');

    $infolist = Infolist::make()
        ->record($record)
        ->columns(2)
        ->schema([$entry]);

    expect($infolist->getRecord())->toBe($record)
        ->and($infolist->getColumns())->toBe(2)
        ->and($infolist->getSchema())->toBe([$entry]);
});

it('accepts a plain array via state()', function () {
    $infolist = Infolist::make()->state(['name' => 'Ada']);

    expect($infolist->getRecord())->toBe(['name' => 'Ada']);
});

// ─── Record propagation ──────────────────────────────────────────────────────

it('propagates the record to top-level entries on render', function () {
    $entry = TextEntry::make('name');

    Infolist::make()->record(['name' => 'Ada'])->schema([$entry])->toHtml();

    expect($entry->getRecord())->toBe(['name' => 'Ada'])
        ->and($entry->getState())->toBe('Ada');
});

it('propagates the record to entries nested in layout components', function () {
    $entry = TextEntry::make('profile.email');

    Infolist::make()
        ->record(['profile' => ['email' => 'ada@example.com']])
        ->schema([
            Section::make('Profile')->schema([
                Grid::make()->schema([$entry]),
            ]),
        ])
        ->toHtml();

    expect($entry->getState())->toBe('ada@example.com');
});

// ─── Rendering ───────────────────────────────────────────────────────────────

it('renders entry labels and values', function () {
    $html = Infolist::make()
        ->record(['name' => 'Ada Lovelace'])
        ->schema([TextEntry::make('name')->label('Full name')])
        ->toHtml();

    expect($html)->toContain('Full name')
        ->and($html)->toContain('Ada Lovelace');
});

it('renders a section heading', function () {
    $html = Infolist::make()
        ->record(['name' => 'Ada'])
        ->schema([
            Section::make('Profile')->schema([TextEntry::make('name')]),
        ])
        ->toHtml();

    expect($html)->toContain('Profile');
});
