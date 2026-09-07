<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/*
 * One row of a user menu.
 *
 * It lives in core rather than in the shell because two packages outside the
 * shell contribute rows — the users module's profile link and the auth module's
 * sign-out — and neither depends on `wire-admin`. The shell's own
 * `<x-wire-admin::menu-item>` delegates here.
 *
 * The two shapes are the two things a user menu holds: somewhere to go, and a
 * form to submit.
 */

it('is a link when it has somewhere to go', function () {
    $html = Blade::render('<x-wire::menu-item href="/profile">Profile</x-wire::menu-item>');

    expect($html)->toContain('<a')
        ->toContain('href="/profile"')
        ->toContain('Profile')
        // No type attribute on an anchor: it means something else there.
        ->not->toContain('type=');
});

it('is a button when it has none, and submits the form around it', function () {
    // A sign-out is a POST, so the row has to be able to be the submit button of
    // the form it sits in rather than a link styled like one.
    $html = Blade::render('<x-wire::menu-item type="submit">Sign out</x-wire::menu-item>');

    expect($html)->toContain('<button')
        ->toContain('type="submit"')
        ->not->toContain('href');
});

it('draws the icon a row was given, and nothing where there is none', function () {
    expect(Blade::render('<x-wire::menu-item icon="outline:user-circle">Profile</x-wire::menu-item>'))
        ->toContain('<svg')
        ->and(Blade::render('<x-wire::menu-item>Profile</x-wire::menu-item>'))
        ->not->toContain('<svg');
});

it('adds to its own classes rather than replacing them', function () {
    // A caller passing `class` is adding an alignment or a colour, not throwing
    // away the padding that makes it a row of this menu.
    $html = Blade::render('<x-wire::menu-item class="text-red-600">Delete</x-wire::menu-item>');

    expect($html)->toContain('text-red-600')
        ->toContain('px-4 py-2');
});

it('carries through the attributes a caller needs on the element itself', function () {
    // `wire:navigate` on the profile link and a test id on both: they belong on
    // the anchor, not on a wrapper.
    $html = Blade::render('<x-wire::menu-item href="/p" wire:navigate data-testid="row">Profile</x-wire::menu-item>');

    expect($html)->toContain('wire:navigate')
        ->toContain('data-testid="row"');
});
