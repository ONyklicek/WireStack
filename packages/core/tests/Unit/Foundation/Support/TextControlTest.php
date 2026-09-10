<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Support\TextControl;

/** Where this repository keeps its packages, from inside one of them. */
function packagesDir(): string
{
    return dirname(__DIR__, 5);
}

// ─── The vocabulary ────────────────────────────────────────────────

test('the base carries every always-on group, in the established order', function () {
    // Pinned as one string on purpose: this is the exact class attribute
    // `wire-forms` rendered before the vocabulary moved here, and pinning it is
    // what makes the migration checkable byte for byte rather than by eye.
    expect(TextControl::base())->toBe(
        'block w-full rounded-md border-gray-300 shadow-sm '
        .'focus:border-primary-500 focus:ring-primary-500 '
        .'placeholder:text-gray-400 dark:placeholder:text-gray-500 '
        .'hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150 '
        .'dark:bg-gray-800 dark:border-gray-600 dark:text-white text-sm'
    );
});

test('a rejected value gets red border and red ring, and nothing else', function () {
    expect(TextControl::rejected())->toBe('border-red-500 focus:border-red-500 focus:ring-red-500');
});

test('the rejected group is not already in the base', function () {
    // The two are applied together under a condition, so an overlap would mean
    // a control that can never lose the red once it has had it.
    expect(TextControl::base())->not->toContain('border-red-500');
});

// ─── The Tailwind trap this class exists to close ──────────────────

test('every utility is literal source text a scanner can find', function () {
    $source = file_get_contents((new ReflectionClass(TextControl::class))->getFileName());

    // Interpolation is the defect: Tailwind reads source, so `border-{$shade}`
    // is never generated and the control renders unstyled.
    foreach (preg_split('/\s+/', TextControl::base().' '.TextControl::rejected()) as $utility) {
        expect($source)->toContain($utility);
    }
});

// ─── The consumers ─────────────────────────────────────────────────

test('the view that renders a text control reads the owner instead of spelling the list out', function () {
    // One consumer, where there were two. The signed-out screens had a copy of
    // this list that had already drifted; they now render `wire-forms`' own
    // field (ADR 0036 §7), so the vocabulary has nowhere left to fork to.
    $markup = file_get_contents(packagesDir().'/forms/resources/views/components/text-input.blade.php');

    expect($markup)->toContain('TextControl::base()')
        ->and($markup)->toContain('TextControl::rejected()')
        // The copy that used to live here, in the spelling both views had.
        ->and($markup)->not->toContain('focus:border-primary-500 focus:ring-primary-500');
});

test('the signed-out screens carry no copy of it at all', function () {
    // Where the drift was found. Those views used to spell this list out; they
    // now render `wire-forms`' own field (ADR 0036 §7), so there is no second
    // copy left to diverge — not even one reading the owner.
    $copies = [];

    foreach (glob(packagesDir().'/module-auth/resources/views/*.blade.php') ?: [] as $view) {
        if (str_contains((string) file_get_contents($view), 'focus:border-primary-500 focus:ring-primary-500')) {
            $copies[] = basename($view);
        }
    }

    expect($copies)->toBe([]);
});
