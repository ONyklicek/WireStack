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

/**
 * Names no menu label of its own — the shape every real resource in this
 * repository had written before anything rendered a menu, and the one no
 * fixture here used to cover.
 */
class WsUnnamedResource implements DescribesResource, ProvidesNavigation
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function pluralLabel(): string
    {
        return 'Invoices';
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make()->group('Billing');
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
        ->and(array_values(array_map(fn (NavigationItem $i) => $i->getLabel(), $nav['Sales'])))
        ->toBe(['Customers', 'Orders'])   // sort 10 before sort 20, not declaration order
        ->and(array_values(array_map(fn (NavigationItem $i) => $i->getLabel(), $nav[''])))
        ->toBe(['Settings']);
});

it('keys every entry by its resource key, through the grouping and the sort', function () {
    // What a menu row needs beside the label: which resource it stands for.
    // NavigationItem holds no URL on purpose, so this key is the only thing a
    // consumer can turn into a link — and it has to survive both the grouping
    // and the sort, which is why the sort is `uasort`.
    $nav = wsWorkspace(WsOrderResource::class, WsCustomerResource::class, WsSettingResource::class)
        ->navigation();

    expect(array_keys($nav['Sales']))->toBe(['ws-customers', 'orders'])
        ->and(array_keys($nav['']))->toBe(['ws-settings'])
        ->and(array_keys(wsWorkspace(WsOrderResource::class)->items()))->toBe(['orders']);
});

it('names an entry after its resource when the entry did not name itself', function () {
    // The case every consumer in this repository actually wrote: a resource
    // declares an icon, a group and a badge, and leaves the label to the thing
    // that already owns its name. Without this the menu rendered blank rows.
    $items = wsWorkspace(WsUnnamedResource::class)->items();

    expect($items[WsUnnamedResource::key()]->getLabel())->toBe('Invoices');
});

it('leaves an entry that named itself alone', function () {
    $items = wsWorkspace(WsOrderResource::class)->items();

    // WsOrderResource::pluralLabel() says "Orders" too, so name it something
    // else first — a fallback that quietly overwrote would pass otherwise.
    expect($items['orders']->getLabel())->toBe('Orders')
        ->and(wsWorkspace(WsCustomerResource::class)->items()['ws-customers']->getLabel())
        ->toBe('Customers')
        ->and(WsCustomerResource::pluralLabel())->toBe('Ws Customers');
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
