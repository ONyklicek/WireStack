<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;
use NyonCode\WireCore\Foundation\Routing\Contracts\AuthorizesUrls;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;
use NyonCode\WireCore\Tours\Tour;
use NyonCode\WireCore\Tours\TourHost;
use NyonCode\WireCore\Tours\TourLedger;
use NyonCode\WireCore\Tours\Tours;
use NyonCode\WireCore\Tours\TourStep;

/*
 * A tour that runs out of screen and carries on on the next page.
 *
 * `TourStep::on()` puts a step on another page of the same zone. The browser
 * reaches it by navigating there with the tour's id and the step in the query,
 * and the server — which renders the host only for a tour that claims the page —
 * has to recognise that a page two steps into a walkthrough is claimed by it.
 * These drive that server half through real requests to routes named the way
 * the panel names them (`{zone}.wire.{key}.{page}`), because the route name is
 * the only thing the host reads its location from.
 *
 * The browser half — navigating, resuming, coming back — is
 * `workbench/scripts/verify-demo-tour.mjs`.
 */

beforeEach(function () {
    // Where each page is, as the one owner of page URLs answers it. A fake with
    // the zone in the path, so an assertion can see which zone was asked for.
    app()->instance(ResolvesPageUrls::class, new class implements ResolvesPageUrls
    {
        public function urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
        {
            return $key === 'unrouted' ? null : '/'.trim((string) $zone, '.').'/'.$key.'/'.$page;
        }
    });

    // The two pages, answering with what the host would hand the browser.
    $answer = function () {
        $host = app(TourHost::class);
        $tour = $host->current();

        return response()->json(['tour' => $tour?->getId(), 'payload' => $tour ? $host->payload($tour) : null]);
    };

    Route::get('/sales/overview', $answer)->name('sales.wire.overview.index');
    Route::get('/sales/orders', $answer)->name('sales.wire.orders.index');
    Route::get('/stock/orders', $answer)->name('stock.wire.orders.index');

    app(Tours::class)->register(
        Tour::make('sales-tour')
            ->zones('sales')
            ->resource('overview')
            ->page('index')
            ->steps([
                TourStep::make('widget-grid')->heading('Numbers'),
                TourStep::make('widget-layout-edit')->heading('Customise'),
                TourStep::make('table-search')->on('orders')->heading('Orders'),
                TourStep::make('table-filters-trigger')->on('unrouted')->heading('Nowhere'),
            ]),
    );
});

it('knows which steps are on the page it starts on, and where the others are', function () {
    $payload = $this->get('/sales/overview')->json('payload');

    expect(array_column($payload['steps'], 'here'))->toBe([true, true, false, false])
        // The page URL comes from the owner of page URLs, in the zone the tour
        // is running in.
        ->and($payload['steps'][2]['url'])->toBe('/sales/orders/index')
        // A step whose page is not routed gets no address — the browser skips
        // it the way it skips an element that is not on screen.
        ->and($payload['steps'][3]['url'])->toBeNull()
        ->and($payload['resume'])->toBeNull()
        ->and($payload['from'])->toBeNull();
});

it('gives no address for a page this person may not open', function () {
    // The page's rules are on its route; the tour asks rather than restating
    // them, and a step into a 403 is skipped like a step that is not there.
    app()->instance(AuthorizesUrls::class, new class implements AuthorizesUrls
    {
        public function allowsUrl(string $url, ?Authenticatable $user): bool
        {
            return $url !== '/sales/orders/index';
        }
    });

    expect($this->get('/sales/overview')->json('payload.steps.2.url'))->toBeNull();
});

it('opens a tour left halfway at the step it was left on', function () {
    app(TourLedger::class)->reach(app(Tours::class)->get('sales-tour'), null, 2);

    expect($this->get('/sales/overview')->json('payload'))
        ->toMatchArray(['resume' => null, 'from' => 2])
        // Carrying on across pages is the query's answer, not the ledger's: the
        // page was sent to a step, and that is the step it shows.
        ->and($this->get('/sales/orders?wire-tour=sales-tour&wire-tour-step=2')->json('payload'))
        ->toMatchArray(['resume' => 2, 'from' => null]);
});

it('carries on at the step it was sent to, on the page that step is on', function () {
    // The orders page is claimed by nothing on its own — without the query it
    // renders no tour at all.
    expect($this->get('/sales/orders')->json('tour'))->toBeNull();

    $response = $this->get('/sales/orders?wire-tour=sales-tour&wire-tour-step=2')->json();

    expect($response['tour'])->toBe('sales-tour')
        ->and($response['payload']['resume'])->toBe(2)
        ->and(array_column($response['payload']['steps'], 'here'))->toBe([false, false, true, false])
        // And the way back: the steps before it lead to the page the tour
        // started on.
        ->and($response['payload']['steps'][1]['url'])->toBe('/sales/overview/index');
});

it('refuses a step that is not on the page it was sent to', function () {
    // Step 0 lives on the overview. A link that names it on the orders page is
    // one somebody edited, and the page renders as though nothing were asked.
    expect($this->get('/sales/orders?wire-tour=sales-tour&wire-tour-step=0')->json('tour'))->toBeNull()
        ->and($this->get('/sales/orders?wire-tour=sales-tour&wire-tour-step=99')->json('tour'))->toBeNull()
        ->and($this->get('/sales/orders?wire-tour=sales-tour&wire-tour-step=x')->json('tour'))->toBeNull()
        ->and($this->get('/sales/orders?wire-tour=nope&wire-tour-step=2')->json('tour'))->toBeNull();
});

it('does not follow a tour out of its zone', function () {
    // The same page key in another zone is another page. A tour runs where it
    // was declared to, and carrying on across zones would walk somebody into a
    // part of the application its author never pointed at.
    expect($this->get('/stock/orders?wire-tour=sales-tour&wire-tour-step=2')->json('tour'))->toBeNull();
});

it('does not resume a tour somebody already finished', function () {
    app(TourLedger::class)->acknowledge(app(Tours::class)->get('sales-tour'), null);

    expect($this->get('/sales/orders?wire-tour=sales-tour&wire-tour-step=2')->json('tour'))->toBeNull();
});

it('names the page a step is on by the key and page the router uses', function () {
    $step = TourStep::make('table-search')->on('orders', 'edit');

    expect($step->isElsewhere())->toBeTrue()
        ->and($step->getResource())->toBe('orders')
        ->and($step->getPage())->toBe('edit')
        ->and(TourStep::make('table-search')->on('orders')->getPage())->toBe('index')
        // A step without `on()` is on the tour's own page, which is every step
        // there was before a tour could span pages.
        ->and(TourStep::make('table-search')->isElsewhere())->toBeFalse();
});

it('offers no way back to a tour that names no page of its own', function () {
    // Constrained by nothing narrower than a zone, a tour has no single page to
    // return to — so the steps it started with get no address from elsewhere.
    expect(Tour::make('zone-wide')->zones('sales')->steps([TourStep::make('widget-grid')])->home())->toBeNull()
        ->and(Tour::make('paged')->resource('overview')->steps([TourStep::make('widget-grid')])->home())
        ->toBe(['overview', 'index']);
});
