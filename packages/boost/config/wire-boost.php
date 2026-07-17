<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Server
    |--------------------------------------------------------------------------
    |
    | Identity reported by the wire-boost MCP server to connected AI agents.
    | The server is registered under the "wire-boost" local handle and is
    | started with `php artisan wire-boost:mcp`.
    |
    */
    'server' => [
        'name' => 'WireStack Boost',
        'version' => '1.0.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tool Toggles
    |--------------------------------------------------------------------------
    |
    | A handful of tools execute application code or read arbitrary data. They
    | are disabled by default and must be explicitly enabled, mirroring the
    | safety posture of laravel/boost.
    |
    */
    'tools' => [
        'database_query' => env('WIRE_BOOST_DATABASE_QUERY', false),
        'tinker' => env('WIRE_BOOST_TINKER', false),
        'browser_logs' => env('WIRE_BOOST_BROWSER_LOGS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Component Scanning
    |--------------------------------------------------------------------------
    |
    | Directories scanned by the "list-wire-components" tool when discovering
    | Livewire components that build wire tables, forms or infolists.
    |
    */
    'scan' => [
        'paths' => [
            app_path(),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Search
    |--------------------------------------------------------------------------
    |
    | The "search-wire-docs" and "fetch-wire-doc" tools always index the corpus
    | shipped with wire-boost: the complete English wireStack documentation plus
    | the curated guidelines and skills. Add extra absolute directories of
    | Markdown here to index your own project's documentation alongside it; each
    | is addressed under a root named after its directory.
    |
    */
    'docs' => [
        'paths' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Browser Logs
    |--------------------------------------------------------------------------
    */
    'browser_logs' => [
        'path' => storage_path('wire-boost/browser.log'),
        'max_entries' => 50,
    ],
];
