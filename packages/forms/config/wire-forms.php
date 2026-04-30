<?php

declare(strict_types=1);

return [
    'date_format' => 'd.m.Y',
    'time_format' => 'H:i',
    'datetime_format' => 'd.m.Y H:i',
    'first_day_of_week' => 1,

    'file_upload' => [
        'disk' => env('WIRE_FORMS_UPLOAD_DISK', 'public'),
        'directory' => 'uploads',
    ],

    'rich_editor' => [
        'toolbar' => [
            'bold', 'italic', 'underline', 'strike',
            '|', 'heading', 'bulletList', 'orderedList',
            '|', 'link', 'blockquote', 'codeBlock',
            '|', 'undo', 'redo',
        ],
    ],
];
