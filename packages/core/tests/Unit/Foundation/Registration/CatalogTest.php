<?php

declare(strict_types=1);

use NyonCode\WireCore\Exceptions\ResourceRegistrationException;
use NyonCode\WireCore\Foundation\Registration\Catalog;
use NyonCode\WireCore\Foundation\Registration\Contracts\RegistrySource;

/**
 * The one list the menu, the router and the search palette read (ADR 0026).
 *
 * What is worth asserting here is not that it concatenates arrays. It is the
 * three properties the three consumers rely on and used to each get wrong on
 * their own: order is registration order, a key means exactly one thing, and
 * "registered" is not "capable of anything in particular".
 */
final class CatSource implements RegistrySource
{
    /** @param array<string, class-string> $classes */
    public function __construct(private array $classes) {}

    public function registeredClasses(): array
    {
        return $this->classes;
    }
}

interface CatRoutable {}

class CatOrders implements CatRoutable {}

class CatInvoices {}

class CatReports implements CatRoutable {}

it('reads its sources in the order it was given them', function () {
    // Registration order is load-bearing downstream: it decides which menu group
    // appears first, and it is the only order a route file has.
    $catalog = new Catalog([
        new CatSource(['orders' => CatOrders::class]),
        new CatSource(['invoices' => CatInvoices::class]),
    ]);

    expect(array_keys($catalog->all()))->toBe(['orders', 'invoices']);
});

it('refuses two sources claiming one key', function () {
    // Whichever way it were resolved, one registration would take another's
    // place — silently in a menu, and at a URL prefix in the router.
    $catalog = new Catalog([
        new CatSource(['orders' => CatOrders::class]),
        new CatSource(['orders' => CatInvoices::class]),
    ]);

    expect(fn () => $catalog->all())->toThrow(ResourceRegistrationException::class);
});

it('accepts the same class registered twice under one key', function () {
    // Config merging and a provider booting twice in tests both do it; only two
    // *different* classes are the mistake.
    $catalog = new Catalog([
        new CatSource(['orders' => CatOrders::class]),
        new CatSource(['orders' => CatOrders::class]),
    ]);

    expect($catalog->all())->toBe(['orders' => CatOrders::class]);
});

it('enforces the key rule on every read, not only when a menu is drawn', function () {
    // The point of moving the rule out of Workspace: an application that routes
    // and searches while rendering its own navigation never calls the menu, and
    // was the one case the old placement could not protect.
    $catalog = new Catalog([
        new CatSource(['orders' => CatOrders::class]),
        new CatSource(['orders' => CatInvoices::class]),
    ]);

    expect(fn () => $catalog->find('orders'))->toThrow(ResourceRegistrationException::class)
        ->and(fn () => $catalog->has('orders'))->toThrow(ResourceRegistrationException::class)
        ->and(fn () => $catalog->implementing(CatRoutable::class))->toThrow(ResourceRegistrationException::class);
});

it('filters by a capability, because registered is not routed or listed', function () {
    $catalog = new Catalog([
        new CatSource([
            'orders' => CatOrders::class,
            'invoices' => CatInvoices::class,
            'reports' => CatReports::class,
        ]),
    ]);

    expect($catalog->implementing(CatRoutable::class))
        ->toBe(['orders' => CatOrders::class, 'reports' => CatReports::class]);
});

it('answers for one key without being asked for the whole list', function () {
    $catalog = new Catalog([new CatSource(['orders' => CatOrders::class])]);

    expect($catalog->find('orders'))->toBe(CatOrders::class)
        ->and($catalog->find('nothing'))->toBeNull()
        ->and($catalog->has('orders'))->toBeTrue()
        ->and($catalog->has('nothing'))->toBeFalse();
});

it('sees a class registered after it was constructed', function () {
    // Not memoized, deliberately: registries are filled during boot and a test
    // fills one mid-run, so a catalogue that cached its first read would answer
    // with whatever existed when something first happened to ask.
    $source = new class implements RegistrySource
    {
        /** @var array<string, class-string> */
        public array $classes = [];

        public function registeredClasses(): array
        {
            return $this->classes;
        }
    };

    $catalog = new Catalog([$source]);

    expect($catalog->all())->toBe([]);

    $source->classes = ['orders' => CatOrders::class];

    expect($catalog->all())->toBe(['orders' => CatOrders::class]);
});
