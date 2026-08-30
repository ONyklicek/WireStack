<?php

declare(strict_types=1);

return [
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

    // Queued actions ({@see Actions\Concerns\Queueable}).
    'action_queued' => ':action is running in the background.',
    'action_queued_done' => ':action finished.',
];
