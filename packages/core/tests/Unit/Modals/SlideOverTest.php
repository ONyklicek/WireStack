<?php

declare(strict_types=1);

use NyonCode\WireCore\Modals\SlideOver;

// ─── Factory ───────────────────────────────────────────────────────────

it('can be created with make', function () {
    expect(SlideOver::make())->toBeInstanceOf(SlideOver::class);
});

// ─── Basic Properties ──────────────────────────────────────────────────

it('can set heading and description', function () {
    $panel = SlideOver::make()
        ->heading('Details')
        ->description('View details.');

    expect($panel->getHeading())->toBe('Details')
        ->and($panel->getDescription())->toBe('View details.');
});

// ─── Position ──────────────────────────────────────────────────────────

it('defaults to right position', function () {
    expect(SlideOver::make()->getPosition())->toBe('right');
});

it('can set position to left', function () {
    expect(SlideOver::make()->position('left')->getPosition())->toBe('left');
});

// ─── Mobile Only ───────────────────────────────────────────────────────

it('is not mobile only by default', function () {
    expect(SlideOver::make()->isMobileOnly())->toBeFalse();
});

it('can be set to mobile only', function () {
    expect(SlideOver::make()->mobileOnly()->isMobileOnly())->toBeTrue();
});

// ─── Width ─────────────────────────────────────────────────────────────

it('has default md width', function () {
    expect(SlideOver::make()->getWidth())->toBe('md');
});

it('can set width', function () {
    expect(SlideOver::make()->width('lg')->getWidth())->toBe('lg');
});

// ─── Icon ──────────────────────────────────────────────────────────────

it('has no icon by default', function () {
    expect(SlideOver::make()->getIcon())->toBeNull();
});

it('can set icon with color', function () {
    $panel = SlideOver::make()->icon('user', 'primary');

    expect($panel->getIcon())->toBe('user')
        ->and($panel->getIconColor())->toBe('primary');
});

// ─── Close Behavior ────────────────────────────────────────────────────

it('closes on click away by default', function () {
    expect(SlideOver::make()->shouldCloseOnClickAway())->toBeTrue();
});

it('can disable close on click away', function () {
    expect(SlideOver::make()->closeOnClickAway(false)->shouldCloseOnClickAway())->toBeFalse();
});

// ─── Sticky ────────────────────────────────────────────────────────────

it('can set sticky footer and header', function () {
    $panel = SlideOver::make()->stickyFooter()->stickyHeader();

    expect($panel->hasStickyFooter())->toBeTrue()
        ->and($panel->hasStickyHeader())->toBeTrue();
});

// ─── Serialization ─────────────────────────────────────────────────────

it('serializes to array', function () {
    $panel = SlideOver::make()
        ->heading('User Panel')
        ->description('Edit user')
        ->width('lg')
        ->position('left')
        ->mobileOnly()
        ->icon('user', 'primary')
        ->color('primary')
        ->stickyFooter()
        ->id('user-panel');

    $array = $panel->toArray();

    expect($array['heading'])->toBe('User Panel')
        ->and($array['description'])->toBe('Edit user')
        ->and($array['width'])->toBe('lg')
        ->and($array['position'])->toBe('left')
        ->and($array['mobileOnly'])->toBeTrue()
        ->and($array['icon'])->toBe('user')
        ->and($array['iconColor'])->toBe('primary')
        ->and($array['color'])->toBe('primary')
        ->and($array['stickyFooter'])->toBeTrue()
        ->and($array['id'])->toBe('user-panel');
});

// ─── Fluent API ────────────────────────────────────────────────────────

it('supports fluent chaining', function () {
    $panel = SlideOver::make()
        ->heading('Test')
        ->width('lg')
        ->position('left')
        ->mobileOnly()
        ->closeOnClickAway(false)
        ->closeOnEscape(false)
        ->stickyFooter()
        ->stickyHeader();

    expect($panel)->toBeInstanceOf(SlideOver::class)
        ->and($panel->getHeading())->toBe('Test');
});
