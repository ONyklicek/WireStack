<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Livewire\Livewire;
use NyonCode\WireCore\Notifications\DatabaseNotification;
use NyonCode\WireModuleNotifications\Pages\ListNotifications;

/*
 * Who can open which notification, over the routes an application registers.
 *
 * `NotificationsModuleTest` mounts the pages as components. This walks them the
 * way a browser does — a URL with an id in it, behind `web` and `auth`, with
 * the router binding the page — because the defect this guards against was
 * exactly that a URL with an id in it was all it took. The detail page resolved
 * its record unscoped while the list was scoped, so an id copied out of a
 * shared screen, a log or a guess rendered someone else's message and marked it
 * read in their inbox.
 */

class NjUser extends User
{
    protected $table = 'users';

    protected $guarded = [];
}

/** Something that is not a user and shares a user's numeric key. */
class NjTeam extends User
{
    protected $table = 'users';

    protected $guarded = [];
}

function njNotification(string $notifiableId, string $title, string $type = NjUser::class): DatabaseNotification
{
    return DatabaseNotification::create([
        'id' => (string) Str::ulid(),
        'type' => 'invoice.paid',
        'notifiable_type' => $type,
        'notifiable_id' => $notifiableId,
        'data' => ['title' => $title],
    ]);
}

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });

    Schema::create('wire_notifications', function (Blueprint $table) {
        $table->ulid('id')->primary();
        $table->string('type');
        $table->string('notifiable_type');
        $table->string('notifiable_id');
        $table->json('data');
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
    });

    $this->jane = NjUser::create(['id' => 1, 'name' => 'Jane']);
    $this->sam = NjUser::create(['id' => 2, 'name' => 'Sam']);

    View::addLocation(__DIR__.'/../../fixtures/views');
    config()->set('livewire.component_layout', 'plain-layout');

    // What an application that routes the panel has: the pages behind the
    // panel's own guard, and a sign-in screen for that guard to send a guest to.
    Route::get('/login', fn (): string => 'sign in')->name('login');
    Route::middleware(['web', 'auth'])->group(fn () => Route::wireResources(only: ['notifications']));
});

// ─── One's own ─────────────────────────────────────────────────────

it('opens a notification from its URL for the person it was sent to, and marks it read', function () {
    $mine = njNotification('1', 'Invoice paid by Northwind');

    $this->actingAs($this->jane)
        ->get(route('wire.notifications.view', $mine->id))
        ->assertOk()
        ->assertSee('Invoice paid by Northwind');

    expect($mine->fresh()->read_at)->not->toBeNull();
});

it('sends a guest to sign in rather than showing or refusing anything', function () {
    $mine = njNotification('1', 'Invoice paid by Northwind');

    $this->get(route('wire.notifications.view', $mine->id))->assertRedirect(route('login'));

    expect($mine->fresh()->read_at)->toBeNull();
});

// ─── Somebody else's ───────────────────────────────────────────────

it('answers another person s notification with not-found, and leaves it unread for them', function () {
    $theirs = njNotification('2', 'Sam s salary review');

    // 404 and not 403: a refusal would confirm the id exists, which is the
    // first thing somebody enumerating ids wants to know.
    $this->actingAs($this->jane)
        ->get(route('wire.notifications.view', $theirs->id))
        ->assertNotFound()
        ->assertDontSee('salary review');

    expect($theirs->fresh()->read_at)->toBeNull();

    // And from Sam's side, nothing happened at all: it is still in his unread tab.
    $this->actingAs($this->sam);

    Livewire::test(ListNotifications::class)
        ->set('tab', 'unread')
        ->assertSee('Sam s salary review');
});

it('answers an id that exists nowhere exactly as it answers someone else s', function () {
    // The two refusals have to be indistinguishable, or the difference between
    // them is the oracle the 404 was chosen to avoid.
    $theirs = njNotification('2', 'Sam s salary review');

    $foreign = $this->actingAs($this->jane)->get(route('wire.notifications.view', $theirs->id));
    $missing = $this->actingAs($this->jane)->get(route('wire.notifications.view', (string) Str::ulid()));

    expect($foreign->status())->toBe(404)
        ->and($missing->status())->toBe($foreign->status());
});

it('does not mistake something else with the same key for the viewer', function () {
    // Notifications are morph-addressed. A team with id 1 is not user 1, and a
    // scope that compared only the id would hand Jane the team's inbox.
    $teams = njNotification('1', 'Team budget approved', NjTeam::class);

    $this->actingAs($this->jane)
        ->get(route('wire.notifications.view', $teams->id))
        ->assertNotFound();

    expect($teams->fresh()->read_at)->toBeNull();
});

it('refuses every list action on someone else s id, not only the page', function () {
    // The list's buttons are Livewire calls that take an id as an argument —
    // user input like the URL is. Each one has to be scoped the same way.
    $theirs = njNotification('2', 'Sam s salary review');

    $this->actingAs($this->jane);

    Livewire::test(ListNotifications::class)
        ->call('markAsRead', $theirs->id)
        ->call('open', $theirs->id)
        ->assertNoRedirect();

    expect($theirs->fresh()->read_at)->toBeNull();

    $theirs->forceFill(['read_at' => now()])->save();

    Livewire::test(ListNotifications::class)->call('markAsUnread', $theirs->id);
    expect($theirs->fresh()->read_at)->not->toBeNull();

    Livewire::test(ListNotifications::class)->call('delete', $theirs->id);
    expect(DatabaseNotification::query()->whereKey($theirs->id)->exists())->toBeTrue();

    // Mark-all is the one action without an id, and it must not reach past
    // the viewer either.
    $alsoTheirs = njNotification('2', 'Sam s second message');

    Livewire::test(ListNotifications::class)->call('markAllAsRead');
    expect($alsoTheirs->fresh()->read_at)->toBeNull();
});

// ─── The administrative view ───────────────────────────────────────

it('opens anybody s notification from its URL once the application asked for the administrative view', function () {
    config()->set('wire-module-notifications.scope', 'all');

    $theirs = njNotification('2', 'Sam s salary review');

    $this->actingAs($this->jane)
        ->get(route('wire.notifications.view', $theirs->id))
        ->assertOk()
        ->assertSee('Sam s salary review');
});
