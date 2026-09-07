<?php

declare(strict_types=1);

use NyonCode\WireCore\Notifications\Support\NotificationStyle;

/*
 * What a notification type looks like where the surface is rendered in PHP.
 *
 * The toast container answers the same question in Alpine, because a toast is
 * built in the browser from a payload. Two surfaces, shared semantics, separate
 * rendering rules — which is why this exists rather than a `match` in the bell.
 */

it('gives each type a role from the shared colour vocabulary', function (string $type, string $color) {
    expect(NotificationStyle::for($type)->color)->toBe($color);
})->with([
    // Roles, not hues: a re-themed palette follows a role and ignores a hue.
    ['success', 'success'],
    ['error', 'danger'],
    ['warning', 'warning'],
    ['info', 'info'],
]);

it('reads anything it does not know as info', function () {
    // A payload written by an older version of an application, or by hand. The
    // arms double as an allow-list — $type comes straight out of a stored row.
    expect(NotificationStyle::for('celebration')->color)->toBe('info')
        ->and(NotificationStyle::for(null)->type)->toBe('info')
        ->and(NotificationStyle::for('<script>')->tint)->not->toContain('<');
});

it('lets a notification that named its own icon keep it', function () {
    $style = NotificationStyle::for('success');

    expect($style->iconOr('outline:rocket-launch'))->toBe('outline:rocket-launch')
        // The type's icon is the fallback for the many that say nothing, and an
        // empty string is one of the ways a payload says nothing.
        ->and($style->iconOr(null))->toBe('outline:check-circle')
        ->and($style->iconOr(''))->toBe('outline:check-circle');
});

it('knows the same six action colours the toast container knows', function () {
    // Not a coincidence to be preserved by memory: an action written once must
    // read the same on both surfaces, so the vocabulary has one owner and this
    // is the list.
    $style = NotificationStyle::for('info');

    expect($style->actionClasses('success'))->toContain('emerald')
        ->and($style->actionClasses('danger'))->toContain('red')
        ->and($style->actionClasses('error'))->toContain('red')
        ->and($style->actionClasses('warning'))->toContain('amber')
        ->and($style->actionClasses('info'))->toContain('cyan')
        ->and($style->actionClasses('primary'))->toContain('primary')
        ->and($style->actionClasses('gray'))->toContain('gray');
});

it('falls an uncoloured action back to the notification it belongs to', function () {
    // What the toast does, and the reason: an action that named no colour is
    // part of the message, not the page's primary call to action.
    expect(NotificationStyle::for('error')->actionClasses(null))->toContain('red')
        ->and(NotificationStyle::for('success')->actionClasses(null))->toContain('emerald');
});
