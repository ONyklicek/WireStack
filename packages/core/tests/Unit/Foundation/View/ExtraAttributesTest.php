<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\View\ExtraAttributes;
use NyonCode\WireForms\Components\TextInput;

it('renders each attribute as a leading-spaced pair', function () {
    $html = ExtraAttributes::for(TextInput::make('a')->extraAttributes([
        'data-probe' => 'yes',
        'aria-label' => 'Serial number',
    ]))->toHtml();

    expect($html)->toBe(' data-probe="yes" aria-label="Serial number"');
});

it('renders nothing at all when there are none', function () {
    // Not an empty attribute, not a stray space in the tag — nothing.
    expect(ExtraAttributes::for(TextInput::make('a'))->toHtml())->toBe('');
});

it('escapes the value so it cannot end the attribute', function () {
    $html = ExtraAttributes::for(TextInput::make('a')->extraAttributes([
        'title' => '"><script>alert(1)</script>',
    ]))->toHtml();

    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&quot;&gt;');
});

it('drops a name that is not a plain attribute', function () {
    // Values are escaped, so the only way out of the tag would be through the
    // name — which nothing here generates, but an application might pass one.
    $html = ExtraAttributes::for(TextInput::make('a')->extraAttributes([
        'onclick=x foo' => 'y',
        '><script' => 'y',
        'data-kept' => 'yes',
    ]))->toHtml();

    expect($html)->toBe(' data-kept="yes"');
});

it('keeps the names the framework and its ecosystem actually use', function () {
    $html = ExtraAttributes::for(TextInput::make('a')->extraAttributes([
        'data-testid' => 'a',
        'aria-describedby' => 'b',
        'wire:key' => 'c',
        'x-on:click' => 'd',
        ':class' => 'e',
    ]))->toHtml();

    expect($html)->toContain('data-testid="a"')
        ->and($html)->toContain('aria-describedby="b"')
        ->and($html)->toContain('wire:key="c"')
        ->and($html)->toContain('x-on:click="d"')
        ->and($html)->toContain(':class="e"');
});

it('takes an object with no such getter without complaining', function () {
    // The schema Callout is a layout element and renders the same partial as the
    // forms Alert, which is a component. One of the two has no attributes.
    expect(ExtraAttributes::for(new stdClass)->toHtml())->toBe('')
        ->and(ExtraAttributes::for(null)->toHtml())->toBe('');
});
