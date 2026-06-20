<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Infolists\Components\ColorEntry;
use NyonCode\WireCore\Infolists\Components\IconEntry;
use NyonCode\WireCore\Infolists\Components\ImageEntry;
use NyonCode\WireCore\Infolists\Components\KeyValueEntry;
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
