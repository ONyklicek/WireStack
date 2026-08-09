<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use NyonCode\WireCore\Foundation\View\CopyButton;

/**
 * The canonical copy affordance: one owner for the markup, the behaviour and the
 * feedback pill, reached by a table's copyable cell and an infolist's copyable
 * entry alike.
 *
 * The byte-identity test below is what made the move safe: the table's cell had
 * this exact button before it was hoisted into core, down to the attribute order,
 * and a page that already ships it must not grow by so much as a space.
 */
it('renders the button a table cell already emitted, byte for byte', function () {
    $expected = '<button type="button"'
        .' data-copy="'.e('a"b&<x>').'"'
        .' data-copy-message="'.e('Copied!').'"'
        .' data-testid="cell-copy"'
        .' aria-label="'.e('Copied!').'"'
        .' title="'.e('Copy').'"'
        .' class="'.CopyButton::DEFAULT_CLASS.'">'
        .'ICON</button>';

    $actual = app(CopyButton::class)->render(
        value: 'a"b&<x>',
        message: 'Copied!',
        label: 'Copied!',
        title: 'Copy',
        testId: 'cell-copy',
        icon: 'ICON',
    );

    expect($actual)->toBe($expected);
});

it('escapes the value and the announcement into their attributes', function () {
    $html = app(CopyButton::class)->render(
        value: '<script>"&\'',
        message: 'He said "hi" & left',
        icon: '',
    );

    expect($html)
        ->toContain('data-copy="&lt;script&gt;&quot;&amp;&#039;"')
        ->toContain('data-copy-message="He said &quot;hi&quot; &amp; left"')
        ->not->toContain('<script>');
});

it('falls back to core\'s own strings when a caller gives none', function () {
    $html = app(CopyButton::class)->render('x', icon: '');

    expect($html)->toContain('title="'.__('wire-core::messages.copy').'"')
        ->and($html)->toContain('data-testid="copy"');
});

it('compiles once per shape and splices the rest', function () {
    $count = 0;
    View::composer('*', function () use (&$count): void {
        $count++;
    });

    $button = app(CopyButton::class);

    foreach (range(1, 20) as $i) {
        $button->render(value: 'v'.$i, message: 'Copied!', testId: 'cell-copy', icon: 'ICON');
    }

    // One view for twenty buttons — the point of the owner holding a skeleton. The
    // value is the per-record thing and it is a slot; everything else is the shape.
    expect($count)->toBe(1);

    // A different chrome is a different shape, and gets its own compile.
    $button->render(value: 'x', message: 'Copied!', class: 'other', icon: 'ICON');

    expect($count)->toBe(2);
});

it('treats a per-caller announcement as a new shape, because it is also the label', function () {
    // `aria-label` falls back to the announcement, which is what a table cell has
    // always rendered — so an announcement that varies really is different markup
    // and gets its own skeleton rather than the wrong label. It costs nothing in
    // practice: the table's copy message is a column setting, constant down the
    // page. A caller that genuinely varies it per record should pass `label` too.
    $count = 0;
    View::composer('*', function () use (&$count): void {
        $count++;
    });

    $button = app(CopyButton::class);

    foreach (range(1, 5) as $i) {
        $button->render(value: 'v'.$i, message: 'copied '.$i, icon: 'ICON');
    }

    expect($count)->toBe(5);

    // Pin the label and the five collapse back to one.
    $count = 0;
    foreach (range(1, 5) as $i) {
        $button->render(value: 'w'.$i, message: 'copied '.$i, label: 'Copy', icon: 'ICON');
    }

    expect($count)->toBe(1);
});

it('leaves no slot sentinel behind', function () {
    expect(app(CopyButton::class)->render('v', 'm', icon: ''))->not->toContain('WIRE_SLOT');
});

it('renders one feedback pill however many copyable surfaces a page has', function () {
    // A table includes the assets partial once for the whole table; an infolist
    // includes it per copyable ENTRY, and two entries used to mean two pills. The
    // controller writes into the first one it finds, so the second sat there dead.
    $html = Blade::render(
        "@include('wire-core::partials.copy-assets')@include('wire-core::partials.copy-assets')"
    );

    expect(substr_count($html, 'data-copy-feedback-text'))->toBe(1);
});
