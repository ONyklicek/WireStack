<?php

declare(strict_types=1);

use NyonCode\WireBoost\Mcp\Tools\ApplicationInfo;
use NyonCode\WireBoost\Mcp\Tools\DescribeComponentApi;
use NyonCode\WireBoost\Mcp\Tools\DescribeForm;
use NyonCode\WireBoost\Mcp\Tools\DescribeInfolist;
use NyonCode\WireBoost\Mcp\Tools\DescribeTable;
use NyonCode\WireBoost\Mcp\Tools\ListComponentTypes;
use NyonCode\WireBoost\Mcp\Tools\ListIcons;
use NyonCode\WireBoost\Mcp\Tools\ListWireComponents;
use NyonCode\WireBoost\Mcp\Tools\SearchDocs;
use NyonCode\WireBoost\Mcp\Tools\WireConfig;
use NyonCode\WireBoost\Mcp\WireBoostServer;
use NyonCode\WireBoost\Tests\Fixtures\DemoForm;
use NyonCode\WireBoost\Tests\Fixtures\DemoInfolist;
use NyonCode\WireBoost\Tests\Fixtures\DemoTable;
use NyonCode\WireCore\Foundation\Icons\IconManager;

it('reports application info with wire package versions', function () {
    WireBoostServer::tool(ApplicationInfo::class)
        ->assertOk()
        ->assertSee('nyoncode/wire-core')
        ->assertSee('livewire/livewire');
});

it('lists wire components from the configured scan paths', function () {
    config()->set('wire-boost.scan.paths', [realpath(__DIR__.'/../Fixtures')]);

    WireBoostServer::tool(ListWireComponents::class)
        ->assertOk()
        ->assertSee('DemoTable');
});

it('describes a table component', function () {
    WireBoostServer::tool(DescribeTable::class, ['component' => DemoTable::class])
        ->assertOk()
        ->assertSee('BadgeColumn')
        ->assertSee('status');
});

it('describes a form component', function () {
    WireBoostServer::tool(DescribeForm::class, ['component' => DemoForm::class])
        ->assertOk()
        ->assertSee('role');
});

it('describes an infolist component', function () {
    WireBoostServer::tool(DescribeInfolist::class, ['component' => DemoInfolist::class])
        ->assertOk()
        ->assertSee('IconEntry');
});

it('lists component types for a category', function () {
    WireBoostServer::tool(ListComponentTypes::class, ['category' => 'fields'])
        ->assertOk()
        ->assertSee('text-input');
});

it('reports an unknown component category', function () {
    WireBoostServer::tool(ListComponentTypes::class, ['category' => 'bogus'])
        ->assertHasErrors()
        ->assertSee('Unknown category')
        // The message names the valid categories, so the agent can recover.
        ->assertSee('columns');
});

it('describes a component api by fully-qualified class name', function () {
    WireBoostServer::tool(DescribeComponentApi::class, ['class' => DemoTable::class])
        ->assertOk()
        ->assertSee('demo-table');
});

it('describes a component api by short name', function () {
    WireBoostServer::tool(DescribeComponentApi::class, ['class' => 'badge-column'])
        ->assertOk()
        ->assertSee('sortable');
});

it('reports an unresolvable component api', function () {
    WireBoostServer::tool(DescribeComponentApi::class, ['class' => 'nope'])
        ->assertHasErrors()
        ->assertSee('Could not resolve')
        ->assertSee('list-component-types');
});

it('lists icons and filters them', function () {
    $names = app(IconManager::class)->allNames();
    $sample = $names[0];

    WireBoostServer::tool(ListIcons::class)
        ->assertOk()
        ->assertSee($sample);

    WireBoostServer::tool(ListIcons::class, ['filter' => $sample])
        ->assertOk()
        ->assertSee($sample);
});

it('returns the effective wire configuration', function () {
    WireBoostServer::tool(WireConfig::class)
        ->assertOk()
        ->assertSee('wire-table');

    WireBoostServer::tool(WireConfig::class, ['key' => 'wire-table.defaults.per_page'])
        ->assertOk()
        ->assertSee('per_page');
});

it('searches the wire documentation corpus', function () {
    WireBoostServer::tool(SearchDocs::class, ['query' => 'badge column color'])
        ->assertOk()
        ->assertSee('wire-table');

    WireBoostServer::tool(SearchDocs::class, ['query' => 'field validation', 'package' => 'wire-forms'])
        ->assertOk();

    WireBoostServer::tool(SearchDocs::class, ['query' => '  '])
        ->assertOk();
});
