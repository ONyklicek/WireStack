<?php

declare(strict_types=1);

return [
    'breadcrumbs' => 'Breadcrumb',
    // Shared inline-edit messages (editable panel entries).
    'error' => 'Something went wrong.',
    'save_failed' => 'Could not save. Please try again.',
    'save_error' => 'Could not save: :error',
    'validation_failed' => 'The value is invalid.',
    'record_conflict' => 'This record was changed elsewhere. The latest value is shown.',
    'record_not_found' => 'Record not found.',
    'entry_not_editable' => 'This field cannot be edited.',
    'no_permission' => 'You are not allowed to edit this field.',
    'no_permission_edit' => 'You are not allowed to edit this record.',

    // The copy-to-clipboard affordance ({@see Foundation\View\CopyButton}).
    'copy' => 'Copy',
    'copied' => 'Copied!',

    // The notification bell ({@see Notifications\NotificationBell}).
    'notifications' => 'Notifications',
    'no_notifications' => 'Nothing here yet.',
    'mark_read' => 'Mark as read',
    'mark_all_read' => 'Mark all read',
    'notifications_all' => 'All',
    'notifications_unread' => 'Unread',
    'no_unread_notifications' => 'Nothing unread.',
    'view_all_notifications' => 'View all',
    'mark_unread' => 'Mark as unread',
    'delete_notification' => 'Delete',
    'clear_read' => 'Clear read',
    'notifications_today' => 'Today',
    'notifications_yesterday' => 'Yesterday',
    'notifications_earlier' => 'Earlier',
    'notifications_all_read' => 'Everything is read',

    // Queued actions ({@see Actions\Concerns\Queueable}).
    'action_queued' => ':action is running in the background.',
    'action_queued_done' => ':action finished.',

    // The three states of Foundation\Enums\Theme. `System` is a choice too —
    // it is what a laptop that dims itself in the evening needs somebody to be
    // able to go back to.
    'theme_light' => 'Light',
    'theme_system' => 'System',
    'theme_dark' => 'Dark',

    // What a file is, as a family rather than as a format
    // ({@see Foundation\Enums\FileKind}). Shown where a file has no preview to
    // show and its name has no extension to print instead.
    'file_kinds' => [
        'image' => 'Image',
        'video' => 'Video',
        'audio' => 'Audio',
        'document' => 'Document',
        'spreadsheet' => 'Spreadsheet',
        'presentation' => 'Presentation',
        'archive' => 'Archive',
        'code' => 'Code',
        'other' => 'File',
    ],

    // The dashboard grid ({@see Widgets\Widget}). `widget_loading` is announced
    // to a screen reader while a deferred widget is being fetched; the empty
    // state is what a list widget shows when its query came back with nothing.
    'widget_loading' => 'Loading…',
    'widget_filter' => 'Filter',
    'widget_empty' => 'Nothing to show.',
    'widget_reorder' => 'Drag to reorder',
    'widget_tray' => 'Available widgets',
    'widget_tray_empty' => 'Everything is on the dashboard.',
    'widget_add' => 'Add to dashboard',
    'widget_remove' => 'Remove from dashboard',
    'widget_wider' => 'Wider',
    'widget_narrower' => 'Narrower',
    'widget_taller' => 'Taller',
    'widget_shorter' => 'Shorter',
    'widget_customise' => 'Customise',
    'widget_save_layout' => 'Save layout',
    'widget_cancel_layout' => 'Cancel',
    'widget_reset_layout' => 'Reset to default',
];
