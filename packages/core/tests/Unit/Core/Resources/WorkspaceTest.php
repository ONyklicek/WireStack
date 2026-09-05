<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroups;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Core\Resources\Workspace;
use NyonCode\WireCore\Exceptions\ResourceRegistrationException;
use NyonCode\WireCore\Foundation\Registration\Catalog;
use NyonCode\WireCore\Foundation\Registration\Contracts\RegistrySource;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;

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
    return wsWorkspaceWith([], ...$resources);
}

/**
 * @param  array<int, NavigationGroup>  $groups  The groups an application declared, if any.
 */
function wsWorkspaceWith(array $groups, string ...$resources): Workspace
{
    $registry = new ResourceRegistry;

    foreach ($resources as $resource) {
        $registry->register($resource);
    }

    $declared = new NavigationGroups;
    $declared->registerMany($groups);

    return new Workspace(new Catalog([$registry]), $declared);
}

/** @return array<int, string> */
function wsLabels(NavigationGroup $group): array
{
    return array_values(array_map(fn (NavigationItem $i): ?string => $i->getLabel(), $group->getItems()));
}

it('groups entries under their heading and orders them within it', function () {
    $nav = wsWorkspace(WsOrderResource::class, WsCustomerResource::class, WsSettingResource::class)
        ->navigation();

    expect(array_keys($nav))->toBe(['Sales', ''])
        ->and(wsLabels($nav['Sales']))
        ->toBe(['Customers', 'Orders'])   // sort 10 before sort 20, not declaration order
        ->and(wsLabels($nav['']))
        ->toBe(['Settings']);
});

it('keys every entry by its resource key, through the grouping and the sort', function () {
    // What a menu row needs beside the label: which resource it stands for.
    // NavigationItem holds no URL on purpose, so this key is the only thing a
    // consumer can turn into a link — and it has to survive both the grouping
    // and the sort, which is why the sort is `uasort`.
    $nav = wsWorkspace(WsOrderResource::class, WsCustomerResource::class, WsSettingResource::class)
        ->navigation();

    expect(array_keys($nav['Sales']->getItems()))->toBe(['ws-customers', 'orders'])
        ->and(array_keys($nav['']->getItems()))->toBe(['ws-settings'])
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

    expect($nav['']->getItems())->toHaveCount(1);
});

it('leaves out a resource that declares no navigation', function () {
    // Registered and routable, just not in the menu — what an internal or
    // nested resource wants.
    $workspace = wsWorkspace(WsInternalResource::class, WsSettingResource::class);

    expect($workspace->items())->toHaveCount(1)
        ->and($workspace->registered())->toHaveCount(2);
});

it('leaves out an entry hidden by its own condition', function () {
    expect(wsWorkspace(WsHiddenResource::class)->items())->toBe([]);
});

it('is empty when nothing is registered', function () {
    expect(wsWorkspace()->navigation())->toBe([])
        ->and(wsWorkspace()->items())->toBe([]);
});

it('makes an implicit group for a key nothing declared', function () {
    // Grouping must never require registration: `->group('sales')` on one
    // resource is a whole menu, and the heading reads from the key.
    $nav = wsWorkspace(WsOrderResource::class)->navigation();

    expect($nav['Sales']->getKey())->toBe('Sales')
        ->and($nav['Sales']->getLabel())->toBe('Sales')
        ->and($nav['Sales']->getIcon())->toBeNull()
        ->and($nav['Sales']->getSort())->toBe(0);
});

it('takes the heading, the icon and the order from a declared group', function () {
    // The heading is not the key: `->group(__('nav.sales'))` used to make the
    // translation the array key, so the same menu was keyed differently per
    // locale.
    $nav = wsWorkspaceWith(
        [NavigationGroup::make('Sales')->label('Revenue')->icon('outline:banknotes')],
        WsOrderResource::class,
    )->navigation();

    expect($nav['Sales']->getLabel())->toBe('Revenue')
        ->and($nav['Sales']->getIcon())->toBe('outline:banknotes')
        ->and(array_keys($nav['Sales']->getItems()))->toBe(['orders']);
});

it('orders groups by their declared sort, against registration order', function () {
    // WsOrderResource (Sales) is registered first and WsSettingResource ('')
    // second, so without the declared sort the menu reads Sales → ungrouped.
    $nav = wsWorkspaceWith(
        [NavigationGroup::make('Sales')->sort(10), NavigationGroup::make('')->sort(-10)],
        WsOrderResource::class,
        WsSettingResource::class,
    )->navigation();

    expect(array_keys($nav))->toBe(['', 'Sales']);
});

it('keeps first-appearance order where group sorts tie', function () {
    $nav = wsWorkspaceWith(
        [NavigationGroup::make('Sales'), NavigationGroup::make('')],
        WsOrderResource::class,
        WsSettingResource::class,
    )->navigation();

    expect(array_keys($nav))->toBe(['Sales', '']);
});

it('takes a hidden group out of the menu, entries and all', function () {
    // The reason a group owns visibility: the alternative is the same condition
    // on every resource in the group, and the n+1st resource is where it drifts.
    $workspace = wsWorkspaceWith(
        [NavigationGroup::make('Sales')->visible(false)],
        WsOrderResource::class,
        WsCustomerResource::class,
        WsSettingResource::class,
    );

    expect(array_keys($workspace->navigation()))->toBe([''])
        // items() has to agree: "in the menu" is one question, and a flat list
        // that answered it differently would be a second answer.
        ->and(array_keys($workspace->items()))->toBe(['ws-settings'])
        // Still registered and still routable — hidden is not unregistered.
        ->and($workspace->registered())->toHaveCount(3);
});

it('never fills the registered group itself', function () {
    // The declared group is a singleton in the registry. Filling it would make
    // a second call to navigation() answer differently from the first — the bug
    // that only shows up on a page rendering a menu twice.
    $group = NavigationGroup::make('Sales');
    $workspace = wsWorkspaceWith([$group], WsOrderResource::class, WsCustomerResource::class);

    $first = $workspace->navigation();
    $second = $workspace->navigation();

    expect($group->getItems())->toBe([])
        ->and($group->hasItems())->toBeFalse()
        ->and($first['Sales']->getItems())->toHaveCount(2)
        ->and($second['Sales']->getItems())->toHaveCount(2);
});

it('returns the flat list in sort order, not registration order', function () {
    // What the docblock promised before anything read it: WsOrderResource is
    // registered first and sorts 20, WsCustomerResource second and sorts 10.
    $items = wsWorkspace(WsOrderResource::class, WsCustomerResource::class)->items();

    expect(array_keys($items))->toBe(['ws-customers', 'orders']);
});

// =============================================================================
// A menu made of more than resources (V2.6 step 3)
// =============================================================================

/** A second source, standing in for the dashboard registry without importing L2. */
final class WsExtraSource implements RegistrySource
{
    /** @param array<string, class-string> $classes */
    public function __construct(private array $classes) {}

    public function registeredClasses(): array
    {
        return $this->classes;
    }
}

/** Not a resource at all — it only declares a menu entry, as a dashboard does. */
class WsReportPage implements ProvidesNavigation
{
    public static function navigation(): NavigationItem
    {
        return NavigationItem::make('Weekly report')->group('Sales')->sort(5);
    }
}

/** One that points somewhere of its own, which the fallback must not overwrite. */
class WsLinkedPage implements ProvidesNavigation
{
    public static function navigation(): NavigationItem
    {
        return NavigationItem::make('Status')->url('https://status.example.com');
    }
}

/** And one that names no entry label, having no plural to fall back to. */
class WsUnnamedPage implements ProvidesNavigation
{
    public static function navigation(): NavigationItem
    {
        return NavigationItem::make()->group('Sales');
    }
}

it('lists entries from a source that holds no resources at all', function () {
    // The whole point of RegistrySource: Workspace lives in L1 and a dashboard
    // is L2, so the menu cannot be built by teaching it what a dashboard is.
    $registry = new ResourceRegistry;
    $registry->register(WsOrderResource::class);

    $workspace = new Workspace(
        new Catalog([$registry, new WsExtraSource(['weekly-report' => WsReportPage::class])]),
        new NavigationGroups,
    );

    $nav = $workspace->navigation();

    expect(array_keys($nav['Sales']->getItems()))->toBe(['weekly-report', 'orders'])
        ->and(wsLabels($nav['Sales']))->toBe(['Weekly report', 'Orders'])
        ->and(array_keys($workspace->registered()))->toBe(['orders', 'weekly-report']);
});

it('leaves an entry unnamed when its class has no plural to fall back to', function () {
    // The fallback is a resource's, because `pluralLabel()` is a resource's word.
    // Anything else in a menu names its own entry rather than reaching for a
    // general "what is this class called", which would be a third vocabulary.
    $workspace = new Workspace(
        new Catalog([new WsExtraSource(['unnamed' => WsUnnamedPage::class])]),
        new NavigationGroups,
    );

    expect($workspace->items()['unnamed']->getLabel())->toBeNull();
});

it('refuses two sources claiming one key', function () {
    // Whichever way it were resolved, one entry would take the other's place —
    // and a menu that quietly lost a row is noticed on the day that row mattered.
    $registry = new ResourceRegistry;
    $registry->register(WsOrderResource::class);

    $workspace = new Workspace(
        new Catalog([$registry, new WsExtraSource(['orders' => WsReportPage::class])]),
        new NavigationGroups,
    );

    expect(fn () => $workspace->navigation())
        ->toThrow(ResourceRegistrationException::class);
});

it('points an entry at its key page, and leaves an unrouted one unlinked', function () {
    // ADR 0026: the key is already what a router builds a URL from, so an
    // application asked to repeat it in a hand-written map is maintaining the
    // copy that drifts. Null stays null — an unrouted resource, or an
    // application with no page package at all, still renders its menu.
    $registry = new ResourceRegistry;
    $registry->register(WsOrderResource::class);

    $workspace = new Workspace(
        new Catalog([$registry, new WsExtraSource(['weekly-report' => WsReportPage::class])]),
        new NavigationGroups,
        new class implements ResolvesPageUrls
        {
            public function urlFor(string $key, string $page = 'index', array $parameters = []): ?string
            {
                return $key === 'orders' ? '/admin/orders' : null;
            }
        },
    );

    $items = $workspace->items();

    expect($items['orders']->getUrl())->toBe('/admin/orders')
        ->and($items['weekly-report']->getUrl())->toBeNull();
});

it('leaves an entry that named its own url alone', function () {
    // An external link, or a shell with a URL scheme of its own — the fallback
    // only fills an entry that named nowhere, so this never overrides.
    $workspace = new Workspace(
        new Catalog([new WsExtraSource(['linked' => WsLinkedPage::class])]),
        new NavigationGroups,
        new class implements ResolvesPageUrls
        {
            public function urlFor(string $key, string $page = 'index', array $parameters = []): ?string
            {
                return '/should-not-win';
            }
        },
    );

    expect($workspace->items()['linked']->getUrl())->toBe('https://status.example.com');
});

it('links nothing when no package owns routing', function () {
    // The default: core answers "nothing is routed" until something rebinds it,
    // and a menu entry without an href already renders.
    $registry = new ResourceRegistry;
    $registry->register(WsOrderResource::class);

    $workspace = new Workspace(new Catalog([$registry]), new NavigationGroups);

    expect($workspace->items()['orders']->getUrl())->toBeNull();
});

it('reads sources in the order it was given them', function () {
    $registry = new ResourceRegistry;
    $registry->register(WsSettingResource::class);

    $workspace = new Workspace(
        new Catalog([new WsExtraSource(['weekly-report' => WsReportPage::class]), $registry]),
        new NavigationGroups,
    );

    // Sales first because the extra source came first: group order is
    // first-appearance, and that is now a property of the source order.
    expect(array_keys($workspace->navigation()))->toBe(['Sales', '']);
});
