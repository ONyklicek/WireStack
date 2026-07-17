<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithStateColor;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithStateIcon;
use NyonCode\WireCore\Foundation\Icons\Icon;

/*
 * The concerns' own defaults — the hooks a plain consumer does not override, and
 * the fluent setters. Core's only consumer (IconEntry) overrides every hook and
 * the table columns live in another package, so this exercises the traits in
 * isolation the way the standard asks for.
 */
class SrcPlainConsumer
{
    use InteractsWithStateColor;
    use InteractsWithStateIcon;

    public function __construct(
        private ?string $color = null,
        private ?string $icon = null,
    ) {}

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }
}

test('colors() unwraps a Color enum in the map eagerly', function () {
    expect((new SrcPlainConsumer)->colors(['on' => Color::Success])->getColorForState('on'))
        ->toBe('success');
});

test('icons() unwraps an Icon enum in the map eagerly', function () {
    expect((new SrcPlainConsumer)->icons(['on' => Icon::pen])->getIconForState('on'))
        ->toBe(Icon::pen->value());
});

test('a bad map value is rejected at configuration time, not mid-render', function () {
    // The eager array_map is what buys this: a Closure map cannot be checked
    // until it is called, but an array can, and a table render is a bad place
    // to discover it.
    expect(fn () => (new SrcPlainConsumer)->colors(['on' => ['not', 'a', 'color']]))
        ->toThrow(TypeError::class)
        ->and(fn () => (new SrcPlainConsumer)->icons(['on' => ['not', 'an', 'icon']]))
        ->toThrow(TypeError::class);
});

test('colorUsing()/iconUsing() take precedence over the map', function () {
    $subject = (new SrcPlainConsumer)
        ->colors(['on' => 'danger'])
        ->icons(['on' => 'clock'])
        ->colorUsing(fn () => Color::Success)
        ->iconUsing(fn () => Icon::pen);

    expect($subject->getColorForState('on'))->toBe('success')
        ->and($subject->getIconForState('on'))->toBe(Icon::pen->value());
});

test('a Closure map is ignored by the default hook, which only reads arrays', function () {
    // A surface that wants Closure maps evaluates them in resolveState*Map();
    // the default deliberately does not, so it falls through to the default.
    $subject = (new SrcPlainConsumer('info', 'star'))
        ->colors(fn () => ['on' => 'danger'])
        ->icons(fn () => ['on' => 'clock']);

    expect($subject->getColorForState('on'))->toBe('info')
        ->and($subject->getIconForState('on'))->toBe('star');
});

test('an unmapped state falls back to the component colour, then to gray', function () {
    expect((new SrcPlainConsumer('info'))->getColorForState('off'))->toBe('info')
        ->and((new SrcPlainConsumer)->getColorForState('off'))->toBe(Color::Gray->value);
});

test('an unmapped state falls back to the component icon, and to no icon', function () {
    expect((new SrcPlainConsumer(icon: 'star'))->getIconForState('off'))->toBe('star')
        ->and((new SrcPlainConsumer)->getIconForState('off'))->toBeNull();
});

test('no override is applied unless a surface opts in', function () {
    // resolveState*Override() returns null by default: boolean() modes are the
    // opt-in, and a plain consumer must not get one.
    expect((new SrcPlainConsumer)->colors(['1' => 'success'])->getColorForState(true))
        ->toBe('success');
});
