<?php

declare(strict_types=1);

use NyonCode\WireTable\Exceptions\TableConfigurationException;
use NyonCode\WireTable\Support\TableGestures;

/**
 * The gesture vocabulary itself — what a table is allowed to offer, before any
 * table's own shape is taken into account.
 */
it('leaves the two loudest gestures off in the shipped default', function () {
    // What a table gets without saying anything: no keyboard navigation (which
    // would take the rows into the tab order and answer arrows) and no drag
    // sweep (which people find by accident). The rest need an invitation of
    // their own anyway.
    $gestures = TableGestures::defaults();

    expect($gestures->allowsKeyboard())->toBeFalse()
        ->and($gestures->allowsDragSelect())->toBeFalse()
        ->and($gestures->allowsRangeSelection())->toBeTrue()
        ->and($gestures->allowsContextMenu())->toBeTrue()
        ->and($gestures->allowsShortcutHelp())->toBeTrue()
        ->and($gestures->allowsFillHandle())->toBeTrue();
});

it('allows everything once asked, with the keyboard left to the table', function () {
    $gestures = TableGestures::all();

    expect($gestures->allowsKeyboard())->toBeNull()
        ->and($gestures->allowsRangeSelection())->toBeTrue()
        ->and($gestures->allowsDragSelect())->toBeTrue()
        ->and($gestures->allowsContextMenu())->toBeTrue()
        ->and($gestures->allowsShortcutHelp())->toBeTrue()
        ->and($gestures->allowsFillHandle())->toBeTrue();
});

it('switches the whole layer off', function () {
    $gestures = TableGestures::none();

    expect($gestures->allowsKeyboard())->toBeFalse()
        ->and($gestures->allowsRangeSelection())->toBeFalse()
        ->and($gestures->allowsDragSelect())->toBeFalse()
        ->and($gestures->allowsContextMenu())->toBeFalse()
        ->and($gestures->allowsShortcutHelp())->toBeFalse()
        ->and($gestures->allowsFillHandle())->toBeFalse();
});

it('configures one capability at a time, fluently', function () {
    $gestures = TableGestures::all()
        ->keyboard(true)
        ->dragSelect(false)
        ->rangeSelection()
        ->contextMenu(false)
        ->shortcutHelp(false)
        ->fillHandle(false);

    expect($gestures->allowsKeyboard())->toBeTrue()
        ->and($gestures->allowsDragSelect())->toBeFalse()
        ->and($gestures->allowsRangeSelection())->toBeTrue()
        ->and($gestures->allowsContextMenu())->toBeFalse()
        ->and($gestures->allowsShortcutHelp())->toBeFalse()
        ->and($gestures->allowsFillHandle())->toBeFalse();

    // Three-state: null hands the keyboard decision back to the table.
    expect(TableGestures::none()->keyboard(null)->allowsKeyboard())->toBeNull();
});

it('reads the project default from config', function () {
    // null (or a missing key) is the shipped default, true is everything,
    // false is nothing.
    expect(TableGestures::fromConfig(null)->allowsDragSelect())->toBeFalse()
        ->and(TableGestures::fromConfig(null)->allowsKeyboard())->toBeFalse()
        ->and(TableGestures::fromConfig(true)->allowsDragSelect())->toBeTrue()
        ->and(TableGestures::fromConfig(true)->allowsKeyboard())->toBeNull()
        ->and(TableGestures::fromConfig(false)->allowsDragSelect())->toBeFalse()
        ->and(TableGestures::fromConfig(false)->allowsKeyboard())->toBeFalse();
});

it('reads a mixed default, accepting the snake_case config idiom', function () {
    $gestures = TableGestures::fromConfig([
        'keyboard' => true,
        'drag_select' => true,
        'range-selection' => false,
    ]);

    expect($gestures->allowsKeyboard())->toBeTrue()
        ->and($gestures->allowsDragSelect())->toBeTrue()
        ->and($gestures->allowsRangeSelection())->toBeFalse()
        // Untouched capabilities keep what the shipped default gave them.
        ->and($gestures->allowsContextMenu())->toBeTrue();
});

it('refuses an unknown capability instead of silently ignoring it', function () {
    TableGestures::fromConfig(['drag-selct' => false]);
})->throws(TableConfigurationException::class, 'Unknown table gesture [drag-selct]');

it('exposes the whole set as data', function () {
    expect(TableGestures::defaults()->toArray())->toBe([
        'keyboard' => false,
        'rangeSelection' => true,
        'dragSelect' => false,
        'contextMenu' => true,
        'shortcutHelp' => true,
        'fillHandle' => true,
    ]);

    expect(TableGestures::all()->toArray())->toBe([
        'keyboard' => null,
        'rangeSelection' => true,
        'dragSelect' => true,
        'contextMenu' => true,
        'shortcutHelp' => true,
        'fillHandle' => true,
    ]);
});
