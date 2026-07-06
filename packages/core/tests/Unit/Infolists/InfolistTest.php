<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Foundation\Contracts\HasFieldActions;
use NyonCode\WireCore\Foundation\Schema\Flex;
use NyonCode\WireCore\Foundation\Schema\Grid;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Infolists\Components\ListEntry;
use NyonCode\WireCore\Infolists\Components\RepeatableEntry;
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

// ─── Section header actions ──────────────────────────────────────────────────

it('exposes header actions on a section via the HasFieldActions contract', function () {
    $edit = Action::make('edit');
    $section = Section::make('Profile')->headerActions([$edit]);

    expect($section)->toBeInstanceOf(HasFieldActions::class)
        ->and($section->getHeaderActions())->toBe([$edit])
        ->and($section->getFieldAction('edit'))->toBe($edit)
        ->and($section->getFieldAction('missing'))->toBeNull();
});

it('renders section header actions wired to callInfolistAction', function () {
    $html = Infolist::make()
        ->record(['name' => 'Ada'])
        ->schema([
            Section::make('Profile')
                ->headerActions([Action::make('edit')->label('Edit')])
                ->schema([TextEntry::make('name')]),
        ])
        ->toHtml();

    expect($html)->toContain('Edit')
        ->and($html)->toContain("callInfolistAction('edit')");
});

it('renders a header for a section that only has header actions', function () {
    $html = Infolist::make()
        ->schema([
            Section::make()
                ->headerActions([Action::make('refresh')->label('Refresh')])
                ->schema([TextEntry::make('name')]),
        ])
        ->record(['name' => 'Ada'])
        ->toHtml();

    expect($html)->toContain('Refresh')
        ->and($html)->toContain("callInfolistAction('refresh')");
});

it('renders inline entry actions wired to callInfolistAction', function () {
    $html = Infolist::make()
        ->record(['email' => 'a@b.c'])
        ->schema([
            TextEntry::make('email')->actions([Action::make('copy')->label('Copy')]),
        ])
        ->toHtml();

    expect($html)->toContain('Copy')
        ->and($html)->toContain("callInfolistAction('copy')");
});

// ─── ListEntry rendering ─────────────────────────────────────────────────────

it('renders a bulleted list entry with an overflow indicator', function () {
    $html = Infolist::make()
        ->record(['tags' => ['a', 'b', 'c', 'd']])
        ->schema([ListEntry::make('tags')->limitList(2)])
        ->toHtml();

    expect($html)->toContain('list-disc')
        ->and($html)->toContain('>a<')
        ->and($html)->toContain('>b<')
        ->and($html)->not->toContain('>c<')
        ->and($html)->toContain('+2');
});

it('renders a list entry as badge chips', function () {
    $html = Infolist::make()
        ->record(['tags' => ['alpha', 'beta']])
        ->schema([ListEntry::make('tags')->badge()->color('success')])
        ->toHtml();

    expect($html)->toContain('rounded-full')
        ->and($html)->toContain('alpha')
        ->and($html)->toContain('beta');
});

// ─── Flex layout ─────────────────────────────────────────────────────────────

it('renders flex children side by side and propagates the record', function () {
    $html = Infolist::make()
        ->record(['first' => 'Ada', 'last' => 'Lovelace'])
        ->schema([
            Flex::make()->schema([
                TextEntry::make('first'),
                TextEntry::make('last'),
            ]),
        ])
        ->toHtml();

    expect($html)->toContain('md:flex-row')
        ->and($html)->toContain('Ada')
        ->and($html)->toContain('Lovelace');
});

it('renders repeatable per-row action buttons keyed by row index', function () {
    $html = Infolist::make()
        ->record(['lines' => [['sku' => 'A1'], ['sku' => 'B2']]])
        ->schema([
            RepeatableEntry::make('lines')
                ->schema([TextEntry::make('sku')])
                ->actions([Action::make('viewLine')->label('View')]),
        ])
        ->toHtml();

    expect($html)->toContain("callInfolistAction('viewLine', 0)")
        ->and($html)->toContain("callInfolistAction('viewLine', 1)")
        ->and($html)->toContain('A1')
        ->and($html)->toContain('B2');
});

it('honors a custom flex breakpoint and hides invisible children', function () {
    $html = Infolist::make()
        ->record(['first' => 'Ada', 'secret' => 'hidden-value'])
        ->schema([
            Flex::make()->from('lg')->schema([
                TextEntry::make('first'),
                TextEntry::make('secret')->visible(false),
            ]),
        ])
        ->toHtml();

    expect($html)->toContain('lg:flex-row')
        ->and($html)->toContain('Ada')
        ->and($html)->not->toContain('hidden-value');
});
