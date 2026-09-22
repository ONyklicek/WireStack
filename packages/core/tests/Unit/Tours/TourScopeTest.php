<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use NyonCode\WireCore\Tours\Tour;
use NyonCode\WireCore\Tours\TourStep;

/**
 * Who a tour is for, and where it runs.
 *
 * Both axes delegate: `permission()` to `Foundation\Concerns\HasAuthorization`
 * (and through it to Laravel's `Gate`, which is what makes wildcards and the
 * super-admin gate of `laravel-permission-extended` work without this module
 * requiring that package), and the location constraints to values
 * `Foundation\Routing\Zone` produces.
 *
 * The rule under all of it: an absent constraint means "anywhere", so the
 * single-audience application writes none of this.
 */
function tour(string $id = 't'): Tour
{
    return Tour::make($id)->steps([TourStep::make('table-search')]);
}

it('runs anywhere when nothing constrains it', function () {
    expect(tour()->matchesLocation(null, null, null))->toBeTrue()
        ->and(tour()->matchesLocation('sales.', 'orders', 'index'))->toBeTrue();
});

it('matches a zone, and no other', function () {
    $t = tour()->zones('sales');

    expect($t->matchesLocation('sales.', null, null))->toBeTrue()
        ->and($t->matchesLocation('admin.', null, null))->toBeFalse()
        ->and($t->matchesLocation(null, null, null))->toBeFalse();
});

/**
 * `Zone::current()` answers with a trailing dot (`business.`), and an author
 * writing `->zones('business')` should not have to know that. Normalising on
 * the way in is what keeps the two spellings from being two different zones.
 */
it('normalises a zone name the way Zone does, so both spellings are one zone', function () {
    $written = tour()->zones('sales');
    $withDot = tour()->zones('sales.');

    expect($written->matchesLocation('sales.', null, null))->toBeTrue()
        ->and($withDot->matchesLocation('sales.', null, null))->toBeTrue();
});

/**
 * The overwhelmingly common installation has no zones at all, so "this tour is
 * for the default zone and no other" has to be sayable. Without null being a
 * matchable value it could only be written by omitting the constraint, which
 * means something else.
 */
it('lets the unzoned application be named explicitly', function () {
    $t = tour()->zones(null);

    expect($t->matchesLocation(null, null, null))->toBeTrue()
        ->and($t->matchesLocation('sales.', null, null))->toBeFalse();
});

it('matches several zones at once, the unzoned one included', function () {
    $t = tour()->zones('sales', null);

    expect($t->matchesLocation('sales.', null, null))->toBeTrue()
        ->and($t->matchesLocation(null, null, null))->toBeTrue()
        ->and($t->matchesLocation('admin.', null, null))->toBeFalse();
});

it('matches a resource and a page kind', function () {
    $t = tour()->resource('orders')->page('index');

    expect($t->matchesLocation(null, 'orders', 'index'))->toBeTrue()
        ->and($t->matchesLocation(null, 'orders', 'edit'))->toBeFalse()
        ->and($t->matchesLocation(null, 'invoices', 'index'))->toBeFalse();
});

it('composes its constraints with AND', function () {
    $t = tour()->zones('sales')->resource('orders')->page('index');

    expect($t->matchesLocation('sales.', 'orders', 'index'))->toBeTrue()
        ->and($t->matchesLocation('sales.', 'orders', 'edit'))->toBeFalse()
        ->and($t->matchesLocation('admin.', 'orders', 'index'))->toBeFalse();
});

it('accepts any of several resources or pages', function () {
    $t = tour()->resource('orders', 'invoices')->page('index', 'view');

    expect($t->matchesLocation(null, 'invoices', 'view'))->toBeTrue()
        ->and($t->matchesLocation(null, 'orders', 'index'))->toBeTrue()
        ->and($t->matchesLocation(null, 'orders', 'create'))->toBeFalse();
});

it('shows an unconstrained tour to a guest', function () {
    expect(tour()->appliesTo(null, null, null))->toBeTrue();
});

/**
 * `HasAuthorization` fails closed with no authenticated user, which is the
 * behaviour a tour wants: a permission-gated walkthrough is a hint about a
 * screen, and hinting at one to somebody who is not signed in leaks it.
 */
it('hides a permission-gated tour from a guest', function () {
    expect(tour()->permission('sales.view')->appliesTo(null, null, null))->toBeFalse();
});

it('shows a permission-gated tour only to somebody the Gate allows', function () {
    $allowed = new class extends Authenticatable
    {
        public $id = 1;
    };

    $denied = new class extends Authenticatable
    {
        public $id = 2;
    };

    Gate::define('sales.view', fn ($user): bool => $user->id === 1);

    $t = tour()->permission('sales.view');

    auth()->setUser($allowed);
    expect($t->appliesTo(null, null, null))->toBeTrue();

    auth()->setUser($denied);
    expect($t->appliesTo(null, null, null))->toBeFalse();
});

/**
 * The two axes are independent, and the failure this pins is the one that
 * matters: a tour whose location matches must still be refused when the
 * permission does not, or a sales walkthrough reaches an administrator's screen.
 */
it('refuses a tour whose location matches but whose permission does not', function () {
    $user = new class extends Authenticatable
    {
        public $id = 1;
    };

    Gate::define('sales.view', fn (): bool => false);
    auth()->setUser($user);

    $t = tour()->zones('sales')->permission('sales.view');

    expect($t->matchesLocation('sales.', null, null))->toBeTrue()
        ->and($t->appliesTo('sales.', null, null))->toBeFalse();
});

it('hides a tour whose own visible() says no', function () {
    expect(tour()->visible(false)->appliesTo(null, null, null))->toBeFalse()
        ->and(tour()->hidden(true)->appliesTo(null, null, null))->toBeFalse();
});

it('resolves a visible() closure rather than treating it as truthy', function () {
    expect(tour()->visible(fn (): bool => false)->appliesTo(null, null, null))->toBeFalse()
        ->and(tour()->visible(fn (): bool => true)->appliesTo(null, null, null))->toBeTrue();
});
