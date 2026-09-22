<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Preferences\Contracts\PreferenceDriver;
use NyonCode\WireCore\Foundation\Preferences\PreferenceManager;
use NyonCode\WireCore\Foundation\View\PageChrome;
use NyonCode\WireCore\Tours\Tour;
use NyonCode\WireCore\Tours\TourAcknowledgement;
use NyonCode\WireCore\Tours\TourHost;
use NyonCode\WireCore\Tours\TourLedger;
use NyonCode\WireCore\Tours\TourReplay;
use NyonCode\WireCore\Tours\Tours;
use NyonCode\WireCore\Tours\TourStep;

/**
 * The one round trip a tour makes, and the way back into one that is finished.
 *
 * Everything else a tour does happens in the browser over data the page already
 * carried. These two are the exceptions, and both are public Livewire methods —
 * which means the browser decides when they are called, so what they trust is
 * as much the subject here as what they do.
 */
beforeEach(function () {
    // A real driver, so an acknowledgement has somewhere to land and the test
    // asserts what was stored rather than that a mock was called.
    PreferenceManager::swap(new class implements PreferenceDriver
    {
        public array $store = [];

        public function load(string $surfaceKey, ?Authenticatable $user, ?string $view = null): array
        {
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
    });
});

afterEach(fn () => PreferenceManager::swap(null));

function aTour(string $id = 't', string $version = '1'): Tour
{
    $tour = Tour::make($id)->since($version)->steps([TourStep::make('table-search')]);

    app(Tours::class)->register($tour);

    return $tour;
}

function aSignedInUser(int $id = 1): User
{
    $user = new class extends User {};
    $user->id = $id;
    auth()->setUser($user);

    return $user;
}

it('records the tour it was mounted with', function () {
    $tour = aTour('getting-started', '2.2');
    $user = aSignedInUser();

    Livewire::test(TourAcknowledgement::class, ['tourId' => 'getting-started'])
        ->call('acknowledge');

    expect(app(TourLedger::class)->hasSeen($tour, $user))->toBeTrue();
});

/**
 * `acknowledge()` is a public Livewire method, so the browser decides when it is
 * called. It takes no arguments on purpose: the tour is the one the component
 * was mounted with, held server-side across the round trip. A method that took
 * an id would let any page acknowledge any tour for the person looking at it.
 */
it('takes no id from the browser', function () {
    $mine = aTour('mine');
    $other = aTour('other');
    $user = aSignedInUser();

    Livewire::test(TourAcknowledgement::class, ['tourId' => 'mine'])
        ->call('acknowledge', 'other');

    expect(app(TourLedger::class)->hasSeen($mine, $user))->toBeTrue()
        ->and(app(TourLedger::class)->hasSeen($other, $user))->toBeFalse();
});

it('records the version that was current, so a bump shows the tour again', function () {
    aTour('t', '2.1');
    $user = aSignedInUser();

    Livewire::test(TourAcknowledgement::class, ['tourId' => 't'])->call('acknowledge');

    $ledger = app(TourLedger::class);

    expect($ledger->hasSeen(Tour::make('t')->since('2.1')->steps([TourStep::make('table-search')]), $user))->toBeTrue()
        ->and($ledger->hasSeen(Tour::make('t')->since('2.2')->steps([TourStep::make('table-search')]), $user))->toBeFalse();
});

/**
 * A tour that blew up for a guest would take the page's whole Livewire layer
 * with it. The guest driver is the session, so this is a real write — just one
 * that lasts as long as the session does.
 */
it('does not throw for a guest, and remembers them for their session', function () {
    $tour = aTour();

    Livewire::test(TourAcknowledgement::class, ['tourId' => 't'])->call('acknowledge');

    expect(app(TourLedger::class)->hasSeen($tour, null))->toBeTrue();
});

it('ignores an id the registry does not know', function () {
    aSignedInUser();

    Livewire::test(TourAcknowledgement::class, ['tourId' => 'removed-last-release'])
        ->call('acknowledge')
        ->assertOk();
});

it('claims the browser event by id before spending a round trip', function () {
    aTour();

    Livewire::test(TourAcknowledgement::class, ['tourId' => 't'])
        ->assertSee('wire-tour:done', escape: false)
        ->assertSee('$wire.acknowledge()', escape: false);
});

// ── Replay ──────────────────────────────────────────────────────────────────

it('offers a replay in the user menu', function () {
    expect(app(PageChrome::class)->has('wire-core::tours.replay-entry', PageChrome::USER_MENU))->toBeTrue();
});

it('is not mounted at all on a screen no tour claims', function () {
    aSignedInUser();

    // The view registered with PageChrome decides this, so an application with
    // no tours never pays for a Livewire component here — no snapshot, no
    // checksum, on any page.
    $rendered = view('wire-core::tours.replay-entry', ['replayableTour' => null])->render();

    expect(trim($rendered))->toBe('');
});

/**
 * The entry is for the tour that has *been* seen, which is exactly the one
 * `TourHost::current()` has stopped answering with — hence a second resolver
 * that ignores the ledger.
 */
it('offers the tour claiming this screen even once it has been acknowledged', function () {
    $tour = aTour();
    $user = aSignedInUser();

    app(TourLedger::class)->acknowledge($tour, $user);

    expect(app(TourHost::class)->replayable()?->getId())->toBe($tour->getId());

    Livewire::test(TourReplay::class, ['tourId' => $tour->getId()])
        ->assertSee(__('wire-core::messages.tour_replay'));
});

it('forgets the tour, so the page runs it again', function () {
    $tour = aTour();
    $user = aSignedInUser();

    app(TourLedger::class)->acknowledge($tour, $user);

    Livewire::test(TourReplay::class, ['tourId' => $tour->getId()])
        ->call('replay')
        ->assertDispatched('wire-tour:forgotten');

    expect(app(TourLedger::class)->hasSeen($tour, $user))->toBeFalse();
});

/**
 * The obvious implementation keeps the page's URL in a property and redirects to
 * it. Every public Livewire property is writable from the browser, so that
 * property is an open redirect waiting for somebody to be handed a link that
 * sets it. The browser reloads itself instead, and no address is stored at all.
 */
it('holds no address the browser could point somewhere else', function () {
    $public = array_map(
        static fn (ReflectionProperty $property): string => $property->getName(),
        array_filter(
            (new ReflectionClass(TourReplay::class))->getProperties(ReflectionProperty::IS_PUBLIC),
            static fn (ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === TourReplay::class,
        ),
    );

    expect(array_values($public))->toBe(['tourId']);

    aTour();
    aSignedInUser();

    Livewire::test(TourReplay::class, ['tourId' => 't'])->call('replay')->assertNoRedirect();
});
