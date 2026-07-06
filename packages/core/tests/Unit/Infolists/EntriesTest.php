<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Contracts\HasFieldActions;
use NyonCode\WireCore\Foundation\Enums\FontWeight;
use NyonCode\WireCore\Infolists\Components\BadgeEntry;
use NyonCode\WireCore\Infolists\Components\BooleanEntry;
use NyonCode\WireCore\Infolists\Components\ColorEntry;
use NyonCode\WireCore\Infolists\Components\IconEntry;
use NyonCode\WireCore\Infolists\Components\ImageEntry;
use NyonCode\WireCore\Infolists\Components\KeyValueEntry;
use NyonCode\WireCore\Infolists\Components\ListEntry;
use NyonCode\WireCore\Infolists\Components\RepeatableEntry;
use NyonCode\WireCore\Infolists\Components\TextEntry;

// ─── Entry base: state resolution ────────────────────────────────────────────

it('resolves state from the record by name', function () {
    $entry = TextEntry::make('name')->record(['name' => 'Ada']);

    expect($entry->getState())->toBe('Ada');
});

it('resolves dotted relation paths', function () {
    $entry = TextEntry::make('profile.city')->record(['profile' => ['city' => 'London']]);

    expect($entry->getState())->toBe('London');
});

it('falls back to default when state is empty', function () {
    $entry = TextEntry::make('name')->default('N/A')->record(['name' => null]);

    expect($entry->getState())->toBe('N/A');
});

it('resolves state with a custom callback', function () {
    $entry = TextEntry::make('name')
        ->state(fn ($record) => strtoupper($record['name']))
        ->record(['name' => 'ada']);

    expect($entry->getState())->toBe('ADA');
});

it('auto-generates a label from the name', function () {
    expect(TextEntry::make('first_name')->getLabel())->toBe('First Name');
});

// ─── TextEntry: formatting ───────────────────────────────────────────────────

it('formats money', function () {
    $entry = TextEntry::make('price')->money('Kč')->record(['price' => 1234]);

    expect($entry->getFormattedState())->toBe('1 234 Kč');
});

it('formats numbers', function () {
    $entry = TextEntry::make('count')->numeric(2)->record(['count' => 1234.5]);

    expect($entry->getFormattedState())->toBe('1 234,50');
});

it('formats dates', function () {
    $entry = TextEntry::make('created_at')->date('Y-m-d')->record(['created_at' => '2026-06-20 10:00:00']);

    expect($entry->getFormattedState())->toBe('2026-06-20');
});

it('applies a format callback and truncation', function () {
    $entry = TextEntry::make('bio')
        ->formatStateUsing(fn ($state) => strtoupper($state))
        ->limit(5)
        ->record(['bio' => 'hello world']);

    expect($entry->getFormattedState())->toBe('HELLO...');
});

it('returns placeholder for empty state', function () {
    $entry = TextEntry::make('name')->record(['name' => null]);

    expect($entry->getFormattedState())->toBe('-');
});

it('returns each list item formatted', function () {
    $entry = TextEntry::make('tags')->listWithLineBreaks()->record(['tags' => ['a', 'b', 'c']]);

    expect($entry->getFormattedStates())->toBe(['a', 'b', 'c']);
});

it('resolves a dynamic color closure with state', function () {
    $entry = TextEntry::make('status')
        ->badge()
        ->color(fn ($state) => $state === 'active' ? Color::Success : Color::Gray)
        ->record(['status' => 'active']);

    expect($entry->getColor())->toBe('success');
});

it('resolves the text color class through the canonical palette', function () {
    $coloured = TextEntry::make('status')->color(Color::Success)->record(['status' => 'x']);
    $plain = TextEntry::make('status')->record(['status' => 'x']);

    expect($coloured->getTextColorClass())->toBe('text-emerald-600 dark:text-emerald-400')
        ->and($plain->getTextColorClass())->toBe('text-gray-900 dark:text-white');
});

it('resolves the font-weight class, accepting a string or FontWeight enum', function () {
    expect(TextEntry::make('a')->weight('bold')->getWeightClass())->toBe('font-bold')
        ->and(TextEntry::make('a')->weight(FontWeight::SemiBold)->getWeightClass())->toBe('font-semibold')
        ->and(TextEntry::make('a')->weight(null)->getWeightClass())->toBe('font-normal');
});

it('resolves the badge color class through the canonical palette', function () {
    $coloured = TextEntry::make('status')->badge()->color(Color::Danger)->record(['status' => 'x']);
    $plain = TextEntry::make('status')->badge()->record(['status' => 'x']);

    expect($coloured->getBadgeColorClass())->toContain('bg-red-100')
        ->and($plain->getBadgeColorClass())->toContain('bg-gray-100');
});

// ─── IconEntry ───────────────────────────────────────────────────────────────

it('maps boolean state to icon and color', function () {
    $true = IconEntry::make('verified')->boolean()->record(['verified' => true]);
    $false = IconEntry::make('verified')->boolean()->record(['verified' => false]);

    expect($true->getResolvedIcon())->toBe('check-circle')
        ->and($true->getResolvedColor())->toBe('success')
        ->and($false->getResolvedIcon())->toBe('x-circle')
        ->and($false->getResolvedColor())->toBe('danger');
});

it('maps state to icon via a map', function () {
    $entry = IconEntry::make('status')
        ->icons(['draft' => 'pencil', 'published' => 'check'])
        ->record(['status' => 'published']);

    expect($entry->getResolvedIcon())->toBe('check');
});

// ─── ImageEntry ──────────────────────────────────────────────────────────────

it('uses absolute urls verbatim', function () {
    $entry = ImageEntry::make('avatar')->record(['avatar' => 'https://cdn/x.png']);

    expect($entry->getImageUrls())->toBe(['https://cdn/x.png']);
});

it('returns a gallery for array states', function () {
    $entry = ImageEntry::make('photos')->record(['photos' => ['https://a/1.png', 'https://a/2.png']]);

    expect($entry->getImageUrls())->toHaveCount(2);
});

it('falls back to a default image url', function () {
    $entry = ImageEntry::make('avatar')->defaultImageUrl('https://cdn/default.png')->record(['avatar' => null]);

    expect($entry->getImageUrls())->toBe(['https://cdn/default.png']);
});

// ─── KeyValueEntry ───────────────────────────────────────────────────────────

it('returns array state as pairs', function () {
    $entry = KeyValueEntry::make('meta')->record(['meta' => ['a' => 1, 'b' => 2]]);

    expect($entry->getPairs())->toBe(['a' => 1, 'b' => 2]);
});

it('returns no pairs for non-array state', function () {
    $entry = KeyValueEntry::make('meta')->record(['meta' => 'oops']);

    expect($entry->getPairs())->toBe([]);
});

// ─── RepeatableEntry ─────────────────────────────────────────────────────────

it('deep-clones nested repeatable schemas per row (regression guard: shallow clone shared child instances)', function () {
    $entry = RepeatableEntry::make('orders')
        ->schema([
            TextEntry::make('number'),
            RepeatableEntry::make('lines')->schema([TextEntry::make('sku')]),
        ])
        ->record(['orders' => [
            ['number' => 'A-1', 'lines' => [['sku' => 'X'], ['sku' => 'Y']]],
            ['number' => 'A-2', 'lines' => [['sku' => 'Z']]],
        ]]);

    $rows = $entry->getRows();

    // Each row's nested RepeatableEntry is a distinct instance with a distinct
    // inner schema, and resolves its own item's state.
    expect($rows[0][1])->not->toBe($rows[1][1])
        ->and($rows[0][1]->getSchema()[0])->not->toBe($rows[1][1]->getSchema()[0])
        ->and($rows[0][1]->getRows()[1][0]->getState())->toBe('Y')
        ->and($rows[1][1]->getRows()[0][0]->getState())->toBe('Z');
});

it('builds one row per item with item-bound entries', function () {
    $entry = RepeatableEntry::make('items')
        ->schema([TextEntry::make('label')])
        ->record(['items' => [
            ['label' => 'One'],
            ['label' => 'Two'],
        ]]);

    $rows = $entry->getRows();

    expect($rows)->toHaveCount(2)
        ->and($rows[0][0])->toBeInstanceOf(TextEntry::class)
        ->and($rows[0][0]->getState())->toBe('One')
        ->and($rows[1][0]->getState())->toBe('Two');
});

it('returns no rows for empty repeatable state', function () {
    $entry = RepeatableEntry::make('items')
        ->schema([TextEntry::make('label')])
        ->record(['items' => []]);

    expect($entry->getRows())->toBe([]);
});

it('renders a swatch value for a color entry', function () {
    $entry = ColorEntry::make('brand')->record(['brand' => '#ff0000']);

    expect($entry->getFormattedState())->toBe('#ff0000');
});

it('color entry is copyable opt-in and uses the color view', function () {
    $entry = ColorEntry::make('brand');

    expect($entry->isCopyable())->toBeFalse()
        ->and($entry->copyable()->isCopyable())->toBeTrue()
        ->and($entry->copyable(false)->isCopyable())->toBeFalse()
        ->and($entry->render()->name())->toBe('wire-core::infolists.entries.color');
});

// ─── Entry actions ───────────────────────────────────────────────────────────

it('has no actions by default', function () {
    $entry = TextEntry::make('email');

    expect($entry)->toBeInstanceOf(HasFieldActions::class)
        ->and($entry->hasActions())->toBeFalse()
        ->and($entry->getActions())->toBe([]);
});

it('stores a list of inline actions', function () {
    $action = Action::make('copy');
    $entry = TextEntry::make('email')->actions([$action]);

    expect($entry->getActions())->toBe([$action])
        ->and($entry->hasActions())->toBeTrue();
});

it('appends a single action with action()', function () {
    $entry = TextEntry::make('email')
        ->action(Action::make('a'))
        ->action(Action::make('b'));

    expect($entry->getActions())->toHaveCount(2);
});

it('filters hidden actions out of getActions()', function () {
    $visible = Action::make('visible');
    $hidden = Action::make('hidden')->hidden();
    $entry = TextEntry::make('email')->actions([$visible, $hidden]);

    expect($entry->getActions())->toBe([$visible])
        ->and($entry->hasActions())->toBeTrue();
});

it('resolves an entry action by name including hidden ones', function () {
    $hidden = Action::make('hidden')->hidden();
    $entry = TextEntry::make('email')->actions([Action::make('visible'), $hidden]);

    expect($entry->getFieldAction('hidden'))->toBe($hidden)
        ->and($entry->getFieldAction('missing'))->toBeNull();
});

// ─── BadgeEntry ──────────────────────────────────────────────────────────────

it('badge entry renders as a badge by default and reuses the text view', function () {
    $entry = BadgeEntry::make('status')->record(['status' => 'active']);

    expect($entry->isBadge())->toBeTrue()
        ->and($entry->getFormattedState())->toBe('active')
        ->and($entry->render()->name())->toBe('wire-core::infolists.entries.text');
});

it('badge entry keeps the canonical color vocabulary', function () {
    $entry = BadgeEntry::make('status')->color('success');

    expect($entry->getBadgeColorClass())->toBe(TextEntry::make('x')->color('success')->badge()->getBadgeColorClass());
});

// ─── BooleanEntry ────────────────────────────────────────────────────────────

it('boolean entry maps truthy/falsy state to check/x icons', function () {
    expect(BooleanEntry::make('active')->record(['active' => true])->getResolvedIcon())->toBe('check-circle')
        ->and(BooleanEntry::make('active')->record(['active' => false])->getResolvedIcon())->toBe('x-circle')
        ->and(BooleanEntry::make('active')->record(['active' => true])->render()->name())->toBe('wire-core::infolists.entries.icon');
});

it('boolean entry honors custom true/false icons and colors', function () {
    $entry = BooleanEntry::make('active')
        ->trueIcon('star')
        ->falseIcon('minus')
        ->trueColor('primary')
        ->falseColor('gray');

    expect($entry->record(['active' => 1])->getResolvedIcon())->toBe('star')
        ->and($entry->record(['active' => 0])->getResolvedIcon())->toBe('minus')
        ->and($entry->record(['active' => 1])->getResolvedColor())->toBe('primary');
});

// ─── ListEntry ───────────────────────────────────────────────────────────────

it('list entry formats an array state into items', function () {
    $entry = ListEntry::make('tags')->record(['tags' => ['a', 'b', 'c']]);

    expect($entry->getItems())->toBe(['a', 'b', 'c'])
        ->and($entry->render()->name())->toBe('wire-core::infolists.entries.list');
});

it('list entry splits a string state on a separator', function () {
    $entry = ListEntry::make('tags')->separator(',')->record(['tags' => 'a, b ,c']);

    expect($entry->getItems())->toBe(['a', 'b', 'c']);
});

it('list entry skips empty items', function () {
    $entry = ListEntry::make('tags')->record(['tags' => ['a', '', null, 'b']]);

    expect($entry->getItems())->toBe(['a', 'b']);
});

it('list entry caps visible items and reports the remainder', function () {
    $entry = ListEntry::make('tags')->limitList(2)->record(['tags' => ['a', 'b', 'c', 'd']]);

    expect($entry->getVisibleItems())->toBe(['a', 'b'])
        ->and($entry->getRemainingCount())->toBe(2)
        ->and($entry->getLimitList())->toBe(2);
});

it('list entry has no remainder without a limit', function () {
    $entry = ListEntry::make('tags')->record(['tags' => ['a', 'b']]);

    expect($entry->getVisibleItems())->toBe(['a', 'b'])
        ->and($entry->getRemainingCount())->toBe(0);
});

it('list entry yields no items for an empty state', function () {
    expect(ListEntry::make('tags')->record(['tags' => null])->getItems())->toBe([])
        ->and(ListEntry::make('tags')->record(['tags' => ''])->getItems())->toBe([]);
});

// ─── RepeatableEntry per-row actions ─────────────────────────────────────────

it('repeatable re-indexes row items from zero', function () {
    $entry = RepeatableEntry::make('lines')->record(['lines' => [5 => ['a' => 1], 9 => ['a' => 2]]]);

    expect($entry->getRowItems())->toBe([['a' => 1], ['a' => 2]]);
});

it('repeatable yields no row items for a non-iterable state', function () {
    expect(RepeatableEntry::make('lines')->record(['lines' => null])->getRowItems())->toBe([]);
});

it('repeatable stores per-row actions via the shared HasActions vocabulary', function () {
    $action = Action::make('view');
    $entry = RepeatableEntry::make('lines')->actions([$action]);

    expect($entry->getActions())->toBe([$action])
        ->and($entry->getFieldAction('view'))->toBe($action);
});

it('repeatable with() accepts a string or array and merges without duplicates', function () {
    $entry = RepeatableEntry::make('lines')->with('product')->with(['tax', 'product']);

    expect($entry->getWith())->toBe(['product', 'tax']);
});

it('repeatable has no eager-loads by default', function () {
    expect(RepeatableEntry::make('lines')->getWith())->toBe([]);
});

it('repeatable eager-load is a no-op for plain array rows', function () {
    $entry = RepeatableEntry::make('lines')
        ->with('product')
        ->record(['lines' => [['a' => 1], ['a' => 2]]]);

    expect($entry->getRowItems())->toBe([['a' => 1], ['a' => 2]]);
});
