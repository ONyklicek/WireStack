<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\BooleanColumn;
use NyonCode\WireTable\Columns\ButtonColumn;
use NyonCode\WireTable\Columns\CheckboxColumn;
use NyonCode\WireTable\Columns\ColorColumn;
use NyonCode\WireTable\Columns\IconColumn;
use NyonCode\WireTable\Columns\ImageColumn;
use NyonCode\WireTable\Columns\PollColumn;
use NyonCode\WireTable\Columns\RatingColumn;
use NyonCode\WireTable\Columns\SelectColumn;
use NyonCode\WireTable\Columns\SplitColumn;
use NyonCode\WireTable\Columns\StackedColumn;
use NyonCode\WireTable\Columns\TagsColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
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

it('does not let record-derived confirmation text break out of the wire:click attribute', function () {
    // Regression (stored XSS): the wire:click attribute is emitted raw, and
    // addslashes is JS-string escaping, not HTML-attribute escaping — a record
    // name containing "> would terminate the attribute and inject an element.
    $payload = '"><img src=x onerror=alert(document.cookie)>';

    $html = ButtonColumn::make('delete')
        ->buttonLabel('Delete')
        ->action(fn () => null)
        ->requiresConfirmation(true, description: fn ($record) => "Delete {$record->name}?")
        ->renderCell(partialRecord(['id' => 1, 'name' => $payload]));

    // The dangerous sequence is HTML-encoded, so it stays inside the attribute…
    expect($html)->toContain('&quot;')
        ->and($html)->not->toContain('"><img')
        ->and($html)->not->toContain('<img src=x');
});

it('escapes a string primary key in the wire:click attribute', function () {
    $html = ButtonColumn::make('go')
        ->buttonLabel('Go')
        ->action(fn () => null)
        ->renderCell(partialRecord(['id' => '"><script>alert(1)</script>']));

    expect($html)->not->toContain('"><script>')
        ->and($html)->not->toContain('<script>alert(1)');
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

    // The behaviour is bound once for the document by the record-copy bundle, so the
    // cell carries the value on `data-copy` and no Alpine component of its own. The
    // assertion used to look for the string "clipboard", which only ever matched the
    // `navigator.clipboard` call in the inline handler that has now moved out.
    expect($html)->toContain('data-copy="a@b.test"')
        ->and($html)->toContain('data-testid="cell-copy"')
        ->and($html)->toContain('a@b.test')
        ->and($html)->not->toContain('x-data');
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

it('renders ColorColumn as a swatch plus its literal value', function () {
    $html = ColorColumn::make('brand')->renderCell(partialRecord(['brand' => '#1a2b3c']));

    expect($html)->toContain('background-color: #1a2b3c;')
        ->and($html)->toContain('font-mono')
        ->and($html)->toContain('#1a2b3c');
});

it('hides the literal value in swatch-only mode', function () {
    $html = ColorColumn::make('brand')->swatchOnly()->renderCell(partialRecord(['brand' => 'rebeccapurple']));

    expect($html)->toContain('background-color: rebeccapurple;')
        ->and($html)->not->toContain('font-mono');
});

// The swatch is the one cell that interpolates record data into a `style`
// attribute, where e() alone would still let `;` open a second declaration.
it('renders no swatch for a value that is not a css color', function () {
    $html = ColorColumn::make('brand')
        ->renderCell(partialRecord(['brand' => 'red; background-image: url(https://evil.test/x)']));

    expect($html)->not->toContain('background-color')
        ->and($html)->not->toContain('evil.test');
});

it('offers the color value for copying when the column is copyable', function () {
    $html = ColorColumn::make('brand')->copyable()->renderCell(partialRecord(['brand' => '#0f0']));

    expect($html)->toContain('data-testid="cell-copy"')
        ->and($html)->toContain('#0f0');
});

it('renders CheckboxColumn through its partial', function () {
    $html = CheckboxColumn::make('active')->renderCell(partialRecord(['id' => 7, 'active' => true]));

    expect($html)->toContain('type="checkbox"')
        ->and($html)->toContain('wireEditableCell(')
        ->and($html)->toContain('commit($event.target.checked)')
        ->and($html)->toContain('data-record-key="7"')
        ->and($html)->toContain('data-column-name="active"');
});

it('does not wire a disabled checkbox cell for editing', function () {
    $record = partialRecord(['id' => 7, 'active' => true]);
    $column = CheckboxColumn::make('active')->disabled(fn () => true);

    expect($column->renderCell($record))->toContain('disabled')
        ->and($column->renderCell($record))->not->toContain('commit($event.target.checked)')
        // The client-side disabled state is cosmetic; the server must refuse too.
        ->and($column->canEdit($record))->toBeFalse();
});

it('renders RatingColumn as filled and empty stars', function () {
    $html = RatingColumn::make('score')->renderCell(partialRecord(['score' => 3]));

    // 3 filled + 2 empty over the default max of 5.
    expect(substr_count($html, '<svg'))->toBe(5)
        ->and($html)->toContain('aria-label="3 out of 5"');
});

it('clips a half star only when half precision is allowed', function () {
    $record = partialRecord(['score' => 2.5]);

    expect(RatingColumn::make('score')->allowHalf()->renderCell($record))->toContain('w-1/2 overflow-hidden')
        ->and(RatingColumn::make('score')->renderCell($record))->not->toContain('w-1/2 overflow-hidden');
});

it('honours max, color and the numeric value on a rating column', function () {
    $html = RatingColumn::make('score')->max(3)->color('danger')->showValue()
        ->renderCell(partialRecord(['score' => 2]));

    expect(substr_count($html, '<svg'))->toBe(3)
        ->and($html)->toContain('text-red-600')
        ->and($html)->toContain('>2<');
});

it('renders the empty cell text for a non-numeric rating', function () {
    expect(RatingColumn::make('score')->renderCell(partialRecord(['score' => null])))
        ->toBe(RatingColumn::make('score')->getEmptyCellText());
});

it('renders TagsColumn chips from an array state', function () {
    $html = TagsColumn::make('tags')->renderCell(partialRecord(['tags' => ['php', 'laravel']]));

    expect(substr_count($html, 'rounded-full'))->toBe(2)
        ->and($html)->toContain('php')
        ->and($html)->toContain('laravel');
});

it('splits a delimited string state into chips only when a separator is set', function () {
    $record = partialRecord(['tags' => 'php,laravel']);

    expect(substr_count(TagsColumn::make('tags')->separator()->renderCell($record), 'rounded-full'))->toBe(2)
        // Without a separator the value is one tag, not two.
        ->and(substr_count(TagsColumn::make('tags')->renderCell($record), 'rounded-full'))->toBe(1);
});

it('collapses tags beyond the list limit into a +N chip', function () {
    $html = TagsColumn::make('tags')->limitList(2)
        ->renderCell(partialRecord(['tags' => ['a', 'b', 'c', 'd']]));

    expect($html)->toContain('+2')
        ->and($html)->toContain('>a<')
        ->and($html)->not->toContain('>c<');
});

it('colors a tag through the shared state-color vocabulary', function () {
    $html = TagsColumn::make('tags')->colors(['urgent' => 'danger'])
        ->renderCell(partialRecord(['tags' => ['urgent']]));

    expect($html)->toContain('bg-red-100');
});

it('drops blank tags and renders the empty cell text when nothing is left', function () {
    $column = TagsColumn::make('tags')->separator();

    expect($column->renderCell(partialRecord(['tags' => 'php,, ,laravel'])))
        ->toContain('php')->toContain('laravel')
        ->and(substr_count($column->renderCell(partialRecord(['tags' => 'php,, ,laravel'])), 'rounded-full'))->toBe(2)
        ->and($column->renderCell(partialRecord(['tags' => []])))->toBe($column->getEmptyCellText());
});

it('renders chips from an Arrayable relation collection', function () {
    $html = TagsColumn::make('tags')
        ->renderCell(partialRecord(['tags' => collect(['alpha', 'beta'])]));

    expect(substr_count($html, 'rounded-full'))->toBe(2)->and($html)->toContain('alpha');
});

it('names the island every editable cell writes into', function () {
    // A `$wire` call from Alpine carries no DOM origin, so Livewire cannot work
    // out which island it belongs to — an inline save re-rendered the whole
    // component while a sort header inside the same island re-rendered only the
    // table. The cell has to say. Measured on the editable preview: 59,123 B
    // against 42,765 B for the same write.
    //
    // Asserted for all four editable columns, because the one that forgets is the
    // one that quietly costs the most.
    $record = partialRecord(['id' => 1, 'active' => true, 'role' => 'a', 'name' => 'x']);

    expect(ToggleColumn::make('active')->renderCell($record))->toContain("island: 'data-region'")
        ->and(CheckboxColumn::make('active')->renderCell($record))->toContain("island: 'data-region'")
        ->and(TextInputColumn::make('name')->renderCell($record))->toContain("island: 'data-region'")
        ->and(SelectColumn::make('role')->options(['a' => 'A'])->renderCell($record))
        ->toContain("island: 'data-region'");
});
