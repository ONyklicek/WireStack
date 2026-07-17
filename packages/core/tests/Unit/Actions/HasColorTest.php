<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\Concerns\HasColor;

// HasColor trait provides static methods for color class resolution.
// Instance methods (resolveSolidColorClasses, etc.) are protected and
// called internally during rendering. We test the public static methods.

// ─── Badge color classes (public static) ──────────────────────────────────

it('resolves badge color classes for danger', function () {
    $classes = Action::getBadgeColorClasses('danger');

    expect($classes)->toBeString()
        ->and($classes)->toContain('red');
});

it('resolves badge color classes for success', function () {
    $classes = Action::getBadgeColorClasses('success');

    expect($classes)->toBeString()
        ->and($classes)->toContain('emerald');
});

it('resolves badge color classes for warning', function () {
    $classes = Action::getBadgeColorClasses('warning');

    expect($classes)->toBeString()
        ->and($classes)->toContain('amber');
});

it('resolves badge color classes for primary', function () {
    $classes = Action::getBadgeColorClasses('primary');

    expect($classes)->toBeString()
        ->and($classes)->toContain('primary');
});

// ─── Modal icon classes (public static) ────────────────────────────────────

it('resolves modal icon background class for danger', function () {
    $class = Action::getModalIconBgClass('danger');

    expect($class)->toBeString()
        ->and($class)->toContain('red');
});

it('resolves modal icon background class for success', function () {
    $class = Action::getModalIconBgClass('success');

    expect($class)->toBeString()
        ->and($class)->toContain('emerald');
});

it('resolves modal icon text class for danger', function () {
    $class = Action::getModalIconTextClass('danger');

    expect($class)->toBeString()
        ->and($class)->toContain('red');
});

it('resolves modal icon text class for warning', function () {
    $class = Action::getModalIconTextClass('warning');

    expect($class)->toBeString()
        ->and($class)->toContain('amber');
});

// ─── Color via action fluent API ──────────────────────────────────────────

it('default color is primary', function () {
    expect(Action::make('test')->getColor())->toBe('primary');
});

it('can set color via fluent api', function () {
    expect(Action::make('test')->color('danger')->getColor())->toBe('danger');
});

it('color aliases work', function () {
    // Verify the trait handles the true semantic aliases (emerald=success,
    // amber=warning, secondary=gray) by testing badge classes which use the
    // same color resolution.
    expect(Action::getBadgeColorClasses('emerald'))->toBe(Action::getBadgeColorClasses('success'))
        ->and(Action::getBadgeColorClasses('amber'))->toBe(Action::getBadgeColorClasses('warning'))
        ->and(Action::getBadgeColorClasses('secondary'))->toBe(Action::getBadgeColorClasses('gray'));
});

it('resolves literal hues distinct from the semantic role', function () {
    // blue/green/yellow are first-class literal hues, not aliases of the brand
    // primary / success / warning roles.
    expect(Action::getBadgeColorClasses('blue'))->not->toBe(Action::getBadgeColorClasses('primary'))
        ->and(Action::getBadgeColorClasses('blue'))->toContain('blue')
        ->and(Action::getBadgeColorClasses('green'))->toContain('green')
        ->and(Action::getBadgeColorClasses('yellow'))->toContain('yellow');
});

// ─── getSolidColorClasses via HasColor trait (public static on trait) ──────

it('getSolidColorClasses returns string for known colors', function () {
    // Access through ActionGroup which exposes color class methods publicly
    $group = ActionGroup::make([])->color('primary');

    // The getTriggerClasses method internally uses color resolution
    $classes = $group->getTriggerClasses();

    expect($classes)->toBeString();
});
