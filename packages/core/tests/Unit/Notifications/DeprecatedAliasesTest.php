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
