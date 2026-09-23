<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use NyonCode\WireCore\Foundation\Preferences\Contracts\PreferenceDriver;
use NyonCode\WireCore\Foundation\Routing\Contracts\AuthorizesUrls;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;
use NyonCode\WireCore\Tours\Tour;
use NyonCode\WireCore\Tours\TourDestination;
use NyonCode\WireCore\Tours\TourHost;
use NyonCode\WireCore\Tours\TourLedger;
use NyonCode\WireCore\Tours\Tours;
use NyonCode\WireCore\Tours\TourState;
use NyonCode\WireCore\Tours\TourStep;
use NyonCode\WireCore\Tours\TourWelcome;

/**
 * The block a tour opens with, and the third answer it makes possible.
 *
 * Before this, a tour had two outcomes and both were final: finish and skip are
 * one call, because somebody who skipped has decided. "Later" is the answer that
 * could not be given — not now, ask again — and it needs two things a boolean
 * could not carry: a scope, so it does not quietly mean "never" on a driver that
 * remembers for ever, and a count, so it does not mean "never, one sitting at a
 * time" either.
 *
 * What these cannot see is the greeting itself — whether the card lands centred,
 * whether Escape dismisses it, whether "Start" then points at the right element.
 * That is `workbench/scripts/verify-tour.mjs`.
 */
function welcomeDriver(array $initial = []): PreferenceDriver
{
    return new class($initial) implements PreferenceDriver
    {
        public function __construct(public array $store) {}

        public function load(string $surfaceKey, ?Authenticatable $user, ?string $view = null): array
        {
            return $this->store[$surfaceKey] ?? [];
        }

        public function save(string $surfaceKey, ?Authenticatable $user, array $preferences, ?string $view = null): void
        {
            $this->store[$surfaceKey] = $preferences;
        }

        public function forget(string $surfaceKey, ?Authenticatable $user, ?string $view = null): void
        {
            unset($this->store[$surfaceKey]);
        }

        public function views(string $surfaceKey, ?Authenticatable $user): array
        {
            return [];
        }
    };
}

function walker(): Authenticatable
{
    return tap(new User)->forceFill(['id' => 7]);
}

/** A session id Laravel will accept: anything else is silently replaced. */
function sittingIn(string $letter): void
{
    session()->setId(str_repeat($letter, 40));
}

function welcomeState(PreferenceDriver $driver, Tour ...$tours): TourState
{
    $registry = new Tours;
    $registry->register(...$tours);

    return new TourState($registry, new TourLedger($driver), app(TourDestination::class));
}

function greeter(): Tour
{
    return Tour::make('greeted')
        ->welcome(TourWelcome::make()->heading('Welcome'))
        ->steps([TourStep::make('table-search')]);
}

function renderWelcomeHost(): string
{
    $host = app(TourHost::class);

    return Blade::render(
        file_get_contents(__DIR__.'/../../../resources/views/tours/host.blade.php'),
        ['tourHost' => $host, 'tour' => $host->current()],
    );
}

// ── The definition ──────────────────────────────────────────────────────────

it('carries what the author wrote and nothing it was not given', function () {
    $welcome = TourWelcome::make()
        ->heading('Welcome aboard')
        ->text('A minute, and you will know where everything is.')
        ->start('Show me')
        ->later('Not now')
        ->view('tours.mine');

    expect($welcome->getHeading())->toBe('Welcome aboard')
        ->and($welcome->getText())->toBe('A minute, and you will know where everything is.')
        ->and($welcome->getStart())->toBe('Show me')
        ->and($welcome->getLater())->toBe('Not now')
        ->and($welcome->getView())->toBe('tours.mine');

    $bare = TourWelcome::make();

    expect($bare->getHeading())->toBeNull()
        ->and($bare->getText())->toBeNull()
        ->and($bare->getStart())->toBeNull()
        ->and($bare->getLater())->toBeNull()
        ->and($bare->getView())->toBeNull();
});

it('has no welcome and no opinion about postponing until it is given one', function () {
    $tour = Tour::make('plain')->steps([TourStep::make('table-search')]);

    expect($tour->getWelcome())->toBeNull()
        ->and($tour->getPostponeLimit())->toBeNull();
});

it('refuses to count postponements below none at all', function () {
    // A negative allowance is the same statement as zero — do not offer the
    // choice — and letting it through would make `<= 0` the only guard standing
    // between a forged event and an unbounded count.
    expect(Tour::make('t')->postpone(-5)->getPostponeLimit())->toBe(0);
});

// ── What the browser is handed ──────────────────────────────────────────────

it('hands the browser no welcome for a tour that has none', function () {
    $payload = app(TourHost::class)->payload(Tour::make('t')->steps([TourStep::make('table-search')]));

    expect($payload['welcome'])->toBeNull();
});

it('resolves the framework labels for an author who wrote none', function () {
    // Resolved here rather than on the value object, which is built in a
    // provider's boot: a translation resolved then is in whatever locale the
    // console had, not the one the person reading the page is in.
    $payload = app(TourHost::class)->payload(greeter());

    expect($payload['welcome']['heading'])->toBe('Welcome')
        ->and($payload['welcome']['text'])->toBeNull()
        ->and($payload['welcome']['start'])->toBe(__('wire-core::messages.tour_start'))
        ->and($payload['welcome']['later'])->toBe(__('wire-core::messages.tour_later'))
        ->and($payload['welcome']['view'])->toBeNull();
});

it('keeps the labels an author did write', function () {
    $payload = app(TourHost::class)->payload(
        Tour::make('t')
            ->welcome(TourWelcome::make()->text('Two minutes.')->start('Go')->later('Not today')->view('tours.mine'))
            ->steps([TourStep::make('table-search')]),
    );

    expect($payload['welcome']['text'])->toBe('Two minutes.')
        ->and($payload['welcome']['start'])->toBe('Go')
        ->and($payload['welcome']['later'])->toBe('Not today')
        ->and($payload['welcome']['view'])->toBe('tours.mine');
});

it('drops the later label for a tour that allows no postponement', function () {
    // What the payload does not carry, the markup cannot render: the choice is
    // absent from the page rather than present and quietly ignored.
    $payload = app(TourHost::class)->payload(
        Tour::make('t')
            ->postpone(0)
            ->welcome(TourWelcome::make()->later('Not today'))
            ->steps([TourStep::make('table-search')]),
    );

    expect($payload['welcome']['later'])->toBeNull();
});

it('takes the postponement allowance from configuration when the tour says nothing', function () {
    config()->set('wire-core.tours.postpone', 0);

    expect(app(TourHost::class)->payload(greeter())['welcome']['later'])->toBeNull();

    config()->set('wire-core.tours.postpone', 'not a number');

    expect(TourState::configuredPostponeLimit())->toBe(0);
});

// ── The ledger ──────────────────────────────────────────────────────────────

it('holds a postponement only for the sitting it was made in', function () {
    $ledger = new TourLedger(welcomeDriver());
    $tour = greeter();

    sittingIn('a');
    $ledger->postpone($tour, walker());

    expect($ledger->isPostponed($tour, walker()))->toBeTrue();

    // The same person, the next time they sign in. On the session driver the
    // entry would have gone with the session; on `database` it is still there,
    // and without this comparison "later" would have meant "never".
    sittingIn('b');

    expect($ledger->isPostponed($tour, walker()))->toBeFalse()
        ->and($ledger->postponements($tour, walker()))->toBe(1);
});

it('counts postponements across sittings but not across versions', function () {
    $ledger = new TourLedger(welcomeDriver());

    sittingIn('a');
    $ledger->postpone(greeter(), walker());
    sittingIn('b');
    $ledger->postpone(greeter(), walker());

    expect($ledger->postponements(greeter(), walker()))->toBe(2)
        // A count kept against an older `since()` is not this tour's: the
        // author changed what it says, and the person has not put *this* off.
        ->and($ledger->postponements(greeter()->since('2'), walker()))->toBe(0);
});

it('clears a postponement when the tour is finished or forgotten', function () {
    $ledger = new TourLedger(welcomeDriver());

    sittingIn('a');
    $ledger->postpone(greeter(), walker());
    $ledger->acknowledge(greeter(), walker());

    expect($ledger->postponements(greeter(), walker()))->toBe(0);

    // And on the way back in: a replayed tour that kept its count would be one
    // "later" away from acknowledging itself again.
    $ledger->postpone(greeter(), walker());
    $ledger->forget(greeter(), walker());

    expect($ledger->postponements(greeter(), walker()))->toBe(0)
        ->and($ledger->isPostponed(greeter(), walker()))->toBeFalse();
});

it('ignores a postponement entry it did not write', function () {
    // The bag is shared and open on purpose, so this key can come back as
    // something else entirely. Treating that as "not postponed" shows a
    // greeting again, which is recoverable; trusting it would index a string.
    $ledger = new TourLedger(welcomeDriver([
        'tours' => ['postponed' => ['greeted' => 'yesterday', 'other' => ['since' => '1']]],
    ]));

    sittingIn('a');

    expect($ledger->isPostponed(greeter(), walker()))->toBeFalse()
        ->and($ledger->postponements(greeter(), walker()))->toBe(0);

    $flat = new TourLedger(welcomeDriver(['tours' => ['postponed' => 'corrupted']]));

    expect($flat->isPostponed(greeter(), walker()))->toBeFalse();
});

// ── The decision ────────────────────────────────────────────────────────────

it('stops offering a tour that has been put off, until the next sitting', function () {
    $state = welcomeState(welcomeDriver(), greeter());

    sittingIn('a');

    expect($state->match(null, null, null, walker())?->getId())->toBe('greeted');

    $state->postpone('greeted', walker());

    expect($state->match(null, null, null, walker()))->toBeNull();

    sittingIn('b');

    expect($state->match(null, null, null, walker())?->getId())->toBe('greeted');
});

it('gives up asking once the allowance is spent', function () {
    $driver = welcomeDriver();
    $ledger = new TourLedger($driver);
    $tour = greeter()->postpone(2);
    $state = welcomeState($driver, $tour);

    sittingIn('a');
    $state->postpone('greeted', walker());

    expect($ledger->hasSeen($tour, walker()))->toBeFalse();

    // The second "later" is the last one: recorded as seen, exactly as skipping
    // would — not as a fourth state that something later would have to handle.
    sittingIn('b');
    $state->postpone('greeted', walker());

    expect($ledger->hasSeen($tour, walker()))->toBeTrue()
        ->and($ledger->postponements($tour, walker()))->toBe(0)
        ->and($state->match(null, null, null, walker()))->toBeNull();
});

it('records nothing for a tour whose author offered no postponement', function () {
    // The event is the browser's to dispatch, so the allowance is checked on
    // the server: a forged one cannot put off a tour that never showed a button.
    $driver = welcomeDriver();
    $state = welcomeState($driver, greeter()->postpone(0));

    sittingIn('a');
    $state->postpone('greeted', walker());

    expect($driver->store)->toBe([])
        ->and($state->match(null, null, null, walker()))->not->toBeNull();
});

it('ignores a postponement for a tour that is not registered', function () {
    $driver = welcomeDriver();

    welcomeState($driver)->postpone('never-registered', walker());

    expect($driver->store)->toBe([]);
});

// ── Replaying somewhere else ────────────────────────────────────────────────

it('forgets a tour and hands back the way to the screen it runs on', function () {
    app()->instance(ResolvesPageUrls::class, new class implements ResolvesPageUrls
    {
        public function urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
        {
            return '/'.trim((string) $zone, '.').'/'.$key.'/'.$page;
        }
    });

    $driver = welcomeDriver();
    $ledger = new TourLedger($driver);
    $tour = Tour::make('orders-tour')
        ->zones('sales')
        ->resource('orders')
        ->steps([TourStep::make('table-search')]);

    $ledger->acknowledge($tour, walker());

    $redirect = welcomeState($driver, $tour)->replayNow('orders-tour', walker());

    expect($ledger->hasSeen($tour, walker()))->toBeFalse()
        // The tour rides in the query the same way a step on another page does,
        // so the page it lands on renders the chrome for a tour that would not
        // otherwise have claimed it.
        ->and($redirect?->getTargetUrl())->toContain('/sales/orders/index')
        ->and($redirect?->getTargetUrl())->toContain('wire-tour=orders-tour')
        ->and($redirect?->getTargetUrl())->toContain('wire-tour-step=0');
});

it('forgets the tour even when there is nowhere to send anybody', function () {
    // A tour scoped to nothing narrower than a zone has no single screen to be
    // sent to. The half that was asked for still happens; the caller reloads.
    $driver = welcomeDriver();
    $ledger = new TourLedger($driver);
    $tour = greeter();

    $ledger->acknowledge($tour, walker());

    expect(welcomeState($driver, $tour)->replayNow('greeted', walker()))->toBeNull()
        ->and($ledger->hasSeen($tour, walker()))->toBeFalse();
});

it('refuses to send anybody to a page they may not open', function () {
    app()->instance(ResolvesPageUrls::class, new class implements ResolvesPageUrls
    {
        public function urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
        {
            return '/'.$key;
        }
    });

    app()->instance(AuthorizesUrls::class, new class implements AuthorizesUrls
    {
        public function allowsUrl(string $url, ?Authenticatable $user): bool
        {
            return false;
        }
    });

    $tour = Tour::make('locked')->resource('orders')->steps([TourStep::make('table-search')]);

    expect(welcomeState(welcomeDriver(), $tour)->replayNow('locked', walker()))->toBeNull();
});

it('answers with nothing for a replay of a tour that is not registered', function () {
    expect(welcomeState(welcomeDriver())->replayNow('never-registered', walker()))->toBeNull();
});

// ── The markup ──────────────────────────────────────────────────────────────

it('carries every element hook an application may style the welcome by', function () {
    app(Tours::class)->register(greeter());

    $html = renderWelcomeHost();

    foreach (['tour-welcome', 'tour-welcome-heading', 'tour-welcome-text', 'tour-welcome-start', 'tour-welcome-later'] as $hook) {
        expect($html)->toContain('data-wire="'.$hook.'"');
    }

    expect($html)->toContain('greeting');
});

it('renders no welcome markup for a tour without one', function () {
    app(Tours::class)->register(Tour::make('plain')->steps([TourStep::make('table-search')]));

    expect(renderWelcomeHost())->not->toContain('data-wire="tour-welcome"');
});

it('renders an application view in place of its own', function () {
    View::addLocation(__DIR__.'/../../Fixtures/views');

    app(Tours::class)->register(
        Tour::make('own')
            ->welcome(TourWelcome::make()->heading('Ours')->view('tour-welcome'))
            ->steps([TourStep::make('table-search')]),
    );

    $html = renderWelcomeHost();

    expect($html)->toContain('data-testid="own-welcome"')
        // Both halves of the contract: the server-side objects it was handed,
        // and the Alpine names it calls, which are this scope's.
        ->and($html)->toContain('Ours')
        ->and($html)->toContain('own')
        ->and($html)->toContain('begin()')
        ->and($html)->toContain('later()')
        ->and($html)->not->toContain('data-wire="tour-welcome"');
});
