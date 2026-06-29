<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;
use NyonCode\WireBoost\Mcp\Tools\ApplicationInfo;
use NyonCode\WireBoost\Mcp\Tools\BrowserLogs;
use NyonCode\WireBoost\Mcp\Tools\DatabaseConnections;
use NyonCode\WireBoost\Mcp\Tools\DatabaseQuery;
use NyonCode\WireBoost\Mcp\Tools\DatabaseSchema;
use NyonCode\WireBoost\Mcp\Tools\DescribeComponentApi;
use NyonCode\WireBoost\Mcp\Tools\DescribeForm;
use NyonCode\WireBoost\Mcp\Tools\DescribeInfolist;
use NyonCode\WireBoost\Mcp\Tools\DescribeTable;
use NyonCode\WireBoost\Mcp\Tools\GetAbsoluteUrl;
use NyonCode\WireBoost\Mcp\Tools\LastError;
use NyonCode\WireBoost\Mcp\Tools\ListArtisanCommands;
use NyonCode\WireBoost\Mcp\Tools\ListComponentTypes;
use NyonCode\WireBoost\Mcp\Tools\ListIcons;
use NyonCode\WireBoost\Mcp\Tools\ListRoutes;
use NyonCode\WireBoost\Mcp\Tools\ListWireComponents;
use NyonCode\WireBoost\Mcp\Tools\ReadLogEntries;
use NyonCode\WireBoost\Mcp\Tools\SearchDocs;
use NyonCode\WireBoost\Mcp\Tools\Tinker;
use NyonCode\WireBoost\Mcp\Tools\WireConfig;

class WireBoostServer extends Server
{
    protected string $name = 'WireStack Boost';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
    WireStack Boost exposes tools for building applications with the wireStack ecosystem
    (wire-core, wire-forms, wire-table, wire-sortable) — fluent, Nova/Filament-style Livewire
    tables, forms and infolists.

    Use these tools before and while writing wire code:
    - `search-wire-docs` to confirm conventions and APIs from the bundled guidelines/skills.
    - `list-component-types` and `describe-component-api` to discover available columns, fields,
      filters, actions, entries and widgets and their fluent methods.
    - `list-wire-components`, `describe-table`, `describe-form`, `describe-infolist` to inspect
      the components already present in the application.
    - `list-icons` for valid icon names, `wire-config` for effective configuration.

    Prefer extending existing canonical concerns over inventing local variants, and render reusable
    markup from PHP (Htmlable) rather than ad-hoc Blade conditionals.
    MARKDOWN;

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ApplicationInfo::class,
        ListWireComponents::class,
        DescribeTable::class,
        DescribeForm::class,
        DescribeInfolist::class,
        ListComponentTypes::class,
        DescribeComponentApi::class,
        ListIcons::class,
        WireConfig::class,
        SearchDocs::class,
        DatabaseSchema::class,
        DatabaseConnections::class,
        DatabaseQuery::class,
        LastError::class,
        ReadLogEntries::class,
        GetAbsoluteUrl::class,
        ListArtisanCommands::class,
        ListRoutes::class,
        Tinker::class,
        BrowserLogs::class,
    ];
}
