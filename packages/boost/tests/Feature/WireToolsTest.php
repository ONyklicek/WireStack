<?php

declare(strict_types=1);

use NyonCode\WireBoost\Mcp\Tools\ApplicationInfo;
use NyonCode\WireBoost\Mcp\Tools\DescribeComponentApi;
use NyonCode\WireBoost\Mcp\Tools\DescribeForm;
use NyonCode\WireBoost\Mcp\Tools\DescribeInfolist;
use NyonCode\WireBoost\Mcp\Tools\DescribeModule;
use NyonCode\WireBoost\Mcp\Tools\DescribeResource;
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
use NyonCode\WireCore\Core\Modules\DomainModule;
use NyonCode\WireCore\Core\Plugin\Contracts\HasDependencies;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;

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

it('describes the registered resources', function () {
    app(ResourceRegistry::class)->register(WtOrderResource::class);

    WireBoostServer::tool(DescribeResource::class)
        ->assertOk()
        ->assertSee('wt-orders')
        ->assertSee('infolist');
});

it('describes one resource by key', function () {
    app(ResourceRegistry::class)->register(WtOrderResource::class);

    WireBoostServer::tool(DescribeResource::class, ['resource' => 'wt-orders'])
        ->assertOk()
        ->assertSee('Wt Order');
});

/**
 * Register modules into a manager that has not booted yet.
 *
 * The application's own booted while its providers did, and registering into a
 * booted manager is refused — a plugin arriving that late is never booted and
 * its declarations never reach the registries, so it would look installed and
 * do nothing. Binding a fresh one puts these tests in the phase a package
 * provider registers from.
 */
function wtRegisterModules(object ...$modules): void
{
    $manager = new PluginManager;
    app()->instance(PluginManager::class, $manager);

    foreach ($modules as $module) {
        $manager->register($module);
    }
}

it('describes the registered domain modules', function () {
    // The one thing no other tool can show: which business area a resource
    // belongs to. describe-resource lists resources and knows nothing about it.
    wtRegisterModules(new WtBillingModule);

    WireBoostServer::tool(DescribeModule::class)
        ->assertOk()
        ->assertSee('wt-billing')
        ->assertSee('Billing')
        ->assertSee(WtOrderResource::class);
});

it('describes one module by id, with what it depends on', function () {
    wtRegisterModules(new WtBillingModule, new WtOperationsModule);

    WireBoostServer::tool(DescribeModule::class, ['module' => 'wt-operations'])
        ->assertOk()
        ->assertSee('wt-billing');
});

it('says which modules exist when asked for one that does not', function () {
    wtRegisterModules(new WtBillingModule);

    WireBoostServer::tool(DescribeModule::class, ['module' => 'nope'])
        ->assertOk()
        ->assertSee('wt-billing');
});

it('says which resources exist when asked for one that does not', function () {
    // Better than an empty answer: the developer is usually one typo away, and
    // the registered keys are the shortest way to show it.
    app(ResourceRegistry::class)->register(WtOrderResource::class);

    WireBoostServer::tool(DescribeResource::class, ['resource' => 'nope'])
        ->assertOk()
        ->assertSee('No resource is registered')
        ->assertSee('wt-orders');
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

it('refuses to read application config keys outside the wireStack namespaces', function () {
    // Regression H6: the free-form key must not leak arbitrary application config
    // (app secrets, DB credentials) into the model context.
    config(['app.key' => 'base64:super-secret-app-key']);

    WireBoostServer::tool(WireConfig::class, ['key' => 'app.key'])
        ->assertHasErrors()
        ->assertDontSee('super-secret-app-key');

    WireBoostServer::tool(WireConfig::class, ['key' => 'database.connections.mysql.password'])
        ->assertHasErrors();
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

/** A resource for the describe-resource tool: identity plus one surface. */
class WtOrderResource implements DescribesResource, ProvidesResourceInfolist
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('number')]);
    }
}

final class WtBillingModule extends DomainModule
{
    public function getId(): string
    {
        return 'wt-billing';
    }

    public function resources(): array
    {
        return [WtOrderResource::class];
    }

    public function navigation(): ?NavigationGroup
    {
        return NavigationGroup::make('wt-billing')->label('Billing')->sort(10);
    }
}

final class WtOperationsModule extends DomainModule implements HasDependencies
{
    public function getId(): string
    {
        return 'wt-operations';
    }

    public function dependencies(): array
    {
        return ['wt-billing'];
    }
}
