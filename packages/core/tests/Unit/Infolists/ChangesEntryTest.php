<?php

declare(strict_types=1);

use NyonCode\WireCore\Infolists\Components\ChangesEntry;

/*
 * A before-and-after diff as one table.
 *
 * The shape a reader of an audit entry is actually after. What it replaced was a
 * repeated card per field, which said the same thing and repeated its three
 * headings once per row — the work the diff exists to have already done.
 *
 * It knows nothing about auditing on purpose: `Infolists` and `Audit` are both
 * L2 and may not see each other, and a diff of two arrays is not an audit
 * concept anyway.
 */

it('reads the old/new pairs an audit entry produces', function () {
    $entry = ChangesEntry::make('changes')->record([
        'changes' => [
            'status' => ['old' => 'draft', 'new' => 'sent'],
            'paid' => ['old' => false, 'new' => true],
        ],
    ]);

    expect($entry->getRows())->toBe([
        ['field' => 'status', 'before' => 'draft', 'after' => 'sent'],
        // Through ChangeSet, so a boolean is not `1` here either.
        ['field' => 'paid', 'before' => 'false', 'after' => 'true'],
    ]);
});

it('takes rows that are already field/before/after', function () {
    // A caller that has already built them — the audit module does, because its
    // list column needs the field names on their own.
    $rows = [
        ['field' => 'total', 'before' => '100', 'after' => '250'],
    ];

    expect(ChangesEntry::make('changes')->record(['changes' => $rows])->getRows())->toBe($rows);
});

it('renumbers rows it was handed under other keys', function () {
    // A caller filtering a list leaves holes in the keys; a view loops them and
    // a gap would be a row drawn under the wrong index.
    $rows = [3 => ['field' => 'total', 'before' => null, 'after' => '250']];

    expect(array_keys(ChangesEntry::make('changes')->record(['changes' => $rows])->getRows()))->toBe([0]);
});

it('has nothing to draw for a state that is not a diff', function () {
    expect(ChangesEntry::make('changes')->record(['changes' => null])->getRows())->toBe([])
        ->and(ChangesEntry::make('changes')->record(['changes' => []])->getRows())->toBe([])
        ->and(ChangesEntry::make('changes')->record(['changes' => 'nope'])->getRows())->toBe([]);
});

it('renders the diff as one table with a row per field', function () {
    $html = (string) ChangesEntry::make('changes')
        ->label('Changes')
        ->record(['changes' => ['status' => ['old' => 'draft', 'new' => 'sent']]]);

    expect($html)->toContain('<table')
        ->toContain('status')
        ->toContain('draft')
        ->toContain('sent')
        // One header row for the whole diff, not one per field.
        ->and(substr_count($html, '<thead'))->toBe(1);
});

it('says the old side is empty rather than drawing a blank cell', function () {
    // "Was nothing, now set" is one of the two rows a reader looks for first,
    // and a blank cell is indistinguishable from a value that failed to render.
    $html = (string) ChangesEntry::make('changes')
        ->record(['changes' => ['note' => ['old' => null, 'new' => 'hello']]]);

    expect($html)->toContain(__('wire-core::audit.empty'));
});

it('draws its placeholder when nothing moved', function () {
    $html = (string) ChangesEntry::make('changes')
        ->placeholder('No changes recorded')
        ->record(['changes' => []]);

    expect($html)->toContain('No changes recorded')
        ->not->toContain('<table');
});

it('can be drawn a size smaller for a diff inside something else', function () {
    // The trail slide-over draws it as supporting evidence; a page draws it as
    // the subject. Same table, two sizes.
    expect(ChangesEntry::make('c')->isDense())->toBeFalse()
        ->and(ChangesEntry::make('c')->dense()->isDense())->toBeTrue()
        ->and(ChangesEntry::make('c')->dense()->dense(false)->isDense())->toBeFalse();

    $dense = (string) ChangesEntry::make('c')->dense()
        ->record(['c' => ['a' => ['old' => 1, 'new' => 2]]]);

    expect($dense)->toContain('text-xs');
});
