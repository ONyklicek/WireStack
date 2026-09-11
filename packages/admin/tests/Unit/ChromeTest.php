<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroups;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Foundation\Enums\Theme;
use NyonCode\WireCore\Foundation\View\PageChrome;

/*
 * The chrome around the page: the bell, the theme switch, the rail, the folding
 * groups. None of it is a feature of its own — it is the part that makes a shell
 * read as finished rather than as a frame someone stopped writing.
 *
 * State that a user sets is remembered (localStorage) and read before the first
 * paint where it affects layout, because a preference the page forgets on every
 * navigation is worse than no preference at all.
 */

function chGet(): string
{
    // Rendered directly rather than through a request: the fixture uses no named
    // slots, so `Blade::render`'s buffer quirk does not apply, and the auth state
    // a test sets survives without a session provider behind it.
    View::addLocation(__DIR__.'/../fixtures/views');

    return view('bare')->render();
}

it('mounts the notification bell where notifications are stored', function () {
    // The component has shipped in wire-core for versions; what was missing was
    // a shell to put it in.
    config()->set('wire-core.notifications.default', ['session', 'database']);

    expect(chGet())->toContain('data-testid="notification-bell"');
});

it('leaves the bell out when nothing is stored to put in it', function () {
    // Measured, not cautious: the bell counts rows as soon as a user is
    // authenticated, so mounting it under the default `session` driver is a SQL
    // error on every page of an application that never asked for stored
    // notifications.
    config()->set('wire-core.notifications.default', 'session');

    expect(chGet())->not->toContain('data-testid="notification-bell"');
});

it('lets the application decide for itself', function () {
    config()->set('wire-core.notifications.default', 'session');

    View::addLocation(__DIR__.'/../fixtures/views');
    Route::get('/ch-bell', fn () => view('with-bell'));

    expect(test()->get('/ch-bell')->getContent())->toContain('data-testid="notification-bell"');
});

it('offers three themes, and decides which one before the page paints', function () {
    $html = chGet();

    // Three, not a toggle: `system` has to be somewhere to go back to, or a
    // laptop that dims itself in the evening stops being followed the first time
    // anybody touches the switch.
    expect($html)->toContain('data-testid="admin-theme-light"')
        ->toContain('data-testid="admin-theme-system"')
        ->toContain('data-testid="admin-theme-dark"')
        ->toContain('role="radiogroup"')
        // The inline head script is the difference between a dark page and a
        // white flash followed by a dark page.
        ->and($html)->toContain("const key = 'wire-admin.theme';")
        ->and($html)->toContain('prefers-color-scheme: dark');
});

it('takes the two preferences out of the top bar on a phone and puts them in the drawer', function () {
    $html = chGet();

    // Seven controls in a 390px strip is some seventy pixels more than there is:
    // the bar overflowed, the page scrolled sideways, and the user menu — last in
    // the row — sat off the edge of the screen.
    expect($html)->toContain('data-testid="admin-theme"')
        // The bar's copy starts hidden and appears at `sm`.
        ->toContain('dark:border-gray-700 hidden sm:inline-flex')
        // The drawer is the one piece of chrome a phone always has, and unlike
        // the user menu it is there whether or not the application passed its own.
        ->toContain('data-testid="admin-theme-nav"')
        ->toContain('data-testid="admin-density-nav"')
        ->toContain('sm:hidden');

    // Both copies are in the document at every width and CSS hides one of them,
    // which querySelector knows nothing about — so no two elements may answer to
    // the same testid, or a driver clicks the invisible one and nothing happens.
    expect(substr_count($html, 'data-testid="admin-theme-dark"'))->toBe(1)
        ->and(substr_count($html, 'data-testid="admin-density-compact"'))->toBe(1);
});

it('puts the theme back after a wire:navigate swap', function () {
    // Livewire's navigate copies the fetched document's <html> attributes over
    // the live ones, and the server cannot know what this browser chose — so
    // without this the admin went white the moment you opened a second page.
    // `onSwap` runs in the same task as the swap, so nothing is painted in
    // between; the `navigated` listener covers the cached back/forward path.
    $html = chGet();

    expect($html)->toContain("document.addEventListener('livewire:navigating'")
        ->and($html)->toContain('event.detail?.onSwap')
        ->and($html)->toContain("document.addEventListener('livewire:navigated'");
});

it('keeps the switch and the page reading the same choice', function () {
    // Two copies of "what does dark mean" is how the login screen ended up
    // treating `system` as light while the admin followed the OS. The store is
    // the switch; partials/theme.blade.php is the rule.
    expect(chGet())->toContain('window.wireAdminTheme.set(theme)')
        ->and(chGet())->toContain('window.wireAdminTheme.get()');
});

it('keeps following the system while the page is open', function () {
    // Without the listener, "system" means "whatever the system said at load",
    // and an admin sitting at their desk at sunset is left in the wrong theme
    // until they reload.
    expect(chGet())->toContain("system.addEventListener('change'");
});

it('names every theme in a language, not only in an icon', function () {
    // Icon-only controls need an accessible name; the label comes from the enum
    // so a second surface says the same three words.
    $html = chGet();

    foreach (Theme::cases() as $theme) {
        expect($html)->toContain($theme->label());
    }
});

it('offers a rail toggle and remembers it', function () {
    $html = chGet();

    expect($html)->toContain('data-testid="admin-rail-toggle"')
        ->and($html)->toContain('localStorage.getItem(key)')
        ->and($html)->toContain("'wire-admin.rail'");
});

it('decides the menu width before the page is painted, not from the store', function () {
    // The defect: reading the choice in the Alpine store meant the first frame of
    // every page was the *other* answer, and `transition-[width]` turned that one
    // wrong frame into a third of a second of the column sliding shut — measured
    // at 288px → 284 → 269 → 235 → … → 64, on every load and every wire:navigate.
    $html = chGet();

    expect($html)->toContain('data-wire-admin-rail')
        // Stamped on <html> by a blocking script, and read by CSS that needs no
        // Alpine — which is what makes it true for the first paint.
        ->and($html)->toContain("setAttribute('data-rail'")
        ->and($html)->toContain('[data-rail="true"] .wire-admin-sidebar')
        // Re-asserted per SPA visit: wire:navigate copies the fetched document's
        // <html> attributes over the live ones, and the server does not know what
        // this browser chose.
        ->and($html)->toContain('livewire:navigating')
        // And the store no longer holds a second copy of the rule.
        ->and($html)->toContain('window.wireAdminRail.get()')
        ->and($html)->toContain('window.wireAdminRail.set(');
});

it('gives the rail toggle a keyboard shortcut that yields to a text field', function () {
    // The binding every editor and admin has settled on for this one control —
    // declined while the caret is in a field, because in a rich-text editor the
    // same chord is bold.
    $html = chGet();

    expect($html)->toContain('aria-keyshortcuts')
        ->and($html)->toContain("\$event.key?.toLowerCase() === 'b'")
        ->and($html)->toContain('[contenteditable]');
});

it('shows who is signed in when the application wrote no user slot', function () {
    // An admin whose top bar cannot say who is signed in reads as unfinished.
    // The slot still wins; this is only what happens when there is none.
    $user = new ChUser;
    $user->name = 'Jane Doe';

    Auth::setUser($user);

    expect(chGet())->toContain('Jane Doe');
});

it('lets a package put its own entry in the user menu', function () {
    // The region that was missing. Before it, every application hand-wrote a
    // profile link and a sign-out form into the `userMenu` slot, against
    // packages it happened to have installed — two entries in one place that no
    // application actually owns. Now the package that owns the page contributes
    // the link to it.
    Auth::setUser(new ChUser);

    View::addLocation(__DIR__.'/../fixtures/views');
    app(PageChrome::class)->add('chrome-probe', PageChrome::USER_MENU);

    expect(chGet())->toContain('data-testid="chrome-probe"');
});

it('keeps the user-menu region out of the end of the document', function () {
    // Regions are not a suggestion: a sign-out form rendered into the body
    // instead of the menu is a form nobody can see, and it would look registered
    // from every diagnostic.
    Auth::setUser(new ChUser);

    View::addLocation(__DIR__.'/../fixtures/views');
    app(PageChrome::class)->add('chrome-probe', PageChrome::USER_MENU);

    // One occurrence, in the menu — not a second copy at the end of the body.
    expect(substr_count(chGet(), 'data-testid="chrome-probe"'))->toBe(1);
});

it('keeps its own menu-item name working over the row core now owns', function () {
    // The markup moved down to `<x-wire::menu-item>` when a second package
    // outside the shell needed a row. An application's layout slot still names
    // this one, and delegation is what keeps that true — a rename here would
    // break a file the shell told applications to write.
    $html = Blade::render('<x-wire-admin::menu-item href="/profile" icon="outline:user-circle" data-testid="row">Profile</x-wire-admin::menu-item>');

    expect($html)->toContain('href="/profile"')
        ->toContain('data-testid="row"')
        ->toContain('<svg')
        ->toContain('px-4 py-2');
});

it('draws a collapsible group as a button, and a plain one as a heading', function () {
    // A group with nothing in it is absent by the rule empty headings already
    // follow, so the entries come first.
    app(ResourceRegistry::class)->register(ChInvoiceResource::class);
    app(NavigationGroups::class)->register(NavigationGroup::make('billing')->label('Billing')->collapsed());

    $html = Blade::render('<x-wire-admin::sidebar />');

    expect($html)->toContain('data-collapsible="true"')
        // Folded to start, and openable — the pair CanBeCollapsed keeps together.
        ->and($html)->toContain('open: false')
        ->and($html)->toContain('wire-admin.nav.billing');
});

class ChUser extends User
{
    protected $table = 'users';

    protected $guarded = [];
}

class ChInvoiceResource implements DescribesResource, ProvidesNavigation
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make()->group('billing');
    }
}
