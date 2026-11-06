<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use NyonCode\WireCore\Foundation\Preferences\Contracts\PreferenceDriver;
use NyonCode\WireCore\Tours\Tour;
use NyonCode\WireCore\Tours\TourDestination;
use NyonCode\WireCore\Tours\TourLedger;
use NyonCode\WireCore\Tours\Tours;
use NyonCode\WireCore\Tours\TourState;
use NyonCode\WireCore\Tours\TourStep;

/**
 * How often the ledger goes to the store.
 *
 * Picking the tour for a page asks two questions of every registered tour, and
 * on the database driver each one was a query of the same row — a dozen tours
 * was two dozen queries on every page, before the tour itself was even decided.
 */
function countingLedgerDriver(): PreferenceDriver
{
    return new class implements PreferenceDriver
    {
        public array $store = [];

        public int $loads = 0;

        public function load(string $surfaceKey, ?Authenticatable $user, ?string $view = null): array
        {
            $this->loads++;

            return $this->store[$surfaceKey.'|'.($user?->getAuthIdentifier() ?? 'guest')] ?? [];
        }

        public function save(string $surfaceKey, ?Authenticatable $user, array $preferences, ?string $view = null): void
        {
            $this->store[$surfaceKey.'|'.($user?->getAuthIdentifier() ?? 'guest')] = $preferences;
        }

        public function forget(string $surfaceKey, ?Authenticatable $user, ?string $view = null): void
        {
            unset($this->store[$surfaceKey.'|'.($user?->getAuthIdentifier() ?? 'guest')]);
        }

        public function views(string $surfaceKey, ?Authenticatable $user): array
        {
            return [];
        }
    };
}

function ledgerReader(int $id): Authenticatable
{
    return tap(new User)->forceFill(['id' => $id]);
}

function ledgerTour(string $id): Tour
{
    return Tour::make($id)->since('1')->steps([TourStep::make('table-search')]);
}

it('reads the store once to pick the tour for a page, however many tours there are', function (): void {
    $driver = countingLedgerDriver();
    $ledger = new TourLedger($driver);
    $user = ledgerReader(7);

    $registry = new Tours;
    $registry->register(...array_map(ledgerTour(...), ['a', 'b', 'c', 'd', 'e', 'f']));

    // Every tour seen: all six are asked about, none matches.
    foreach ($registry->all() as $tour) {
        $ledger->acknowledge($tour, $user);
    }
    $driver->loads = 0;

    $state = new TourState($registry, $ledger, app(TourDestination::class));

    expect($state->match(null, null, null, $user))->toBeNull()
        ->and($driver->loads)->toBe(1);
});

it('answers from what it just wrote', function (): void {
    $driver = countingLedgerDriver();
    $ledger = new TourLedger($driver);
    $user = ledgerReader(7);
    $tour = ledgerTour('a');

    expect($ledger->hasSeen($tour, $user))->toBeFalse();

    $ledger->acknowledge($tour, $user);
    expect($ledger->hasSeen($tour, $user))->toBeTrue();

    $ledger->forget($tour, $user);
    expect($ledger->hasSeen($tour, $user))->toBeFalse();

    $ledger->reach($tour, $user, 2);
    expect($ledger->reached($tour, $user))->toBe(2);

    $ledger->postpone($tour, $user);
    expect($ledger->postponements($tour, $user))->toBe(1);
});

it('keeps one user\'s answers apart from another\'s, and a guest\'s apart from both', function (): void {
    $driver = countingLedgerDriver();
    $ledger = new TourLedger($driver);
    $tour = ledgerTour('a');
    $seen = ledgerReader(7);

    $ledger->acknowledge($tour, $seen);

    expect($ledger->hasSeen($tour, $seen))->toBeTrue()
        ->and($ledger->hasSeen($tour, ledgerReader(8)))->toBeFalse()
        ->and($ledger->hasSeen($tour, null))->toBeFalse()
        ->and($ledger->hasSeen($tour, null))->toBeFalse()
        // the guest bag is read once as well
        ->and($driver->loads)->toBe(4);
});

it('reads afresh in a new ledger, so nothing outlives the lookup it was made for', function (): void {
    $driver = countingLedgerDriver();
    $user = ledgerReader(7);
    $tour = ledgerTour('a');

    expect((new TourLedger($driver))->hasSeen($tour, $user))->toBeFalse();

    // Stored behind the first ledger's back, as another request would.
    (new TourLedger($driver))->acknowledge($tour, $user);

    expect((new TourLedger($driver))->hasSeen($tour, $user))->toBeTrue();
});
