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
use NyonCode\WireBoost\Mcp\Tools\FetchDoc;
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
use NyonCode\WireBoost\Mcp\Tools\ValidateComponent;
use NyonCode\WireBoost\Mcp\Tools\WireConfig;

class WireBoostServer extends Server
{
    protected string $name = 'WireStack Boost';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
    WireStack Boost exposes tools for building applications with the wireStack ecosystem
    (wire-core, wire-forms, wire-table, wire-sortable) — fluent, Nova/Filament-style Livewire
    tables, forms and infolists.

    Before writing wire code:
    - `search-wire-docs` to find the relevant documentation section, then `fetch-wire-doc` with a
      result id to read it in full. The complete wireStack documentation is indexed, not a summary.
    - `list-component-types` and `describe-component-api` to discover available columns, fields,
      filters, actions, entries and widgets, their fluent methods and the values they accept.
    - `list-wire-components`, `describe-table`, `describe-form`, `describe-infolist` to inspect
      the components already present in the application.
    - `list-icons` for valid icon names, `wire-config` for effective configuration.

    After writing or editing a wire component:
    - `validate-wire-component` to catch the faults that do not throw — an unknown color renders
      gray, an unregistered icon renders nothing, and a name the model cannot resolve renders an
      empty cell. A passing render test does not rule any of these out.

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
        ValidateComponent::class,
        ListComponentTypes::class,
        DescribeComponentApi::class,
        ListIcons::class,
        WireConfig::class,
        SearchDocs::class,
        FetchDoc::class,
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
