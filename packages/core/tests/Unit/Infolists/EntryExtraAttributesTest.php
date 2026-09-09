<?php

declare(strict_types=1);

use NyonCode\WireCore\Infolists\Components\ChangesEntry;
use NyonCode\WireCore\Infolists\Components\ColorEntry;
use NyonCode\WireCore\Infolists\Components\HtmlEntry;
use NyonCode\WireCore\Infolists\Components\IconEntry;
use NyonCode\WireCore\Infolists\Components\ImageEntry;
use NyonCode\WireCore\Infolists\Components\KeyValueEntry;
use NyonCode\WireCore\Infolists\Components\ListEntry;
use NyonCode\WireCore\Infolists\Components\RepeatableEntry;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Infolist;

/**
 * `extraAttributes()` is on every component through `Component`, and until now
 * it reached the markup on exactly one family: form fields, because
 * `field-wrapper-start` carried the only copy of the loop that renders it.
 *
 * On an infolist entry the setter existed, returned `$this`, and did nothing —
 * the same silent shape `HasExtraAttributes`'s own docblock records having been
 * caught once before. These assert the markup, not the getter, because the
 * getter was never the broken half.
 */
$record = [
    'name' => 'Ada',
    'colour' => '#ff0000',
    'avatar' => 'https://example.test/a.png',
    'verified' => true,
    'meta' => ['a' => 'b'],
    'tags' => ['x', 'y'],
    'lines' => [['label' => 'one'], ['label' => 'two']],
    'body' => '<em>hi</em>',
    'changes' => ['name' => ['before' => 'A', 'after' => 'B']],
];

dataset('entries', [
    'text' => fn () => TextEntry::make('name'),
    'colour' => fn () => ColorEntry::make('colour'),
    'icon' => fn () => IconEntry::make('verified'),
    'image' => fn () => ImageEntry::make('avatar'),
    'key-value' => fn () => KeyValueEntry::make('meta'),
    'list' => fn () => ListEntry::make('tags'),
    'html' => fn () => HtmlEntry::make('body'),
    'repeatable' => fn () => RepeatableEntry::make('lines')->schema([TextEntry::make('label')]),
    'changes' => fn () => ChangesEntry::make('changes'),
]);

it('renders extraAttributes onto the entry root', function (Closure $make) use ($record) {
    $html = Infolist::make()
        ->record($record)
        ->schema([$make()->extraAttributes(['data-probe' => 'yes'])])
        ->toHtml();

    expect($html)->toContain('data-probe="yes"');
})->with('entries');

it('adds nothing when none were given', function () use ($record) {
    $html = Infolist::make()->record($record)->schema([TextEntry::make('name')])->toHtml();

    expect($html)->not->toContain('data-probe');
});

it('escapes the value rather than letting it open a tag', function () use ($record) {
    // The attribute is a hook a component may add, never a place to inject markup.
    $html = Infolist::make()
        ->record($record)
        ->schema([TextEntry::make('name')->extraAttributes(['title' => '"><script>x</script>'])])
        ->toHtml();

    expect($html)->not->toContain('<script>x</script>');
});

it('evaluates a closure, the way every other component property does', function () use ($record) {
    $html = Infolist::make()
        ->record($record)
        ->schema([TextEntry::make('name')->extraAttributes(fn (): array => ['data-probe' => 'closure'])])
        ->toHtml();

    expect($html)->toContain('data-probe="closure"');
});
