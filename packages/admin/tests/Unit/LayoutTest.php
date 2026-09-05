<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

/*
 * The page frame.
 *
 * Slots rather than configuration is the decision under test (ADR 0028 §1b):
 * everything an application wants in its chrome arrives as markup it wrote, and
 * none of it from a class holding brand, colours or auth.
 *
 * Rendered through a request rather than through `Blade::render()`, which leaks
 * one output buffer per named slot and turns every test that uses one risky.
 * A request is also what a layout actually meets.
 */

function alGet(string $view, array $data = []): string
{
    View::addLocation(__DIR__.'/../fixtures/views');
    Route::get('/al-probe', fn () => view($view, $data));

    return test()->get('/al-probe')->getContent();
}

it('frames a page, with the menu and the assets a shell owes it', function () {
    $html = alGet('bare');

    expect($html)->toContain('<!DOCTYPE html>')
        ->and($html)->toContain('data-testid="admin-sidebar"')
        ->and($html)->toContain('data-testid="admin-content"')
        ->and($html)->toContain('Records')
        // The interaction layer has to be in the initial document, or it arrives
        // late across a wire:navigate — the one thing ADR 0024 forbids.
        ->and($html)->toContain('wire-core-dropdown');
});

it('takes head, brand, topbar and user as slots', function () {
    $html = alGet('page', ['title' => 'Invoices']);

    expect($html)->toContain('<title>Invoices</title>')
        // The shell cannot guess a Vite entry name and does not try.
        ->and($html)->toContain('<link rel="stylesheet" href="/app.css">')
        ->and($html)->toContain('Acme')
        ->and($html)->toContain('data-testid="al-topbar"')
        ->and($html)->toContain('data-testid="al-user"');
});

it('falls back to the application name when no title is given', function () {
    config()->set('app.name', 'Wire Workbench');

    expect(alGet('bare'))->toContain('<title>Wire Workbench</title>');
});

it('carries the linked-only choice through to the menu', function () {
    // One flag, decided by the application and applied where the menu is built,
    // rather than two components disagreeing about which entries exist.
    expect(alGet('page', ['linkedOnly' => true]))->toContain('data-testid="admin-sidebar"');
});

it('mounts the palette and the toast container itself', function () {
    // Both belong to the chrome, not to a page: the palette derives its zone
    // from the route it was rendered on, so one trigger in the frame serves
    // every zone an application mounts.
    $html = alGet('bare');

    expect($html)->toContain('data-testid="global-search-trigger"')
        ->and($html)->toContain('data-testid="global-search-input"')
        ->and($html)->toContain('x-data');
});

it('offers a way past the menu for keyboard users', function () {
    expect(alGet('bare'))->toContain('href="#wire-admin-main"');
});
