<?php

declare(strict_types=1);

use NyonCode\WireCore\Notifications\DatabaseNotification;
use NyonCode\WireModuleNotifications\NotificationsModule;
use NyonCode\WireModuleNotifications\Pages\ListNotifications;
use NyonCode\WireModuleNotifications\Pages\ViewNotification;
use NyonCode\WireModuleNotifications\Resources\NotificationResource;

it('names its pages, its label and its menu entry', function () {
    expect(NotificationResource::pages())->toBe([
        'index' => ListNotifications::class,
        'view' => ViewNotification::class,
    ])
        ->and(NotificationResource::label())->not->toBe('')
        ->and(NotificationResource::pluralLabel())->not->toBe('')
        ->and(NotificationResource::navigation()->getGroup())->toBe('system');
});

it('takes the model from configuration, and answers null when it is blank', function () {
    expect(NotificationResource::modelClass())
        ->toBe(DatabaseNotification::class);

    config()->set('wire-module-notifications.model', '');

    expect(NotificationResource::modelClass())->toBeNull();
});

it('lets an application rename the menu group it ships', function () {
    config()->set('wire-module-notifications.navigation.label', 'Inbox');

    expect((new NotificationsModule)->navigation()?->getLabel())->toBe('Inbox');
});

it('falls back to its own heading when the application renames nothing', function () {
    expect((new NotificationsModule)->navigation()?->getLabel())->not->toBe('');
});

it('keeps the inbox out of the sidebar, and keeps it routed', function () {
    // Notifications are not an entity you administer from a menu. The way in is
    // the bell — it carries the unread count and its panel links here — so a
    // permanent row for a screen you reach from a badge is the row nobody reads.
    expect(NotificationResource::navigation()->isVisible())->toBeFalse()
        // Hidden, not absent: the resource still declares its pages, so the URL
        // keeps working and the bell's "view all" keeps finding it.
        ->and(NotificationResource::pages())->toHaveKey('index');
});

it('puts the row back for an application that wants one', function () {
    config()->set('wire-module-notifications.navigation.visible', true);

    expect(NotificationResource::navigation()->isVisible())->toBeTrue();
});
