<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Foundation\Icons\IconSprite;
use NyonCode\WireCore\Foundation\Icons\ResolvedIcon;

/**
 * Drawing a repeated icon once — the scope rules and the scan.
 *
 * Everything an icon looks like stays on its `<svg>` (classes, viewBox,
 * fill/stroke, aria, forwarded attributes); only the body moves into a
 * `<symbol>`. These tests hold the two halves of that promise: the `<svg>` a
 * sprited icon renders is the inline one with a different body, and every
 * piece of markup a scope closes defines what it references.
 */
function spriteOn(): IconSprite
{
    config()->set('wire-core.icons.sprite', true);
    app()->forgetInstance(IconSprite::class);

    return app(IconSprite::class);
}

/** The opening tag of the first `<svg>` — everything an icon looks like. */
function openingTag(string $html): string
{
    preg_match('/<svg[^>]*>/', $html, $match);

    return $match[0] ?? '';
}

it('leaves an icon inline outside a scope, sprite on or off', function (bool $on) {
    config()->set('wire-core.icons.sprite', $on);
    app()->forgetInstance(IconSprite::class);

    $html = app(IconManager::class)->render('pencil');

    expect($html)->toContain('<path')->not->toContain('<use');
})->with(['sprite off' => false, 'sprite on' => true]);

it('draws an icon inside a scope as the same svg holding a use', function (string $name, string $size, string $label, array $attributes) {
    $manager = app(IconManager::class);
    $inline = $manager->render($name, $size, 'text-gray-500', $label, $attributes);

    $sprite = spriteOn();
    $sprited = $sprite->scope(fn (): string => $manager->render($name, $size, 'text-gray-500', $label, $attributes));

    expect(openingTag($sprited))->toBe(openingTag($inline))
        ->and($sprited)->toContain('<use href="#wi-')
        ->and($sprited)->toContain('<symbol id="wi-');
})->with([
    'solid, decorative' => ['pencil', 'w-4 h-4', '', []],
    'outline, labelled' => ['outline:x-mark', 'w-5 h-5', 'Zavřít', []],
    'stroke set' => ['wire:star', 'w-3 h-3', '', []],
    'alpine-bound' => ['check', 'w-4 h-4', '', ['x-show' => 'open', ':class' => "{ 'rotate-180': open }"]],
]);

it('carries each symbol once, in the first icon of the scope', function () {
    $manager = app(IconManager::class);
    $sprite = spriteOn();

    $html = $sprite->scope(fn (): string => str_repeat('<td>'.$manager->render('pencil').$manager->render('trash').'</td>', 20));

    expect(substr_count($html, '<symbol id="wi-'))->toBe(2)
        ->and(substr_count($html, '<use href="#wi-'))->toBe(40)
        // Both symbols sit in the very first <svg>, ahead of its own <use>.
        ->and(strpos($html, '<symbol'))->toBeLessThan(strpos($html, '<use'))
        ->and(strrpos($html, '<symbol'))->toBeLessThan(strpos($html, '</svg>'));
});

it('does not define again what nested markup already defines', function () {
    $manager = app(IconManager::class);
    $sprite = spriteOn();

    $html = $sprite->scope(function () use ($sprite, $manager): string {
        $child = $sprite->scope(fn (): string => '<div>'.$manager->render('pencil').'</div>');

        return '<section>'.$manager->render('pencil').$child.'</section>';
    });

    expect(substr_count($html, '<symbol id="wi-'))->toBe(1);
});

it('gives the body back where nothing live can carry the symbol', function (string $wrap) {
    $manager = app(IconManager::class);
    $sprite = spriteOn();

    $html = $sprite->scope(fn (): string => sprintf($wrap, $manager->render('pencil')));

    // A template is not in the document until Alpine stamps it, and a script is
    // text: a symbol in either defines nothing.
    expect($html)->not->toContain('<use')->not->toContain('<symbol')->toContain('<path');
})->with([
    'inside a template' => ['<template x-if="open"><button>%s</button></template>'],
    'inside a script' => ['<script type="text/html">%s</script>'],
]);

it('puts the symbols on a live icon when the first one is inside a template', function () {
    $manager = app(IconManager::class);
    $sprite = spriteOn();

    $html = $sprite->scope(fn (): string => '<template x-if="a">'.$manager->render('pencil').'</template><span>'.$manager->render('trash').'</span>');

    $template = substr($html, 0, strpos($html, '</template>'));

    expect($template)->not->toContain('<symbol')
        ->and(substr_count($html, '<symbol id="wi-'))->toBe(2);
});

it('defines a reference that only appears escaped, in a JSON spec', function () {
    $manager = app(IconManager::class);
    $sprite = spriteOn();

    $html = $sprite->scope(fn (): string => '<div x-data="'.e(json_encode(['icon' => $manager->render('pencil')])).'">'.$manager->render('trash').'</div>');

    expect(substr_count($html, '<symbol id="wi-'))->toBe(2);
});

it('keeps an icon inline while a caller asks for it', function () {
    $manager = app(IconManager::class);
    $sprite = spriteOn();

    $html = $sprite->scope(fn (): string => $sprite->inline(fn (): string => $manager->render('pencil')));

    expect($html)->toContain('<path')->not->toContain('<use');
});

it('never shares a body that carries ids or url references', function (string $body) {
    expect((new ResolvedIcon($body))->spriteId())->toBeNull();
})->with([
    'gradient' => ['<defs><linearGradient id="g"/></defs><path fill="url(#g)" d="M0 0h20v20H0z"/>'],
    'clip path' => ['<clipPath id="c"><path d="M0 0h1"/></clipPath><path clip-path="url(#c)" d="M0 0h20"/>'],
    'nested use' => ['<use href="#other"/>'],
]);

it('shares one symbol between two icons drawn from one body', function () {
    $solid = new ResolvedIcon('<path d="M1 1h18"/>', '0 0 20 20', ['fill' => 'currentColor']);
    $stroked = new ResolvedIcon('<path d="M1 1h18"/>', '0 0 20 20', ['stroke' => 'currentColor']);

    // The fill/stroke stay on each <svg> and reach the symbol by inheritance.
    expect($solid->spriteId())->toBe($stroked->spriteId())
        ->and($solid->spriteId())->not->toBe((new ResolvedIcon('<path d="M1 1h18"/>', '0 0 24 24'))->spriteId());
});

it('closes a scope that threw, so later icons are inline again', function () {
    $manager = app(IconManager::class);
    $sprite = spriteOn();

    expect(fn () => $sprite->scope(function (): string {
        throw new RuntimeException('render failed');
    }))->toThrow(RuntimeException::class);

    expect($manager->render('pencil'))->toContain('<path')->not->toContain('<use');
});
