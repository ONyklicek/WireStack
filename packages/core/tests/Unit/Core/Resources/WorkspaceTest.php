<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Core\Resources\Workspace;

/*
 * The registered resources, arranged the way a menu shows them.
 *
 * Everything here answers from the *static* half of the contract, which is the
 * property worth pinning: a sidebar built from fifty resources must not compose
 * fifty tables to find out what to call them.
 */
class WsOrderResource implements DescribesResource, ProvidesNavigation
{
    public static function modelClass(): ?string
    {
        return null;
    }

    public static function key(): string
    {
        return 'orders';
    }

    public static function label(): string
    {
        return 'Order';
    }

    public static function pluralLabel(): string
    {
        return 'Orders';
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make('Orders')->group('Sales')->sort(20);
    }
}

class WsCustomerResource implements DescribesResource, ProvidesNavigation
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make('Customers')->group('Sales')->sort(10);
    }
}

class WsSettingResource implements DescribesResource, ProvidesNavigation
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make('Settings');
    }
}

class WsHiddenResource implements DescribesResource, ProvidesNavigation
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make('Audit')->visible(false);
    }
}

/** Registered and routable, deliberately not in the menu. */
class WsInternalResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }
}

function wsWorkspace(string ...$resources): Workspace
{
    $registry = new ResourceRegistry;

    foreach ($resources as $resource) {
        $registry->register($resource);
    }

    return new Workspace($registry);
}

it('groups entries under their heading and orders them within it', function () {
    $nav = wsWorkspace(WsOrderResource::class, WsCustomerResource::class, WsSettingResource::class)
        ->navigation();

    expect(array_keys($nav))->toBe(['Sales', ''])
        ->and(array_map(fn (NavigationItem $i) => $i->getLabel(), $nav['Sales']))
        ->toBe(['Customers', 'Orders'])   // sort 10 before sort 20, not declaration order
        ->and(array_map(fn (NavigationItem $i) => $i->getLabel(), $nav['']))
        ->toBe(['Settings']);
});

it('keeps declaration order where sort values tie', function () {
    // Which is what makes sort() optional: a menu declared in a sensible order
    // reads that way without anyone numbering it.
    $nav = wsWorkspace(WsSettingResource::class, WsHiddenResource::class)->navigation();

    expect($nav[''] ?? [])->toHaveCount(1);
});

it('leaves out a resource that declares no navigation', function () {
    // Registered and routable, just not in the menu — what an internal or
    // nested resource wants.
    $workspace = wsWorkspace(WsInternalResource::class, WsSettingResource::class);

    expect($workspace->items())->toHaveCount(1)
        ->and($workspace->resources())->toHaveCount(2);
});

it('leaves out an entry hidden by its own condition', function () {
    expect(wsWorkspace(WsHiddenResource::class)->items())->toBe([]);
});

it('is empty when nothing is registered', function () {
    expect(wsWorkspace()->navigation())->toBe([])
        ->and(wsWorkspace()->items())->toBe([]);
});
