<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Infolists\Components\BadgeEntry;
use NyonCode\WireCore\Infolists\Components\BooleanEntry;
use NyonCode\WireCore\Infolists\Components\IconEntry;
use NyonCode\WireCore\Infolists\Components\TextEntry;

/**
 * §7 render memo for state-driven infolist entries (HasViewRenderCache).
 *
 * Inside a RepeatableEntry the schema is cloned per row, so an instance cache
 * cannot collapse rows. The canonical core concern keys the render on a state
 * signature in a request-scoped static, so rows sharing a low-cardinality state
 * render the entry view ONCE. This mirrors the table's Badge/Icon/Boolean memo.
 *
 * Two guards, per the Rendering standard: a render-count fuse and byte-identity.
 */
beforeEach(function () {
    IconEntry::flushViewRenderCache();
});

/** Force a state without needing a bound Eloquent model. */
function iconEntryFor(string $state): IconEntry
{
    return IconEntry::make('status')
        ->getStateUsing(fn () => $state)
        ->icons(['active' => 'check-circle', 'inactive' => 'x-circle', 'pending' => 'clock'])
        ->colors(['active' => 'success', 'inactive' => 'danger', 'pending' => 'warning']);
}

/** Invoke the protected renderCacheSignature() for gating assertions. */
function signatureOf(object $entry): ?string
{
    return (fn () => $this->renderCacheSignature())->call($entry);
}

it('collapses rows sharing a state to ONE view render (the fuse)', function () {
    $renders = 0;
    View::composer('wire-core::infolists.entries.icon', function () use (&$renders) {
        $renders++;
    });

    // 60 "rows", cloned per row exactly like RepeatableEntry, over 3 states.
    $states = ['active', 'inactive', 'pending'];
    $html = [];
    foreach (range(1, 60) as $i) {
        $clone = clone iconEntryFor($states[$i % 3]);
        $html[] = (string) $clone;
    }

    // 3 distinct states ⇒ 3 icon-view renders, not 60.
    expect($renders)->toBe(3);
    // And every row actually produced markup.
    expect($html)->toHaveCount(60);
});

it('flushViewRenderCache clears the shared static so the next render re-renders (the Octane hook)', function () {
    // Regression M13: the memo is a class static that survives across requests in
    // a long-lived Octane worker. The RequestTerminated hook calls
    // flushViewRenderCache(); this proves the flush actually empties the store.
    IconEntry::flushViewRenderCache();
    $renders = 0;
    View::composer('wire-core::infolists.entries.icon', function () use (&$renders) {
        $renders++;
    });

    (string) clone iconEntryFor('active');
    (string) clone iconEntryFor('active');
    expect($renders)->toBe(1); // second render served from the memo

    IconEntry::flushViewRenderCache();
    (string) clone iconEntryFor('active');
    expect($renders)->toBe(2); // memo cleared ⇒ renders again
});

it('is byte-identical to the un-memoised render for the same state', function () {
    $memoised = (string) clone iconEntryFor('active');

    IconEntry::flushViewRenderCache();
    // A fresh instance with an empty cache renders directly — must match byte-for-byte.
    $fresh = (fn () => $this->render()->render())->call(clone iconEntryFor('active'));

    expect($memoised)->toBe($fresh);
});

it('keeps distinct states distinct (signature is complete, not over-collapsing)', function () {
    $active = (string) clone iconEntryFor('active');
    $inactive = (string) clone iconEntryFor('inactive');

    // Distinct states must NOT collapse: different icon body + different color class.
    expect($active)->not->toBe($inactive)
        ->and($active)->toContain('<svg')
        ->and($inactive)->toContain('<svg')
        ->and($active)->toContain(IconEntry::make('x')->getStateUsing(fn () => 'active')
        ->colors(['active' => 'success'])->getIconColorClass())
        ->and($inactive)->toContain(IconEntry::make('x')->getStateUsing(fn () => 'inactive')
        ->colors(['inactive' => 'danger'])->getIconColorClass());
});

it('memoises boolean entries to at most two renders', function () {
    BooleanEntry::flushViewRenderCache();
    $renders = 0;
    View::composer('wire-core::infolists.entries.icon', function () use (&$renders) {
        $renders++;
    });

    foreach (range(1, 40) as $i) {
        $entry = BooleanEntry::make('flag')->getStateUsing(fn () => $i % 2 === 0);
        (string) clone $entry;
    }

    expect($renders)->toBe(2);
});

it('memoises badge text entries but NOT plain/copyable ones', function () {
    // Badge: low-cardinality categorical value ⇒ cacheable (non-null signature).
    $badge = BadgeEntry::make('status')->getStateUsing(fn () => 'active');
    expect(signatureOf($badge))->not->toBeNull();

    // Plain text: content-driven, unique per row ⇒ opt out (null signature).
    $plain = TextEntry::make('name')->getStateUsing(fn () => 'Ada Lovelace');
    expect(signatureOf($plain))->toBeNull();

    // Copyable text: also content-driven ⇒ opt out.
    $copyable = TextEntry::make('email')->copyable()->getStateUsing(fn () => 'a@b.c');
    expect(signatureOf($copyable))->toBeNull();
});

it('opts out of caching when the entry carries actions (per-record wiring)', function () {
    $plain = iconEntryFor('active');
    expect(signatureOf($plain))->not->toBeNull();

    $withAction = iconEntryFor('active')
        ->actions([Action::make('ping')]);
    expect(signatureOf($withAction))->toBeNull();
});
