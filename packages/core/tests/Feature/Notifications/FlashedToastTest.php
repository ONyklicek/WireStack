<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Blade;
use NyonCode\WireCore\Notifications\Drivers\SessionDriver;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\View\ToastContainer;

/*
 * The toast a browser event cannot deliver.
 *
 * A notification raised on the way out of a page — an action with
 * `successRedirect()`, a create page landing on the record it just filed — is
 * dispatched into a document that is replaced before it can show anything. The
 * flash is what crosses that boundary, and the container is what renders it on
 * arrival. What is worth pinning is that the whole notification crosses, that it
 * is shown once, and that a container asked where there is nothing says so.
 */

it('flashes the whole notification, not just its message', function () {
    // Not cosmetic: the toast that arrives after a redirect should look like the
    // one that would have appeared without it. A flash of type + message alone
    // silently dropped the title, the icon and the duration on exactly the saves
    // that redirect.
    (new SessionDriver)->send(
        Notification::success('The order was filed.')
            ->title('Saved')
            ->duration(9000)
            ->icon('outline:check-circle'),
    );

    expect(session('table-notification'))->toMatchArray([
        'type' => 'success',
        'message' => 'The order was filed.',
        'title' => 'Saved',
        'duration' => 9000,
        'icon' => 'outline:check-circle',
    ]);
});

it('hands the container what was flashed, under an id of this render', function () {
    session()->flash('table-notification', ['type' => 'success', 'message' => 'Saved']);

    $flashed = (new ToastContainer)->flashed();

    expect($flashed['payload'])->toMatchArray(['type' => 'success', 'message' => 'Saved'])
        ->and($flashed['id'])->toBeString()->not->toBe('');
});

it('gives every render its own id', function () {
    // The id is what tells a genuinely new notification from the same markup
    // shown again: a wire:navigate back button restores a cached page and
    // re-initialises Alpine over it, which without this re-raises the toast on
    // every press.
    session()->flash('table-notification', ['type' => 'info', 'message' => 'Hello']);

    expect((new ToastContainer)->flashed()['id'])
        ->not->toBe((new ToastContainer)->flashed()['id']);
});

it('has nothing to show when nothing was flashed', function () {
    expect((new ToastContainer)->flashed())->toBeNull();
});

it('ignores a flash that is not a notification', function () {
    // The key is an application's session, and something else may write to it.
    // A toast with no message is not a toast.
    session()->flash('table-notification', ['type' => 'success']);
    expect((new ToastContainer)->flashed())->toBeNull();

    session()->flash('table-notification', 'Saved');
    expect((new ToastContainer)->flashed())->toBeNull();
});

it('reads the key it was told to read', function () {
    // Two separate settings on the driver, so a renamed one has to be nameable
    // here too.
    session()->flash('app-toast', ['type' => 'success', 'message' => 'Renamed']);

    expect((new ToastContainer(sessionKey: 'app-toast'))->flashed()['payload']['message'])
        ->toBe('Renamed');
});

it('carries the payload into the rendered container', function () {
    session()->flash('table-notification', ['type' => 'success', 'message' => 'Order filed']);

    $html = Blade::render('<x-wire-notifications::toast-container />');

    expect($html)->toContain('Order filed')
        ->and($html)->toContain('showFlashed()');
});

it('shows nothing during a Livewire update', function () {
    // Measured, not reasoned: `flash()` writes into the *current* session as
    // well, so a container sitting inside a component re-renders mid-update,
    // reads the notification that update just sent, and puts up a second copy of
    // the toast the browser event is already delivering. Found by
    // `workbench/scripts/verify-toasts.mjs`, which counted six toasts where the
    // page had fired five.
    session()->flash('table-notification', ['type' => 'success', 'message' => 'Saved']);

    request()->headers->set('X-Livewire', 'true');

    expect((new ToastContainer)->flashed())->toBeNull();
});

it('asks for no session where there is none', function () {
    // A container can be rendered outside a request — a console render, a mail
    // preview — and `session()` there is a fatal rather than an empty answer.
    $application = Container::getInstance();

    try {
        Container::setInstance(new Container);

        expect((new ToastContainer)->flashed())->toBeNull();
    } finally {
        Container::setInstance($application);
    }
});

it('renders no payload at all when there is nothing to show', function () {
    $html = Blade::render('<x-wire-notifications::toast-container />');

    expect($html)->toContain('flashed: null');
});
