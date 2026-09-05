<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Modules\DomainModule;
use NyonCode\WireCore\Core\Plugin\Contracts\HasDependencies;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroups;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Core\Resources\Workspace;
use NyonCode\WireCore\Exceptions\PluginRegistrationException;
use NyonCode\WireCore\Widgets\Dashboard;
use NyonCode\WireCore\Widgets\DashboardRegistry;
use NyonCode\WireCore\WireCoreServiceProvider;

/*
 * The domain axis: one business area named in one place.
 *
 * What is worth pinning is not that a module holds arrays — it is that a module
 * is a *plugin*, so the lifecycle and the dependency rule are the ones that
 * already existed, and that declaring is all it does: the provider distributes,
 * because a module that reached for DashboardRegistry itself would be the
 * L2→L2 import ModuleLayersTest fails on.
 */

class DmInvoiceResource implements DescribesResource, ProvidesNavigation
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make()->group('dm-billing');
    }
}

class DmTaskResource implements DescribesResource, ProvidesNavigation
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make()->group('dm-operations');
    }
}

/** Registered by a module, deliberately not in the menu. */
class DmInternalResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }
}

final class DmSalesDashboard extends Dashboard
{
    public function widgets(): array
    {
        return [];
    }
}

final class DmBillingModule extends DomainModule
{
    public function getId(): string
    {
        return 'dm-billing';
    }

    public function resources(): array
    {
        return [DmInvoiceResource::class];
    }

    public function navigation(): ?NavigationGroup
    {
        return NavigationGroup::make('dm-billing')->label('Billing')->sort(20);
    }
}

final class DmOperationsModule extends DomainModule implements HasDependencies
{
    public function getId(): string
    {
        return 'dm-operations';
    }

    public function dependencies(): array
    {
        return ['dm-billing'];
    }

    public function resources(): array
    {
        return [DmTaskResource::class];
    }

    public function dashboards(): array
    {
        return [DmSalesDashboard::class];
    }

    public function navigation(): ?NavigationGroup
    {
        return NavigationGroup::make('dm-operations')->label('Operations')->sort(10);
    }
}

/** Declares nothing at all, which has to be ordinary rather than an error. */
final class DmEmptyModule extends DomainModule
{
    public function getId(): string
    {
        return 'dm-empty';
    }
}

it('declares nothing by default', function () {
    $module = new DmEmptyModule;

    expect($module->resources())->toBe([])
        ->and($module->dashboards())->toBe([])
        ->and($module->navigation())->toBeNull();
});

it('is a plugin, so the lifecycle is the one that already existed', function () {
    // The whole reason there is no ModuleRegistry: register-then-boot, one id
    // per module, dependency checking — PluginManager does all of it, and a
    // second registry over the same list is what this codebase keeps deleting.
    $manager = new PluginManager;
    $manager->register(new DmBillingModule);

    expect(new DmBillingModule)->toBeInstanceOf(Plugin::class)
        ->and($manager->has('dm-billing'))->toBeTrue()
        ->and($manager->get('dm-billing'))->toBeInstanceOf(DmBillingModule::class)
        ->and(array_keys($manager->all()))->toBe(['dm-billing']);
});

it('refuses a module whose dependency is not registered yet', function () {
    // The ordering guarantee V2.6 wanted and did not have to build.
    $manager = new PluginManager;

    expect(fn () => $manager->register(new DmOperationsModule))
        ->toThrow(PluginRegistrationException::class);

    $manager->register(new DmBillingModule);

    $manager->register(new DmOperationsModule);

    expect(array_keys($manager->all()))->toBe(['dm-billing', 'dm-operations']);
});

/**
 * Register modules and run the provider's distribution over them.
 *
 * The provider step is invoked directly rather than by rebooting the
 * application: config set inside a test does not survive `refreshApplication()`,
 * and the half that would prove — config reaching PluginManager — is the plugin
 * system's own and already covered. What is new here is the spreading.
 *
 * A *fresh* manager is bound first, because the application's own has already
 * booted by the time a test body runs, and registering into a booted manager is
 * refused (`PluginRegistrationException::registeredAfterBoot()`) — precisely so
 * that a module arriving too late to be spread cannot look installed. Binding a
 * new one puts the test back in the phase a package provider registers in.
 */
function dmDistribute(Plugin ...$modules): void
{
    $manager = new PluginManager;
    app()->instance(PluginManager::class, $manager);

    foreach ($modules as $module) {
        $manager->register($module);
    }

    $provider = new WireCoreServiceProvider(app());
    $boot = new ReflectionMethod($provider, 'bootModules');
    $boot->invoke($provider);
}

it('spreads two modules into the registries that own them', function () {
    // Three registries filled from two declarations, and none of the three is
    // named by the module — it lists classes, the provider knows the registries.
    dmDistribute(new DmBillingModule, new DmOperationsModule);

    expect(array_keys(app(ResourceRegistry::class)->all()))
        ->toContain('dm-invoices', 'dm-tasks')
        ->and(array_keys(app(DashboardRegistry::class)->all()))->toContain('dm-sales')
        ->and(array_keys(app(NavigationGroups::class)->all()))->toContain('dm-billing', 'dm-operations');
});

it('builds one menu out of both modules, ordered by what they declared', function () {
    // What the axis is for, end to end: two areas, each naming its own things,
    // and a menu that reads in the order they asked for rather than the order
    // they were installed in — billing is registered first and sorts second.
    dmDistribute(new DmBillingModule, new DmOperationsModule);

    $nav = app(Workspace::class)->navigation();

    expect(array_keys($nav))->toBe(['dm-operations', 'dm-billing'])
        ->and($nav['dm-operations']->getLabel())->toBe('Operations')
        ->and($nav['dm-billing']->getLabel())->toBe('Billing');
});

it('draws no heading for a group whose module put nothing in it', function () {
    // A module may ship a group and register only internal resources under it.
    // An empty heading is worse than no heading: it reads as a menu that lost
    // its rows.
    $module = new class extends DomainModule
    {
        public function getId(): string
        {
            return 'dm-internal';
        }

        public function resources(): array
        {
            return [DmInternalResource::class];
        }

        public function navigation(): ?NavigationGroup
        {
            return NavigationGroup::make('dm-internal')->label('Internal');
        }
    };

    dmDistribute($module);

    expect(app(NavigationGroups::class)->has('dm-internal'))->toBeTrue()
        ->and(app(ResourceRegistry::class)->has('dm-internals'))->toBeTrue()
        ->and(app(Workspace::class)->navigation())->toBe([]);
});

it('leaves a plugin that is not a module alone', function () {
    // A plugin is still a plugin: nothing about the domain axis changes what an
    // ordinary one does, and the provider must not treat one as a module.
    //
    // This one carries a `resources()` method of its own precisely because a
    // duck-typed check (`method_exists`) would pass it — the distribution is
    // keyed on the type, and a plugin with a same-named method for its own
    // reasons must not have that method read as a declaration.
    $plugin = new class implements Plugin
    {
        public function getId(): string
        {
            return 'dm-plain';
        }

        /** @return array<int, class-string> Its own business, not a module declaration. */
        public function resources(): array
        {
            return [DmInvoiceResource::class];
        }

        public function register(PluginManager $manager): void {}

        public function boot(PluginManager $manager): void {}
    };

    dmDistribute($plugin);

    expect(app(PluginManager::class)->has('dm-plain'))->toBeTrue()
        ->and(app(ResourceRegistry::class)->all())->toBe([])
        ->and(app(NavigationGroups::class)->all())->toBe([]);
});
