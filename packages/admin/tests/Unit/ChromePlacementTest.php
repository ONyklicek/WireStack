<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use NyonCode\WireAdmin\Enums\ChromeSlot;
use NyonCode\WireAdmin\Exceptions\ChromeSlotException;
use NyonCode\WireCore\Foundation\View\PageChrome;

/*
 * Where the account menu and the collapse button sit: the top bar, or the foot
 * of the menu column (`wire-admin.layout.user` / `rail_toggle`). Today's chrome
 * is the default; either can move, independently, and whatever moves takes all
 * of itself along — the application's entries and every package's.
 */
class CpUser extends User
{
    protected $guarded = [];
}

function cpRender(string $view = 'bare', ?string $user = null, ?string $toggle = null): string
{
    View::addLocation(__DIR__.'/../fixtures/views');
    config()->set('wire-admin.layout.user', $user ?? 'topbar');
    config()->set('wire-admin.layout.rail_toggle', $toggle ?? 'topbar');

    return view($view)->render();
}

/** Where a marker sits: in the top bar, in the menu's foot, or elsewhere. */
function cpWhere(string $html, string $marker): string
{
    $at = strpos($html, $marker);

    if ($at === false) {
        return 'missing';
    }

    $bar = strpos($html, 'data-wire="admin-topbar"');
    $barEnd = strpos($html, '</header>', $bar);
    $foot = strpos($html, 'data-testid="admin-sidebar-footer"');
    $aside = strpos($html, '</aside>');

    return match (true) {
        $at > $bar && $at < $barEnd => 'topbar',
        $foot !== false && $at > $foot && $at < $aside => 'footer',
        default => 'elsewhere',
    };
}

beforeEach(function () {
    Auth::setUser(new CpUser(['name' => 'Jane Doe', 'email' => 'jane@example.com']));
});

it('keeps both in the top bar by default, with no footer under the menu', function () {
    $html = cpRender();

    expect(cpWhere($html, 'data-testid="admin-user"'))->toBe('topbar')
        ->and(cpWhere($html, 'data-testid="admin-rail-toggle"'))->toBe('topbar')
        ->and($html)->not->toContain('data-testid="admin-sidebar-footer"');
});

it('moves the account to the foot of the menu, opening upwards', function () {
    $html = cpRender(user: 'sidebar');

    expect(cpWhere($html, 'data-testid="admin-user"'))->toBe('footer')
        ->and(cpWhere($html, 'data-testid="admin-rail-toggle"'))->toBe('topbar')
        ->and($html)->toContain("placement: 'top-start'");
});

it('moves the collapse button to the very foot, under the account', function () {
    $html = cpRender(user: 'sidebar', toggle: 'sidebar');

    expect(cpWhere($html, 'data-testid="admin-rail-toggle"'))->toBe('footer')
        ->and(strpos($html, 'data-testid="admin-user"'))->toBeLessThan(strpos($html, 'data-testid="admin-rail-toggle"'));
});

it('draws each exactly once, and binds the shortcut once, in every combination', function (string $user, string $toggle) {
    $html = cpRender(user: $user, toggle: $toggle);

    expect(substr_count($html, 'data-testid="admin-user"'))->toBe(1)
        ->and(substr_count($html, 'data-testid="admin-rail-toggle"'))->toBe(1)
        ->and(substr_count($html, "toLowerCase() === 'b'"))->toBe(1);
})->with([
    ['topbar', 'topbar'],
    ['sidebar', 'topbar'],
    ['topbar', 'sidebar'],
    ['sidebar', 'sidebar'],
]);

it('takes the application\'s entries and every package\'s along', function (string $user) {
    app(PageChrome::class)->add('chrome-probe', PageChrome::USER_MENU);
    $html = cpRender('with-user-menu', user: $user);
    $where = $user === 'sidebar' ? 'footer' : 'topbar';

    expect(cpWhere($html, 'data-testid="app-menu-entry"'))->toBe($where)
        ->and(cpWhere($html, 'data-testid="chrome-probe"'))->toBe($where)
        ->and(substr_count($html, 'data-testid="chrome-probe"'))->toBe(1);
})->with(['topbar', 'sidebar']);

it('moves an application\'s own trigger too', function () {
    expect(cpWhere(cpRender('with-user-trigger', user: 'sidebar'), 'data-testid="app-trigger"'))->toBe('footer');
});

it('keeps both in the top bar under the bar shape, which has no column', function () {
    config()->set('wire-admin.layout.navigation', 'top');
    $html = cpRender(user: 'sidebar', toggle: 'sidebar');

    expect(cpWhere($html, 'data-testid="admin-user"'))->toBe('topbar')
        ->and($html)->not->toContain('data-testid="admin-sidebar-footer"')
        ->and($html)->not->toContain('data-testid="admin-rail-toggle"')
        ->and($html)->not->toContain('data-rail-shortcut');
});

it('lets one layout differ from the configuration', function () {
    View::addLocation(__DIR__.'/../fixtures/views');
    $html = Blade::render('<x-wire-admin::layout user-placement="sidebar" rail-toggle-placement="sidebar">Records</x-wire-admin::layout>');

    expect(cpWhere($html, 'data-testid="admin-user"'))->toBe('footer')
        ->and(cpWhere($html, 'data-testid="admin-rail-toggle"'))->toBe('footer');
});

it('refuses a place that does not exist rather than drawing it somewhere', function () {
    expect(fn () => ChromeSlot::resolve('bottom', 'user'))->toThrow(ChromeSlotException::class, '`wire-admin.layout.user`')
        // Through a render it arrives wrapped by the view, with the same words.
        ->and(fn () => cpRender(user: 'bottom'))->toThrow(Exception::class, '[bottom] is not a place in the shell')
        ->and(fn () => cpRender(toggle: 'left'))->toThrow(Exception::class, '`wire-admin.layout.rail_toggle`');
});
