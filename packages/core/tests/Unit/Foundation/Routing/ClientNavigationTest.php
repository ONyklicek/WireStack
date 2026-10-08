<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Foundation\Routing\ClientNavigation;

/*
 * Every link the framework renders asks one object whether it navigates, so
 * these are the whole contract: what counts as a page of this application,
 * and which of the switch and the link's own preference wins.
 *
 * Testbench serves http://localhost.
 */

function clientNavigation(): ClientNavigation
{
    return app(ClientNavigation::class);
}

it('navigates by default', function () {
    expect(clientNavigation()->enabled())->toBeTrue()
        ->and(clientNavigation()->shouldNavigate('/admin/orders'))->toBeTrue();
});

it('stops navigating when the switch is off', function () {
    config()->set('wire-core.navigate', false);

    expect(clientNavigation()->enabled())->toBeFalse()
        ->and(clientNavigation()->shouldNavigate('/admin/orders'))->toBeFalse();
});

it('treats a path and a url on this origin as a page of this application', function (string $url) {
    expect(clientNavigation()->isInternal($url))->toBeTrue();
})->with([
    'path' => '/admin/orders',
    'relative path' => 'orders/1',
    'query only' => '?page=2',
    'absolute' => 'http://localhost/admin',
    'host in capitals' => 'http://LOCALHOST/admin',
    'scheme-relative' => '//localhost/admin',
]);

it('never navigates to anything that is not a page of this application', function (string $url) {
    expect(clientNavigation()->isInternal($url))->toBeFalse()
        ->and(clientNavigation()->shouldNavigate($url, true))->toBeFalse();
})->with([
    'another host' => 'https://example.com/admin',
    'another port' => 'http://localhost:8080/admin',
    'another scheme' => 'https://localhost/admin',
    'scheme-relative elsewhere' => '//example.com/admin',
    'mailto' => 'mailto:someone@example.com',
    'tel' => 'tel:+420123456789',
    'javascript' => 'javascript:void(0)',
    'fragment' => '#section',
    'empty' => '',
    'blank' => '   ',
    'unparseable' => 'http:///nowhere',
]);

it('has nothing to navigate to without a url', function () {
    expect(clientNavigation()->shouldNavigate(null))->toBeFalse()
        ->and(clientNavigation()->attribute(null))->toBe('');
});

it('lets a link overrule the switch in both directions', function () {
    expect(clientNavigation()->shouldNavigate('/report.csv', false))->toBeFalse();

    config()->set('wire-core.navigate', false);

    expect(clientNavigation()->shouldNavigate('/admin/orders', true))->toBeTrue();
});

it('writes the attribute only for a link that navigates', function () {
    expect(clientNavigation()->attribute('/admin'))->toBe('wire:navigate')
        ->and(clientNavigation()->attribute('/admin', false))->toBe('')
        ->and(clientNavigation()->attribute('https://example.com'))->toBe('');
});

it('renders the attribute through @wireNavigate', function () {
    $html = Blade::render('<a href="{{ $url }}" @wireNavigate($url)>x</a>', ['url' => '/admin']);
    $external = Blade::render('<a href="{{ $url }}" @wireNavigate($url)>x</a>', ['url' => 'https://example.com']);
    $declined = Blade::render('<a href="{{ $url }}" @wireNavigate($url, false)>x</a>', ['url' => '/admin']);

    expect($html)->toBe('<a href="/admin" wire:navigate>x</a>')
        ->and($external)->not->toContain('wire:navigate')
        ->and($declined)->not->toContain('wire:navigate');
});

it('carries a link\'s own preference to the decision', function () {
    $action = Action::make('open')->url('/admin/orders');

    expect($action->getNavigatePreference())->toBeNull()
        ->and($action->shouldNavigateTo('/admin/orders'))->toBeTrue()
        // A tab of its own is not this page's to navigate.
        ->and($action->shouldNavigateTo('/admin/orders', true))->toBeFalse()
        ->and($action->navigate(false)->shouldNavigateTo('/admin/orders'))->toBeFalse()
        ->and($action->getNavigatePreference())->toBeFalse()
        ->and($action->navigate(null)->getNavigatePreference())->toBeNull();
});
