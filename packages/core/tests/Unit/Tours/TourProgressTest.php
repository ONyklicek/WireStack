<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Preferences\Contracts\PreferenceDriver;
use NyonCode\WireCore\Foundation\Routing\Contracts\AuthorizesUrls;
use NyonCode\WireCore\Foundation\Routing\UnguardedUrls;
use NyonCode\WireCore\Tours\Tour;
use NyonCode\WireCore\Tours\TourAcknowledgement;
use NyonCode\WireCore\Tours\TourLedger;
use NyonCode\WireCore\Tours\Tours;
use NyonCode\WireCore\Tours\TourState;
use NyonCode\WireCore\Tours\TourStep;

/*
 * A tour left halfway, and picked up again where it was left.
 *
 * The browser tells the server each step it shows, and the ledger keeps the
 * last one beside the acknowledgements. What is worth pinning is what that
 * number is *not* allowed to do: outlive a finish, survive the author changing
 * the tour, or point past the tour's end because the browser said so.
 *
 * Where the browser opens a tour from the number it is handed is
 * `workbench/scripts/verify-demo-tour.mjs`.
 */

beforeEach(function () {
    $this->driver = new class implements PreferenceDriver
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
    };

    app()->instance(TourLedger::class, new TourLedger($this->driver));

    $this->tour = Tour::make('walk')->since('1')->steps([
        TourStep::make('widget-grid'),
        TourStep::make('widget-layout-edit'),
        TourStep::make('widget-layout-save-as'),
        TourStep::make('table-search'),
    ]);

    app(Tours::class)->register($this->tour);

    $this->user = new class extends User {};
    $this->user->id = 7;
});

it('remembers the step somebody reached, per tour and per person', function () {
    $ledger = app(TourLedger::class);

    $ledger->reach($this->tour, $this->user, 2);

    expect($ledger->reached($this->tour, $this->user))->toBe(2)
        ->and($ledger->reached($this->tour, null))->toBeNull()
        ->and(app(TourState::class)->reached($this->tour, $this->user))->toBe(2);
});

it('forgets it once the tour is finished, skipped or replayed', function () {
    $ledger = app(TourLedger::class);

    $ledger->reach($this->tour, $this->user, 2);
    $ledger->acknowledge($this->tour, $this->user);

    expect($ledger->reached($this->tour, $this->user))->toBeNull();

    // A replay starts from the top, not from wherever the last walk stopped.
    $ledger->reach($this->tour, $this->user, 3);
    $ledger->forget($this->tour, $this->user);

    expect($ledger->reached($this->tour, $this->user))->toBeNull();
});

it('does not carry a step number across a new version of the tour', function () {
    app(TourLedger::class)->reach($this->tour, $this->user, 2);

    // The author rewrote it: the third step may be gone, or be about something else.
    $rewritten = Tour::make('walk')->since('2')->steps($this->tour->getSteps());

    expect(app(TourLedger::class)->reached($rewritten, $this->user))->toBeNull();
});

it('keeps what else the bag holds, and ignores progress it did not write', function () {
    $this->driver->store['tours|7'] = [
        'app-owned' => ['kept' => true],
        TourLedger::REACHED => ['walk' => 'three', 'other' => ['since' => '1', 'step' => -1]],
    ];

    expect(app(TourLedger::class)->reached($this->tour, $this->user))->toBeNull();

    app(TourLedger::class)->reach($this->tour, $this->user, 1);

    expect($this->driver->store['tours|7']['app-owned'])->toBe(['kept' => true])
        ->and($this->driver->store['tours|7'][TourLedger::REACHED])->toBe(['walk' => ['since' => '1', 'step' => 1]]);
});

it('bounds the step the browser names to the tour it is in', function () {
    $state = app(TourState::class);

    foreach ([-1, 4, 99] as $step) {
        $state->reach('walk', $step, $this->user);
    }

    $state->reach('no-such-tour', 1, $this->user);

    expect(app(TourLedger::class)->reached($this->tour, $this->user))->toBeNull();

    $state->reach('walk', 3, $this->user);

    expect($state->reached($this->tour, $this->user))->toBe(3);
});

it('treats the first step as nothing to resume, and a stored step past the end as none', function () {
    $ledger = app(TourLedger::class);

    $ledger->reach($this->tour, $this->user, 0);

    expect(app(TourState::class)->reached($this->tour, $this->user))->toBeNull();

    // Written when the tour had more steps than it has now, under the same version.
    $ledger->reach($this->tour, $this->user, 9);

    expect(app(TourState::class)->reached($this->tour, $this->user))->toBeNull();
});

it('does not reopen a tour a late request arrives for after it was finished', function () {
    app(TourLedger::class)->acknowledge($this->tour, $this->user);

    app(TourState::class)->reach('walk', 2, $this->user);

    expect(app(TourLedger::class)->reached($this->tour, $this->user))->toBeNull();
});

it('is recorded through the tour s own component, for the tour it was mounted with', function () {
    auth()->setUser($this->user);

    Livewire::test(TourAcknowledgement::class, ['tourId' => 'walk'])
        ->assertSee('wire-tour:reached', escape: false)
        ->assertSee('$wire.reach($event.detail.step)', escape: false)
        ->call('reach', 2);

    expect(app(TourLedger::class)->reached($this->tour, $this->user))->toBe(2);
});

it('lets every URL through until something that routes says otherwise', function () {
    expect(new UnguardedUrls)->toBeInstanceOf(AuthorizesUrls::class)
        ->and((new UnguardedUrls)->allowsUrl('/anything', null))->toBeTrue();
});
