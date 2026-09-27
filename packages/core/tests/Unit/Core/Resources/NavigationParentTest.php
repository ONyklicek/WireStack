<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Plugin\Hooks\NavigationBuildingPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\ActiveNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroups;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\Navigation\NestNavigationEntries;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Core\Resources\Workspace;
use NyonCode\WireCore\Exceptions\NavigationParentException;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireCore\Foundation\Registration\Catalog;
use NyonCode\WireCore\Foundation\Registration\Contracts\HasRegistryKey;
use NyonCode\WireCore\Foundation\Routing\Contracts\BelongsToCluster;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;

/*
 * An entry that names its parent, rather than a parent that lists its children.
 *
 * What is worth pinning is where each entry ends up in the grouped menu, and
 * that nothing is lost on the way: the flat list still holds every entry, a
 * parent that is a page stays reachable, and a parent the user cannot see does
 * not take a page they can see with it.
 */
abstract class NpResource implements DescribesResource, ProvidesNavigation
{
    use DescribesRecords;

    /** @var array<class-string, string|null> */
    public static array $parents = [];

    /** @var array<class-string, bool> */
    public static array $hidden = [];

    /** @var array<class-string, int> */
    public static array $sorts = [];

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make()
            ->icon('outline:cube')
            ->group('shop')
            ->sort(self::$sorts[static::class] ?? 0)
            ->parent(self::$parents[static::class] ?? null)
            ->hidden(self::$hidden[static::class] ?? false);
    }
}

class NpProductResource extends NpResource {}
class NpCategoryResource extends NpResource {}
class NpBrandResource extends NpResource {}
class NpInternalResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }
}

function npWorkspace(): Workspace
{
    $registry = new ResourceRegistry;
    $registry->registerMany([NpProductResource::class, NpCategoryResource::class, NpBrandResource::class, NpInternalResource::class]);

    return new Workspace(new Catalog([$registry]), new NavigationGroups, new class implements ResolvesPageUrls
    {
        public function urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
        {
            return "/admin/{$key}";
        }
    });
}

/** @return array<int, string|null> */
function npChildLabels(NavigationItem $item): array
{
    return array_map(static fn (NavigationItem $child): ?string => $child->getLabel(), $item->getChildren());
}

beforeEach(function () {
    NpResource::$parents = [];
    NpResource::$hidden = [];
    NpResource::$sorts = [];
});

it('moves an entry under the parent it names, by class or by key', function () {
    NpResource::$parents = [NpCategoryResource::class => NpProductResource::class, NpBrandResource::class => 'np-products'];
    NpResource::$sorts = [NpCategoryResource::class => 20, NpBrandResource::class => 10];

    $shop = npWorkspace()->navigation()['shop'];

    expect(array_keys($shop->getItems()))->toBe(['np-products'])
        ->and(npChildLabels($shop->getItems()['np-products']))->toBe(['Np Products', 'Np Brands', 'Np Categories']);
});

it('repeats a parent that is a page as its own first child, keyed like its row', function () {
    // A branch is a disclosure rather than a link, so without the copy the
    // products list would have no way in from the menu at all.
    NpResource::$parents = [NpCategoryResource::class => NpProductResource::class];

    $children = npWorkspace()->navigation()['shop']->getItems()['np-products']->getChildren();

    expect($children[0]->getUrl())->toBe('/admin/np-products')
        ->and($children[0]->getKey())->toBe('np-products')
        ->and($children[0]->getIcon())->toBe('outline:cube')
        ->and($children[0]->hasChildren())->toBeFalse();
});

it('adds no copy of a parent that is not a page', function () {
    $item = NavigationItem::make('Catalogue')->key('catalogue');

    $nested = (new NestNavigationEntries)(
        ['catalogue' => $item, 'np-brands' => NavigationItem::make('Brands')->url('/b')->parent('catalogue')],
        ['catalogue' => 'Catalogue', 'np-brands' => 'Brands'],
    );

    expect(npChildLabels($nested['catalogue']))->toBe(['Brands']);
});

it('keeps the children a parent wrote itself, before the ones that named it', function () {
    $parent = NavigationItem::make('Catalogue')->children([NavigationItem::make('Written')->sort(5)]);

    $copy = $parent->withAdoptedChildren([NavigationItem::make('Adopted')->sort(5)]);

    expect(npChildLabels($copy))->toBe(['Written', 'Adopted'])
        ->and(npChildLabels($parent))->toBe(['Written']);
});

it('leaves every entry in the flat list, where a palette searches for it', function () {
    NpResource::$parents = [NpCategoryResource::class => NpProductResource::class];

    expect(array_keys(npWorkspace()->items()))->toBe(['np-products', 'np-categories', 'np-brands']);
});

it('leaves an entry in its own group when its parent is not in this menu', function () {
    // Hidden from this user: the child is a page they may still open.
    NpResource::$parents = [NpCategoryResource::class => NpProductResource::class];
    NpResource::$hidden = [NpProductResource::class => true];

    expect(array_keys(npWorkspace()->navigation()['shop']->getItems()))->toBe(['np-categories', 'np-brands']);
});

it('treats a registered class with no menu entry as a parent that is simply absent', function () {
    NpResource::$parents = [NpCategoryResource::class => NpInternalResource::class];

    expect(npWorkspace()->navigation()['shop']->getItems())->toHaveKey('np-categories');
});

it('can nest under an entry a hook added', function () {
    app(PluginManager::class)->hook(Hook::NavigationBuilding, function (NavigationBuildingPayload $payload) {
        $payload->items['catalogue'] = NavigationItem::make('Catalogue')->group('shop');

        return $payload;
    });

    NpResource::$parents = [NpCategoryResource::class => 'catalogue'];

    $shop = npWorkspace()->navigation()['shop'];

    expect(npChildLabels($shop->getItems()['catalogue']))->toBe(['Np Categories']);
});

it('refuses a parent nothing registered', function () {
    NpResource::$parents = [NpCategoryResource::class => 'np-product'];

    npWorkspace()->navigation();
})->throws(NavigationParentException::class, 'names [np-product] as its parent, and nothing is registered');

it('refuses an entry that is its own parent', function () {
    NpResource::$parents = [NpCategoryResource::class => NpCategoryResource::class];

    npWorkspace()->navigation();
})->throws(NavigationParentException::class, 'names itself');

it('refuses a third level rather than dropping it', function () {
    NpResource::$parents = [NpCategoryResource::class => NpProductResource::class, NpBrandResource::class => NpCategoryResource::class];

    npWorkspace()->navigation();
})->throws(NavigationParentException::class, 'already sits under [np-products]');

it('keeps an adopted entry lit on every page of its resource', function () {
    NpResource::$parents = [NpCategoryResource::class => NpProductResource::class];

    $parent = npWorkspace()->navigation()['shop']->getItems()['np-products'];
    $onCategoryEdit = new ActiveNavigation(key: 'np-categories', page: 'edit', url: 'http://localhost/admin/np-categories/3/edit');

    expect($onCategoryEdit->hasActiveChild($parent))->toBeTrue()
        ->and($onCategoryEdit->isActive($parent->getChildren()[1]))->toBeTrue()
        ->and($onCategoryEdit->isActive($parent->getChildren()[0]))->toBeFalse();
});

it('carries the registered key on every entry, without overriding one already set', function () {
    expect(npWorkspace()->items()['np-brands']->getKey())->toBe('np-brands')
        ->and(NavigationItem::make('x')->key('mine')->getKey())->toBe('mine');
});

class NpSectionPage implements HasRegistryKey
{
    public static function key(): string
    {
        return 'np-section';
    }
}

it('leaves a registered cluster member out of the grouped menu and in the flat one', function () {
    $member = new class implements BelongsToCluster
    {
        public static ?string $cluster = NpSectionPage::class;

        public static function cluster(): ?string
        {
            return self::$cluster;
        }
    };

    $nest = new NestNavigationEntries;
    $items = ['np-member' => NavigationItem::make('Member'), 'np-other' => NavigationItem::make('Other')];

    expect(array_keys($nest($items, ['np-member' => $member::class, 'np-section' => NpSectionPage::class])))->toBe(['np-other'])
        // A cluster nobody registered takes nothing away; the router refuses it.
        ->and(array_keys($nest($items, ['np-member' => $member::class])))->toBe(['np-member', 'np-other']);

    $member::$cluster = null;

    expect(array_keys($nest($items, ['np-member' => $member::class, 'np-section' => NpSectionPage::class])))->toBe(['np-member', 'np-other']);
});
