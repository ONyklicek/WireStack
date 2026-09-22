<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use NyonCode\WireCore\Exceptions\TourDefinitionException;
use NyonCode\WireCore\Foundation\Preferences\Contracts\PreferenceDriver;
use NyonCode\WireCore\Tours\Tour;
use NyonCode\WireCore\Tours\TourLedger;
use NyonCode\WireCore\Tours\Tours;
use NyonCode\WireCore\Tours\TourState;
use NyonCode\WireCore\Tours\TourStep;

/**
 * The registry, the acknowledgement ledger, and the one question the host view
 * asks: which tour, if any, does this person see on this screen.
 *
 * The driver here is an in-memory one rather than a swapped global, so each test
 * owns its store and the bag can be inspected directly — including the case the
 * ledger exists to survive, a bag holding keys it did not write.
 */
function inMemoryDriver(array $initial = []): PreferenceDriver
{
    return new class($initial) implements PreferenceDriver
    {
        public function __construct(public array $store) {}

        public function load(string $surfaceKey, ?Authenticatable $user, ?string $view = null): array
        {
            return $this->store[$this->at($surfaceKey, $user)] ?? [];
        }

        public function save(string $surfaceKey, ?Authenticatable $user, array $preferences, ?string $view = null): void
        {
            $this->store[$this->at($surfaceKey, $user)] = $preferences;
        }

        public function forget(string $surfaceKey, ?Authenticatable $user, ?string $view = null): void
        {
            unset($this->store[$this->at($surfaceKey, $user)]);
        }

        public function views(string $surfaceKey, ?Authenticatable $user): array
        {
            return [];
        }

        private function at(string $surfaceKey, ?Authenticatable $user): string
        {
            return $surfaceKey.'|'.($user?->getAuthIdentifier() ?? 'guest');
        }
    };
}

function someone(int $id = 1): Authenticatable
{
    $user = new class extends User {};
    $user->id = $id;

    return $user;
}

function step(): TourStep
{
    return TourStep::make('table-search');
}

// ── The registry ────────────────────────────────────────────────────────────

it('starts empty, because the framework ships no tour of its own', function () {
    expect((new Tours)->all())->toBe([]);
});

it('keeps the first registration of an id and ignores a later one', function () {
    $tours = new Tours;

    $tours->register(Tour::make('t')->since('1')->steps([step()]));
    $tours->register(Tour::make('t')->since('2')->steps([step()]));

    expect($tours->all())->toHaveCount(1)
        ->and($tours->get('t')?->getVersion())->toBe('1');
});

it('registers several at once', function () {
    $tours = new Tours;

    $tours->register(
        Tour::make('a')->steps([step()]),
        Tour::make('b')->steps([step()]),
    );

    expect($tours->has('a'))->toBeTrue()
        ->and($tours->has('b'))->toBeTrue()
        ->and($tours->get('missing'))->toBeNull()
        ->and($tours->has('missing'))->toBeFalse();
});

it('refuses a stepless tour at registration, where the definition is finished', function () {
    expect(fn () => (new Tours)->register(Tour::make('empty')))
        ->toThrow(TourDefinitionException::class);
});

it('orders by sort, lowest first', function () {
    $tours = new Tours;

    $tours->register(
        Tour::make('late')->sort(10)->steps([step()]),
        Tour::make('early')->sort(-10)->steps([step()]),
        Tour::make('middle')->steps([step()]),
    );

    expect(array_map(fn (Tour $t): string => $t->getId(), $tours->all()))
        ->toBe(['early', 'middle', 'late']);
});

/**
 * An application with one tour per screen never sorts, so equal sorts must keep
 * the order they were registered in rather than an arbitrary one.
 */
it('keeps registration order among tours that do not sort themselves', function () {
    $tours = new Tours;

    $tours->register(
        Tour::make('first')->steps([step()]),
        Tour::make('second')->steps([step()]),
        Tour::make('third')->steps([step()]),
    );

    expect(array_map(fn (Tour $t): string => $t->getId(), $tours->all()))
        ->toBe(['first', 'second', 'third']);
});

// ── The ledger ──────────────────────────────────────────────────────────────

it('has seen nothing until something is acknowledged', function () {
    $ledger = new TourLedger(inMemoryDriver());
    $tour = Tour::make('t')->steps([step()]);

    expect($ledger->acknowledged(someone()))->toBe([])
        ->and($ledger->hasSeen($tour, someone()))->toBeFalse();
});

it('records the version that was current when it was acknowledged', function () {
    $driver = inMemoryDriver();
    $ledger = new TourLedger($driver);
    $tour = Tour::make('t')->since('2.2')->steps([step()]);

    $ledger->acknowledge($tour, someone());

    expect($ledger->acknowledged(someone()))->toBe(['t' => '2.2'])
        ->and($ledger->hasSeen($tour, someone()))->toBeTrue();
});

/**
 * The whole of the what's-new mechanism: bumping `since()` makes the stored
 * value unequal, and unequal is all "show it again" means. No ordering, no
 * package version, no second store.
 */
it('shows a tour again once its version is bumped', function () {
    $ledger = new TourLedger(inMemoryDriver());

    $ledger->acknowledge(Tour::make('t')->since('2.1')->steps([step()]), someone());

    expect($ledger->hasSeen(Tour::make('t')->since('2.2')->steps([step()]), someone()))->toBeFalse();
});

it("keeps each person's acknowledgements apart", function () {
    $ledger = new TourLedger(inMemoryDriver());
    $tour = Tour::make('t')->steps([step()]);

    $ledger->acknowledge($tour, someone(1));

    expect($ledger->hasSeen($tour, someone(1)))->toBeTrue()
        ->and($ledger->hasSeen($tour, someone(2)))->toBeFalse();
});

it('forgets one tour without disturbing the others', function () {
    $ledger = new TourLedger(inMemoryDriver());
    $a = Tour::make('a')->steps([step()]);
    $b = Tour::make('b')->steps([step()]);

    $ledger->acknowledge($a, someone());
    $ledger->acknowledge($b, someone());
    $ledger->forget($a, someone());

    expect($ledger->hasSeen($a, someone()))->toBeFalse()
        ->and($ledger->hasSeen($b, someone()))->toBeTrue();
});

/**
 * The bag is shared and open by contract — a driver must keep keys it does not
 * recognise — so this surface must not erase what another one stored.
 */
it("leaves another surface's keys in the bag alone", function () {
    $driver = inMemoryDriver(['tours|1' => ['somethingElse' => ['keep' => 'me']]]);
    $ledger = new TourLedger($driver);

    $ledger->acknowledge(Tour::make('t')->steps([step()]), someone());

    expect($driver->store['tours|1']['somethingElse'])->toBe(['keep' => 'me'])
        ->and($driver->store['tours|1']['tours'])->toBe(['t' => '1']);
});

it('treats a bag whose tours key is not a map as nothing acknowledged', function () {
    $ledger = new TourLedger(inMemoryDriver(['tours|1' => ['tours' => 'corrupted']]));

    expect($ledger->acknowledged(someone()))->toBe([]);
});

it('drops entries in the bag that could not have been written here', function () {
    $ledger = new TourLedger(inMemoryDriver([
        'tours|1' => ['tours' => ['good' => '2.2', 'bad' => ['a' => 'b'], 7 => '1']],
    ]));

    expect($ledger->acknowledged(someone()))->toBe(['good' => '2.2']);
});

it("stores a guest's acknowledgement separately from a user's", function () {
    $ledger = new TourLedger(inMemoryDriver());
    $tour = Tour::make('t')->steps([step()]);

    $ledger->acknowledge($tour, null);

    expect($ledger->hasSeen($tour, null))->toBeTrue()
        ->and($ledger->hasSeen($tour, someone()))->toBeFalse();
});

// ── Matching ────────────────────────────────────────────────────────────────

function stateWith(PreferenceDriver $driver, Tour ...$tours): TourState
{
    $registry = new Tours;
    $registry->register(...$tours);

    return new TourState($registry, new TourLedger($driver));
}

it('answers with nothing when no tour is registered', function () {
    expect(stateWith(inMemoryDriver())->match(null, null, null, someone()))->toBeNull();
});

it('answers with the tour that claims the screen', function () {
    $state = stateWith(
        inMemoryDriver(),
        Tour::make('sales')->zones('sales')->steps([step()]),
        Tour::make('admin')->zones('admin')->steps([step()]),
    );

    expect($state->match('sales.', null, null, someone())?->getId())->toBe('sales')
        ->and($state->match('admin.', null, null, someone())?->getId())->toBe('admin')
        ->and($state->match('other.', null, null, someone()))->toBeNull();
});

it('lets the lowest sort win when two tours claim one screen', function () {
    $state = stateWith(
        inMemoryDriver(),
        Tour::make('sales')->steps([step()]),
        Tour::make('admin')->sort(-10)->steps([step()]),
    );

    expect($state->match(null, null, null, someone())?->getId())->toBe('admin');
});

/**
 * The loser is not lost — it is the next one to match once the winner has been
 * acknowledged. That is what "the rest wait for another visit" means.
 */
it('offers the next tour once the winner has been acknowledged', function () {
    $driver = inMemoryDriver();
    $state = stateWith(
        $driver,
        Tour::make('sales')->steps([step()]),
        Tour::make('admin')->sort(-10)->steps([step()]),
    );

    $state->acknowledge('admin', someone());

    expect($state->match(null, null, null, someone())?->getId())->toBe('sales');
});

it('stops offering a tour once it has been acknowledged', function () {
    $state = stateWith(inMemoryDriver(), Tour::make('t')->steps([step()]));

    expect($state->match(null, null, null, someone())?->getId())->toBe('t');

    $state->acknowledge('t', someone());

    expect($state->match(null, null, null, someone()))->toBeNull();
});

it('offers a tour again after a replay', function () {
    $state = stateWith(inMemoryDriver(), Tour::make('t')->steps([step()]));

    $state->acknowledge('t', someone());
    $state->replay('t', someone());

    expect($state->match(null, null, null, someone())?->getId())->toBe('t');
});

/**
 * Both are public Livewire entry points, so the browser decides what it sends.
 * An id that is not registered means the tour was removed between the page
 * rendering and the click — nothing to record, and nothing to complain about.
 */
it('ignores an acknowledgement or a replay for an id it does not know', function () {
    $state = stateWith(inMemoryDriver(), Tour::make('t')->steps([step()]));

    $state->acknowledge('nonexistent', someone());
    $state->replay('nonexistent', someone());

    expect($state->match(null, null, null, someone())?->getId())->toBe('t');
});

it("keeps one person's acknowledgement from silencing another's tour", function () {
    $driver = inMemoryDriver();
    $state = stateWith($driver, Tour::make('t')->steps([step()]));

    $state->acknowledge('t', someone(1));

    expect($state->match(null, null, null, someone(1)))->toBeNull()
        ->and($state->match(null, null, null, someone(2))?->getId())->toBe('t');
});
