<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\BooleanColumn;
use NyonCode\WireTable\Columns\ButtonColumn;
use NyonCode\WireTable\Columns\IconColumn;
use NyonCode\WireTable\Columns\ImageColumn;
use NyonCode\WireTable\Columns\PollColumn;
use NyonCode\WireTable\Columns\SplitColumn;
use NyonCode\WireTable\Columns\StackedColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\ToggleColumn;

/**
 * End-to-end smoke tests proving each concrete column renders through its
 * Blade partial (tables.columns.*) without errors. Guards against the column
 * class and its partial drifting out of sync.
 */
function partialRecord(array $attributes): Model
{
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill($attributes);

    return $record;
}

it('renders BooleanColumn through its partial', function () {
    $html = BooleanColumn::make('flag')->labels('On', 'Off')->renderCell(partialRecord(['flag' => true]));

    expect($html)->toContain('inline-flex items-center')
        ->and($html)->toContain('<svg')
        ->and($html)->toContain('On');
});

it('renders IconColumn through its partial', function () {
    $html = IconColumn::make('flag')->boolean()->renderCell(partialRecord(['flag' => true]));

    expect($html)->toContain('inline-flex items-center')
        ->and($html)->toContain('<svg');
});

it('renders BadgeColumn through its partial', function () {
    $html = BadgeColumn::make('status')
        ->colors(['active' => 'success'])
        ->renderCell(partialRecord(['status' => 'active']));

    expect($html)->toContain('rounded-full')
        ->and($html)->toContain('bg-emerald-100')
        ->and($html)->toContain('active');
});

it('renders ImageColumn through its partial', function () {
    $html = ImageColumn::make('avatar')->renderCell(partialRecord(['avatar' => 'https://example.test/a.png']));

    expect($html)->toContain('<img')
        ->and($html)->toContain('https://example.test/a.png')
        ->and($html)->toContain('object-cover');
});

it('renders ButtonColumn (button) through its partial', function () {
    $html = ButtonColumn::make('go')
        ->buttonLabel('Go')
        ->action(fn () => null)
        ->renderCell(partialRecord(['id' => 1]));

    expect($html)->toContain('<button')
        ->and($html)->toContain('Go')
        ->and($html)->toContain('wire:click');
});

it('renders ButtonColumn (link) through its partial', function () {
    $html = ButtonColumn::make('go')
        ->buttonLabel('Open')
        ->actionUrl(fn () => 'https://example.test')
        ->renderCell(partialRecord(['id' => 1]));

    expect($html)->toContain('<a')
        ->and($html)->toContain('href="https://example.test"')
        ->and($html)->toContain('Open');
});

it('renders PollColumn (non-polling) through its partial', function () {
    $html = PollColumn::make('status')->renderCell(partialRecord(['status' => 'done']));

    expect($html)->toContain('done');
});

it('renders PollColumn (polling badge) through its partial', function () {
    $html = PollColumn::make('status')
        ->badge()
        ->pollForever()
        ->colors(['processing' => 'info'])
        ->renderCell(partialRecord(['id' => 5, 'status' => 'processing']));

    expect($html)->toContain('wire:poll')
        ->and($html)->toContain('rounded-full')
        ->and($html)->toContain('processing');
});

it('renders SplitColumn through its partial', function () {
    $html = SplitColumn::make('combo')
        ->columns([TextColumn::make('name')])
        ->renderCell(partialRecord(['name' => 'Alice']));

    expect($html)->toContain('flex')
        ->and($html)->toContain('Alice');
});

it('renders StackedColumn through its partial', function () {
    $html = StackedColumn::make('user')
        ->primary('name')
        ->secondary('email')
        ->renderCell(partialRecord(['name' => 'Alice', 'email' => 'alice@example.test']));

    expect($html)->toContain('font-medium')
        ->and($html)->toContain('Alice')
        ->and($html)->toContain('alice@example.test');
});

it('builds escaped stacked line html via getLinesHtml', function () {
    $html = StackedColumn::make('user')->getLinesHtml([
        ['class' => 'font-medium', 'value' => 'Alice'],
        ['class' => 'text-sm', 'value' => '<script>'],
    ])->toHtml();

    expect($html)->toBe('<p class="font-medium">Alice</p><p class="text-sm">&lt;script&gt;</p>');
});

it('renders an empty fragment from getLinesHtml when there are no items', function () {
    expect(StackedColumn::make('user')->getLinesHtml([])->toHtml())->toBe('');
});

it('renders base text cell with styling classes through the text partial', function () {
    $html = TextColumn::make('name')->textColor('danger')->renderCell(partialRecord(['name' => 'Alice']));

    expect($html)->toContain('<span')
        ->and($html)->toContain('text-red-600')
        ->and($html)->toContain('Alice');
});

it('renders base text cell url link through the text partial', function () {
    $html = TextColumn::make('name')
        ->actionUrl(fn () => '/posts/1')
        ->renderCell(partialRecord(['name' => 'Alice']));

    expect($html)->toContain('<a')
        ->and($html)->toContain('href="/posts/1"')
        ->and($html)->toContain('hover:underline');
});

it('renders base text cell copyable through the text partial', function () {
    $html = TextColumn::make('email')->copyable()->renderCell(partialRecord(['email' => 'a@b.test']));

    expect($html)->toContain('x-data')
        ->and($html)->toContain('clipboard')
        ->and($html)->toContain('a@b.test');
});

it('renders base text cell tooltip + description through the text partial', function () {
    $html = TextColumn::make('name')
        ->tooltip('More info')
        ->description('Subtitle')
        ->renderCell(partialRecord(['name' => 'Alice']));

    expect($html)->toContain('cursor-help')
        ->and($html)->toContain('title="More info"')
        ->and($html)->toContain('<p')
        ->and($html)->toContain('Subtitle')
        ->and($html)->toContain('<div>');
});

it('renders base text cell icon through the text partial', function () {
    $html = TextColumn::make('name')->icon('check-circle')->renderCell(partialRecord(['name' => 'Alice']));

    expect($html)->toContain('<svg')
        ->and($html)->toContain('Alice');
});

it('keeps raw markup in html mode through the text partial', function () {
    $html = TextColumn::make('name')
        ->html()
        ->formatStateUsing(fn () => '<b>bold</b>')
        ->renderCell(partialRecord(['name' => 'x']));

    expect($html)->toContain('<b>bold</b>');
});

it('renders ToggleColumn through its partial', function () {
    $html = ToggleColumn::make('active')->renderCell(partialRecord(['id' => 1, 'active' => true]));

    // Optimistic Alpine cell: commit() (which calls $wire.updateTableCell from the
    // shared wireEditableCell component) + record identity via data-attributes.
    expect($html)->toContain('<button')
        ->and($html)->toContain('role="switch"')
        ->and($html)->toContain('wireEditableCell(')
        ->and($html)->toContain('commit(! value)')
        ->and($html)->toContain('data-record-key="1"')
        ->and($html)->toContain('data-column-name="active"');
});

// fontFamily() was a dead setter: getTextClasses() knew about size and weight but
// never the family, so the value went nowhere.
it('renders the text column in its configured font family', function () {
    $record = partialRecord(['name' => 'Ada']);

    expect(TextColumn::make('name')->fontFamily('mono')->renderCell($record))->toContain('font-mono')
        ->and(TextColumn::make('name')->fontFamily('serif')->renderCell($record))->toContain('font-serif')
        ->and(TextColumn::make('name')->renderCell($record))->not->toContain('font-');
});

it('keeps text size and weight when a font family is added', function () {
    // size() is the column's structural size; textSize() is the font size.
    $html = TextColumn::make('name')->textSize('lg')->weight('bold')->fontFamily('mono')
        ->renderCell(partialRecord(['name' => 'Ada']));

    expect($html)->toContain('text-lg')->toContain('font-mono');
});
