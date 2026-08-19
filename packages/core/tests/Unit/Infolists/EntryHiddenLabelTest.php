<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use NyonCode\WireCore\Infolists\Components\ColorEntry;
use NyonCode\WireCore\Infolists\Components\IconEntry;
use NyonCode\WireCore\Infolists\Components\ImageEntry;
use NyonCode\WireCore\Infolists\Components\KeyValueEntry;
use NyonCode\WireCore\Infolists\Components\ListEntry;
use NyonCode\WireCore\Infolists\Components\RepeatableEntry;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Infolist;

/**
 * `hiddenLabel()` was dead on every infolist and panel entry
 * (architecture/plans/forms-and-surfaces-performance.md step 3): the views gated
 * on `@if($field->getLabel())`, and `HasLabel::getLabel()` never returns null —
 * it falls back to `Str::headline($name)`. So the one documented use case for the
 * method ("the surrounding layout already names the value") did nothing, and
 * `->label('')` was the only way to suppress a heading.
 *
 * Both rules now live on `HasLabel::hasVisibleLabel()` and every entry view gates
 * on that, so this asserts the method reaches the markup. It is a render-count
 * test rather than a string test because the entry label is a shared partial:
 * counting its renders proves the gate is at the call site, which is also what
 * keeps a suppressed label from costing a view render.
 */
beforeEach(function () {
    // IconEntry and badge TextEntry memoise their render request-scoped and shared
    // across clones, so a leftover entry from an earlier test would answer one of
    // the two measurements below from cache and report a bogus slope.
    IconEntry::flushViewRenderCache();
});

function entryLabelRenders(array $schema, array $record): int
{
    $count = 0;

    View::composer('wire-core::partials.entry-label', function () use (&$count): void {
        $count++;
    });

    Infolist::make()->record($record)->schema($schema)->toHtml();

    return $count;
}

$record = [
    'name' => 'Ada',
    'email' => 'ada@example.test',
    'colour' => '#ff0000',
    'avatar' => 'https://example.test/a.png',
    'verified' => true,
    'meta' => ['a' => 'b'],
    'tags' => ['x', 'y'],
    'lines' => [['label' => 'one'], ['label' => 'two']],
];

// Every infolist entry view that renders a label, one case each. RepeatableEntry
// is included because its label is the cheapest lever on its per-row cost: the
// shared include is half of its 6-views-per-row slope.
dataset('labelled entries', [
    'text' => fn () => TextEntry::make('name'),
    'color' => fn () => ColorEntry::make('colour'),
    'icon' => fn () => IconEntry::make('verified'),
    'image' => fn () => ImageEntry::make('avatar'),
    'key-value' => fn () => KeyValueEntry::make('meta'),
    'list' => fn () => ListEntry::make('tags'),
    'repeatable' => fn () => RepeatableEntry::make('lines')->schema([TextEntry::make('label')]),
]);

it('renders the entry label by default', function (Closure $make) use ($record) {
    // The baseline the assertion below is read against: >= 1 rather than exactly 1
    // because a RepeatableEntry also labels each of its children.
    expect(entryLabelRenders([$make()], $record))->toBeGreaterThanOrEqual(1);
})->with('labelled entries');

it('renders no entry label at all under hiddenLabel()', function (Closure $make) use ($record) {
    // Only the entry's OWN label is hidden, so a repeatable's children keep theirs;
    // the count therefore drops by exactly one rather than to zero.
    $shown = entryLabelRenders([$make()], $record);
    IconEntry::flushViewRenderCache();
    $hidden = entryLabelRenders([$make()->hiddenLabel()], $record);

    expect($shown - $hidden)->toBe(1);
})->with('labelled entries');

it('keeps the label resolvable while hiding it', function () {
    // hiddenLabel() is not label(null): the text stays available to anything that
    // wants it for accessibility or a heading elsewhere.
    $entry = TextEntry::make('email_address')->hiddenLabel();

    expect($entry->isLabelHidden())->toBeTrue()
        ->and($entry->getLabel())->toBe('Email Address')
        ->and($entry->hasVisibleLabel())->toBeFalse();
});

it('does not serve a hidden-label entry from a labelled sibling render memo', function () {
    // IconEntry and badge TextEntry memoise their render on a state signature
    // (HasViewRenderCache). The signature listed getLabel() but not the hidden
    // flag, so once the label became a rendered difference the two collapsed onto
    // one cache entry and hiddenLabel() silently drew a label anyway. The memo is
    // request-scoped and shared across clones, so one repeatable mixing the two is
    // enough to hit it.
    IconEntry::flushViewRenderCache();

    $shown = (string) IconEntry::make('verified')->getStateUsing(fn () => true);
    $hidden = (string) IconEntry::make('verified')->getStateUsing(fn () => true)->hiddenLabel();

    expect($shown)->toContain('Verified')
        ->and($hidden)->not->toContain('Verified');

    $shownBadge = (string) TextEntry::make('state')->badge()->getStateUsing(fn () => 'open');
    $hiddenBadge = (string) TextEntry::make('state')->badge()->getStateUsing(fn () => 'open')->hiddenLabel();

    expect($shownBadge)->toContain('State')
        ->and($hiddenBadge)->not->toContain('State');
});

it('treats an empty label as nothing to draw', function () {
    // label('') was the pre-existing hack for "no heading" and must keep working.
    expect(TextEntry::make('name')->label('')->hasVisibleLabel())->toBeFalse()
        ->and(TextEntry::make('name')->hasVisibleLabel())->toBeTrue();
});
