<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Foundation\ValueObjects\ShortcutHint;
use NyonCode\WireTable\Support\RecordAction;
use NyonCode\WireTable\Support\TableGestures;
use NyonCode\WireTable\Support\TableShortcutLegend;
use NyonCode\WireTable\Table;

/**
 * The table's keyboard-gesture legend (selection gestures rollout, step 24):
 * data-only ShortcutHint sections assembled from the table's own configuration,
 * rendered later by the `?` help modal.
 */

/** @return array<int, string> */
function legendHeadings(Table $table): array
{
    return array_column($table->shortcutLegend()->sections(), 'heading');
}

/** @return array<int, ShortcutHint> */
function legendSectionHints(Table $table, string $heading): array
{
    foreach ($table->shortcutLegend()->sections() as $section) {
        if ($section['heading'] === $heading) {
            return $section['hints'];
        }
    }

    return [];
}

it('is empty for a plain data table without grid semantics', function () {
    $legend = Table::make()->shortcutLegend();

    expect($legend)->toBeInstanceOf(TableShortcutLegend::class)
        ->and($legend->sections())->toBe([])
        ->and($legend->isEmpty())->toBeTrue();
});

it('stays empty when the keyboard is explicitly opted out', function () {
    $table = Table::make()->selectable()->gestures(fn (TableGestures $g) => $g->keyboard(false));

    expect($table->shortcutLegend()->isEmpty())->toBeTrue();
});

it('lists navigation, selection and help for a selectable-only table', function () {
    $table = Table::make()->selectable();

    expect(legendHeadings($table))->toBe(['Navigation', 'Selection', 'Help']);

    $selection = legendSectionHints($table, 'Selection');
    $allKeys = array_merge(...array_map(fn (ShortcutHint $h): array => $h->keys, $selection));

    expect($allKeys)->toContain('Space')
        ->toContain('shift+ArrowDown')
        ->toContain('mod+shift+ArrowUp')
        ->toContain('shift+Home')
        ->toContain('mod+A');
});

it('lists the selection section for a table that is selectable only through bulk actions', function () {
    // isSelectable() is `selectable || bulkActions`, so a table that never
    // called ->selectable() still gets the selection gestures — and must
    // therefore document them.
    $table = Table::make()->bulkActions([BulkAction::make('archive')->label('Archive')]);

    expect($table->isSelectable())->toBeTrue()
        ->and(legendHeadings($table))->toBe(['Navigation', 'Selection', 'Help']);
});

it('lists navigation, actions and help for a record-action table — no selection section', function () {
    $table = Table::make()->recordAction(Action::make('view')->label('Open record'));

    expect(legendHeadings($table))->toBe(['Navigation', 'Actions', 'Help']);

    $actions = legendSectionHints($table, 'Actions');

    // Enter runs the single pointer binding; no Shift+Enter without a second one.
    expect($actions[0]->keys)->toBe(['Enter'])
        ->and($actions[0]->description)->toBe('Run “Open record”')
        ->and(array_column($actions, 'keys'))->not->toContain(['shift+Enter']);

    // The context-menu row pairs the key with its Shift+F10 alias.
    $menu = array_values(array_filter($actions, fn (ShortcutHint $h): bool => in_array('ContextMenu', $h->keys, true)));
    expect($menu)->toHaveCount(1)
        ->and($menu[0]->keys)->toBe(['ContextMenu', 'shift+F10']);
});

it('adds Shift+Enter only when a secondary pointer binding exists', function () {
    $table = Table::make()->recordActions([
        RecordAction::make(Action::make('preview')->label('Preview'))->onClick(),
        RecordAction::make(Action::make('edit')->label('Edit'))->onDoubleClick(),
    ]);

    $actions = legendSectionHints($table, 'Actions');
    $byKeys = [];

    foreach ($actions as $hint) {
        $byKeys[implode('|', $hint->keys)] = $hint->description;
    }

    expect($byKeys['Enter'])->toBe('Run “Edit”')          // double-click binding is primary
        ->and($byKeys['shift+Enter'])->toBe('Run “Preview”');
});

it('projects an onKey() binding and aliases Delete with Backspace', function () {
    $table = Table::make()->recordAction(
        RecordAction::make(Action::make('purge')->label('Purge'))->onKey('Delete'),
    );

    $actions = legendSectionHints($table, 'Actions');
    $purge = array_values(array_filter($actions, fn (ShortcutHint $h): bool => $h->description === 'Run “Purge”'));

    expect($purge)->toHaveCount(1)
        ->and($purge[0]->keys)->toBe(['Delete', 'Backspace']);
});

it('deduplicates keys inside a row — an explicit Backspace next to Delete stays single', function () {
    $table = Table::make()->recordAction(
        RecordAction::make(Action::make('purge')->label('Purge'))->onKey('Delete')->onKey('Backspace'),
    );

    $actions = legendSectionHints($table, 'Actions');
    $purge = array_values(array_filter($actions, fn (ShortcutHint $h): bool => $h->description === 'Run “Purge”'));

    expect($purge)->toHaveCount(1)
        ->and($purge[0]->keys)->toBe(['Delete', 'Backspace']);
});

it('groups several keys of one action into a single row', function () {
    $table = Table::make()->recordAction(
        RecordAction::make(Action::make('flag')->label('Flag'))->onKey('x')->onKey('mod+x'),
    );

    $actions = legendSectionHints($table, 'Actions');
    $flag = array_values(array_filter($actions, fn (ShortcutHint $h): bool => $h->description === 'Run “Flag”'));

    expect($flag)->toHaveCount(1)
        ->and($flag[0]->keys)->toBe(['x', 'mod+x']);
});

it('falls back to a headline label for a name-only reference', function () {
    // A reference to an action that is not registered anywhere still reads as
    // a human label, never as the raw name.
    $table = Table::make()->recordAction('export-csv');

    $actions = legendSectionHints($table, 'Actions');

    expect($actions[0]->description)->toBe('Run “Export Csv”');
});

it('localizes headings and descriptions', function () {
    app()->setLocale('cs');

    try {
        $table = Table::make()->selectable();

        expect(legendHeadings($table))->toBe(['Navigace', 'Výběr', 'Nápověda']);

        $selection = legendSectionHints($table, 'Výběr');
        $descriptions = array_map(fn (ShortcutHint $h): string => $h->description, $selection);

        expect($descriptions)->toContain('Vybrat celou stránku');
    } finally {
        app()->setLocale('en');
    }
});

it('always closes with the ? help row', function () {
    $help = legendSectionHints(Table::make()->selectable(), 'Help');

    expect($help)->toHaveCount(1)
        ->and($help[0]->keys)->toBe(['?'])
        ->and($help[0]->description)->toBe('Show keyboard shortcuts');
});
