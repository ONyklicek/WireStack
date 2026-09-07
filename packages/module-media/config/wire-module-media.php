<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Where Files Live
    |--------------------------------------------------------------------------
    |
    | The disk is stored on every row as well, because it is part of a file's
    | address: changing this moves new uploads and leaves the old ones readable.
    |
    */
    'disk' => env('WIRE_MEDIA_DISK', 'public'),

    'directory' => env('WIRE_MEDIA_DIRECTORY', 'media'),

    'table' => env('WIRE_MEDIA_TABLE', 'wire_media'),

    'folders_table' => env('WIRE_MEDIA_FOLDERS_TABLE', 'wire_media_folders'),

    /*
    |--------------------------------------------------------------------------
    | What May Be Uploaded
    |--------------------------------------------------------------------------
    |
    | An empty list accepts anything the application's own validation allows.
    | Size is in kilobytes, the unit Laravel's rules use.
    |
    */
    'accepts' => [],

    'max_size' => env('WIRE_MEDIA_MAX_SIZE', 10240),

    /*
    |--------------------------------------------------------------------------
    | Serving Private Files
    |--------------------------------------------------------------------------
    |
    | A `public` disk answers its own URL and the browser fetches the file
    | directly — the fastest thing that can happen, and untouched by this. A
    | private disk has no such URL, and without a route the library can only show
    | blank tiles.
    |
    | The middleware is the application's: this ships `web` and `auth` because a
    | file behind a private disk is a file somebody meant to be behind a login,
    | and it is checked against the Media policy as well, if one is registered.
    |
    */
    'route' => [
        'enabled' => env('WIRE_MEDIA_ROUTE', true),

        'prefix' => env('WIRE_MEDIA_ROUTE_PREFIX', 'wire-media'),

        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Thumbnails
    |--------------------------------------------------------------------------
    |
    | A scaled copy made on upload, so a grid of a hundred files is not a
    | hundred full-size photographs. `width` is the longest edge in pixels; an
    | image already smaller than that is copied rather than enlarged.
    |
    | Switched off, or on a server without GD, every preview falls back to the
    | original and the library works exactly as it did — slower to load, and
    | nothing else.
    |
    */
    'thumbnails' => [
        'enabled' => env('WIRE_MEDIA_THUMBNAILS', true),

        'width' => env('WIRE_MEDIA_THUMBNAIL_WIDTH', 400),

        /*
         * The other sizes to make, by name. `width` above is the **tile** and
         * stays exactly what it was; these are the ones that were missing.
         *
         * One 400-pixel copy used to serve a 32-pixel row, a grid tile and a
         * detail preview — two of those three paying for pixels they throw
         * away, on every screen of the library.
         *
         * Remove a name and nothing asks for it: a view whose size is missing
         * falls back to the tile and then to the original, exactly as it always
         * did. Add one and `wire-module-media:thumbnails --size=<name>` fills it
         * in without remaking anything else.
         */
        'sizes' => [
            'row' => env('WIRE_MEDIA_THUMBNAIL_ROW', 96),
            'preview' => env('WIRE_MEDIA_THUMBNAIL_PREVIEW', 1200),
        ],

        'directory' => env('WIRE_MEDIA_THUMBNAIL_DIRECTORY', 'thumbnails'),

        /*
         * Make them on a queue rather than in the request that uploaded the
         * file. Off by default, because a queue that is not being worked would
         * mean thumbnails that never appear — which is worse than an upload that
         * takes a moment. Turn it on where a worker is actually running: a large
         * photograph is seconds of resizing, and it is seconds the person who
         * dropped it spends watching a spinner.
         *
         * `true` uses the default queue; a string names one.
         */
        'queue' => env('WIRE_MEDIA_THUMBNAIL_QUEUE', false),
    ],

    'navigation' => [
        'group' => 'content',
        'label' => null,
        'icon' => 'outline:photo',
        'sort' => 80,
    ],

];
