<?php

declare(strict_types=1);

use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;

test('TableNotification is a deprecated alias of Notification', function () {
    expect(class_exists('NyonCode\WireCore\Notifications\TableNotification'))->toBeTrue();

    $alias = new ReflectionClass('NyonCode\WireCore\Notifications\TableNotification');

    expect($alias->getName())->toBe(Notification::class);
});

test('TableNotificationManager is a deprecated alias of NotificationManager', function () {
    expect(class_exists('NyonCode\WireCore\Notifications\TableNotificationManager'))->toBeTrue();

    $alias = new ReflectionClass('NyonCode\WireCore\Notifications\TableNotificationManager');

    expect($alias->getName())->toBe(NotificationManager::class);
});

test('deprecated trait shims in Concerns resolve to their canonical traits', function (string $alias) {
    // Referencing the alias triggers PSR-4 autoload of the shim file, which
    // runs its class_alias() call.
    @class_exists($alias);

    expect(trait_exists($alias))->toBeTrue();
})->with([
    'NyonCode\WireCore\Concerns\HasColor',
    'NyonCode\WireCore\Concerns\HasButtonStyles',
    'NyonCode\WireCore\Concerns\HasVisibility',
    'NyonCode\WireCore\Concerns\HasModal',
    'NyonCode\WireCore\Concerns\HasIcons',
    'NyonCode\WireCore\Concerns\HasDynamicProperties',
    'NyonCode\WireCore\Concerns\HasKeyboardShortcut',
    'NyonCode\WireCore\Concerns\HasLifecycle',
    'NyonCode\WireCore\Concerns\HasLoadingState',
]);
