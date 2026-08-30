<?php

declare(strict_types=1);

use NyonCode\WireBoost\Support\ResourceReflector;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;

/*
 * What an application's resources declare, for the describe-resource tool.
 *
 * Separate from ComponentReflector because a resource is not a Livewire
 * component: there is no host to instantiate and no surface to build. Which
 * surfaces exist is reported as "does it implement the contract", and the reason
 * is the same one that makes identity static — composing them would cost exactly
 * what the static half exists to avoid.
 */
class RrfOrderResource implements DescribesResource, ProvidesNavigation, ProvidesResourceInfolist
{
    use DescribesRecords;

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
        return NavigationItem::make('Orders')->icon('outline:cart')->group('Sales')->sort(20);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('number')]);
    }
}

class RrfPlainResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }
}

beforeEach(function () {
    $registry = new ResourceRegistry;
    $registry->register(RrfOrderResource::class);
    $registry->register(RrfPlainResource::class);

    $this->reflector = new ResourceReflector($registry);
});

it('reports identity and which surfaces a resource declares', function () {
    $described = $this->reflector->describe('orders');

    expect($described['key'])->toBe('orders')
        ->and($described['class'])->toBe(RrfOrderResource::class)
        ->and($described['label'])->toBe('Order')
        ->and($described['pluralLabel'])->toBe('Orders')
        ->and($described['surfaces']['infolist'])->toBeTrue()
        ->and($described['surfaces']['form'])->toBeFalse()
        ->and($described['surfaces']['table'])->toBeFalse();
});

it('reports the navigation entry where there is one', function () {
    expect($this->reflector->describe('orders')['navigation'])->toBe([
        'label' => 'Orders',
        'icon' => 'outline:cart',
        'group' => 'Sales',
        'sort' => 20,
        'visible' => true,
    ]);
});

it('omits navigation for a resource that declares none', function () {
    // Registered and routable, just not in the menu.
    expect($this->reflector->describe('rrf-plains'))->not->toHaveKey('navigation');
});

it('accepts a class name as well as a key', function () {
    // Which is what a developer reading their own config has in front of them.
    expect($this->reflector->describe(RrfOrderResource::class)['key'])->toBe('orders')
        ->and($this->reflector->describe('\\'.RrfOrderResource::class)['key'])->toBe('orders');
});

it('answers null for something that is not registered', function () {
    expect($this->reflector->describe('nope'))->toBeNull();
});

it('lists every registered resource', function () {
    expect(array_column($this->reflector->all(), 'key'))->toBe(['orders', 'rrf-plains']);
});

it('reports a surface from a package that is not installed as absent', function () {
    // The contracts for table and form live downstream and boost only requires
    // wire-core, so this must answer without them on the autoloader.
    $described = $this->reflector->describe('rrf-plains');

    expect($described['surfaces'])->toHaveKeys(['table', 'form', 'infolist', 'relationManagers'])
        ->and(array_filter($described['surfaces']))->toBe([]);
});
