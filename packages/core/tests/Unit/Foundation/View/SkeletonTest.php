<?php

declare(strict_types=1);

use Illuminate\Contracts\Support\Htmlable;
use NyonCode\WireCore\Exceptions\SkeletonSlotException;
use NyonCode\WireCore\Foundation\Contracts\WireException;
use NyonCode\WireCore\Foundation\View\Skeleton;

/**
 * Contract for the render-once/splice-per-row primitive
 * (architecture/plans/render-engine-htmlable-first.md §7).
 *
 * Its callers prove byte-identity against the slow render they replace — that is
 * what `TextColumnSkeletonTest` is for. These cover the properties those tests rely
 * on but cannot see: that a sentinel survives the escaping a template applies to its
 * own inputs, that substitution does not cascade, and that an unfilled hole fails
 * loudly instead of shipping its placeholder to the browser.
 */
it('substitutes a slot with the value given for it', function () {
    $skeleton = Skeleton::compile('<p>'.Skeleton::slot('body').'</p>', 'body');

    expect($skeleton->fill(['body' => 'hello']))->toBe('<p>hello</p>');
});

it('substitutes several slots in one pass', function () {
    $skeleton = Skeleton::compile(
        '<a href="'.Skeleton::slot('url').'">'.Skeleton::slot('label').'</a>',
        'url', 'label',
    );

    expect($skeleton->fill(['url' => '/x', 'label' => 'Go']))->toBe('<a href="/x">Go</a>');
});

it('survives the escaping a template applies to its own inputs', function () {
    // The whole design rests on this: a partial that does `e($url)` must hand back
    // the sentinel unchanged, or the compiled template would hold something fill()
    // can no longer find.
    expect(e(Skeleton::slot('url')))->toBe(Skeleton::slot('url'));
});

it('does not re-examine what it just substituted', function () {
    // A value that happens to contain another slot's sentinel is data, not a hole.
    // Sequential str_replace calls would substitute it a second time; one strtr
    // pass does not, and this is why fill() is a single call.
    $skeleton = Skeleton::compile(Skeleton::slot('a').'|'.Skeleton::slot('b'), 'a', 'b');

    expect($skeleton->fill(['a' => Skeleton::slot('b'), 'b' => 'B']))
        ->toBe(Skeleton::slot('b').'|B');
});

it('ignores a slot the template does not contain', function () {
    // A shape that omits a part (no url on this row) must not demand a value for it.
    $skeleton = Skeleton::compile('<p>'.Skeleton::slot('body').'</p>', 'body', 'url');

    expect($skeleton->fill(['body' => 'x']))->toBe('<p>x</p>');
});

it('trims the compiled template', function () {
    expect(Skeleton::compile("\n  <p>x</p>  \n")->toHtml())->toBe('<p>x</p>');
});

it('returns the template unchanged when it has no slots', function () {
    expect(Skeleton::compile('<p>static</p>')->fill(['unused' => 'x']))->toBe('<p>static</p>');
});

it('exposes the unfilled chrome as Htmlable', function () {
    $skeleton = Skeleton::compile('<p>'.Skeleton::slot('body').'</p>', 'body');

    expect($skeleton)->toBeInstanceOf(Htmlable::class)
        ->and($skeleton->toHtml())->toBe('<p>'.Skeleton::slot('body').'</p>');
});

it('refuses to render a hole it was given no value for', function () {
    $skeleton = Skeleton::compile('<p>'.Skeleton::slot('body').'</p>', 'body');

    expect(fn () => $skeleton->fill([]))
        ->toThrow(SkeletonSlotException::class, 'Skeleton slot [body]');
});

it('marks its failure as a wire exception', function () {
    expect(SkeletonSlotException::missing('body'))
        ->toBeInstanceOf(WireException::class)
        ->toBeInstanceOf(InvalidArgumentException::class);
});
