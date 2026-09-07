<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Notifications\DatabaseNotification;
use NyonCode\WireModuleNotifications\Pages\ListNotifications;
use NyonCode\WireModuleNotifications\Pages\ViewNotification;
use NyonCode\WireTable\Table;

/*
 * The history behind the bell.
 *
 * Two things are worth pinning: whose notifications a screen shows — the
 * viewer's, unless an application says otherwise — and that opening one marks it
 * read, which is what every inbox has always done.
 */

class NmUser extends User
{
    protected $table = 'users';

    protected $guarded = [];
}

function nmNotification(string $notifiableId, ?string $readAt = null): DatabaseNotification
{
    return DatabaseNotification::create([
        'id' => (string) Str::uuid(),
        'type' => 'invoice.paid',
        'notifiable_type' => NmUser::class,
        'notifiable_id' => $notifiableId,
        'data' => ['title' => 'Invoice paid '.$notifiableId],
        'read_at' => $readAt,
    ]);
}

/**
 * One notification with a payload of its own, for the list-page cases.
 *
 * @param  array<string, mixed>  $data
 */
function nmPayload(array $data, bool $read = false): DatabaseNotification
{
    return DatabaseNotification::create([
        'id' => (string) Str::ulid(),
        'type' => 'test',
        'notifiable_type' => NmUser::class,
        'notifiable_id' => '1',
        'data' => $data,
        'read_at' => $read ? now() : null,
    ]);
}

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });

    Schema::create('wire_notifications', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('type');
        $table->string('notifiable_type');
        $table->string('notifiable_id');
        $table->json('data');
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
    });

    NmUser::create(['id' => 1, 'name' => 'Jane']);
    NmUser::create(['id' => 2, 'name' => 'Sam']);
});

it('registers itself as the notifications module', function () {
    expect(app(PluginManager::class)->has('notifications'))->toBeTrue();
});

it('shows the viewer their own notifications and nobody else s', function () {
    nmNotification('1');
    nmNotification('2');

    Auth::setUser(NmUser::find(1));

    Livewire::test(ListNotifications::class)
        ->assertOk()
        ->assertSee('Invoice paid 1')
        ->assertDontSee('Invoice paid 2');
});

it('shows nothing at all to nobody, rather than everything', function () {
    // Signed out with the default scope: the honest answer is none, and the
    // tempting one — no filter, so all of them — is a leak.
    nmNotification('1');

    Livewire::test(ListNotifications::class)
        ->assertOk()
        ->assertDontSee('Invoice paid 1');
});

it('shows everyone s notifications where an application asked for that', function () {
    config()->set('wire-module-notifications.scope', 'all');

    nmNotification('1');
    nmNotification('2');

    Livewire::test(ListNotifications::class)
        ->assertSee('Invoice paid 1')
        ->assertSee('Invoice paid 2');
});

it('marks a notification read by opening it', function () {
    $notification = nmNotification('1');

    Auth::setUser(NmUser::find(1));

    expect($notification->read_at)->toBeNull();

    Livewire::test(ViewNotification::class, ['record' => $notification->id])->assertOk();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('leaves an already-read notification alone', function () {
    $readAt = now()->subDay();
    $notification = nmNotification('1', $readAt->toDateTimeString());

    Auth::setUser(NmUser::find(1));

    Livewire::test(ViewNotification::class, ['record' => $notification->id])->assertOk();

    expect($notification->fresh()->read_at->toDateTimeString())->toBe($readAt->toDateTimeString());
});

it('filters by read state', function () {
    Auth::setUser(NmUser::find(1));
    nmNotification('1');
    nmNotification('1', now()->toDateTimeString());

    Livewire::test(ListNotifications::class)
        ->assertSee('Invoice paid 1')
        ->call('setTab', 'unread')
        ->assertSet('tab', 'unread')
        ->call('setTab', 'read')
        ->assertSet('tab', 'read')
        // A tab that does not exist is user input arriving from a URL, and the
        // honest answer to it is the default tab.
        ->call('setTab', 'archived')
        ->assertSet('tab', 'all');
});

it('marks everything read from one place, without a checkbox in sight', function () {
    // What a selection bought — acting on the rows ticked on this page — is
    // strictly less than this, which acts on everything the viewer has not read.
    Auth::setUser(NmUser::find(1));
    nmPayload(['title' => 'One']);
    nmPayload(['title' => 'Two']);

    Livewire::test(ListNotifications::class)->call('markAllAsRead');

    expect(DatabaseNotification::query()->whereNull('read_at')->count())->toBe(0);
});

it('leaves nothing on the page that announces it as a table', function () {
    Auth::setUser(NmUser::find(1));
    nmPayload(['title' => 'Something']);

    $html = Livewire::test(ListNotifications::class)->html();

    // The screen is not built on a table any more, so none of its furniture can
    // arrive by accident: no grid, no header row, no checkbox, no page-size
    // control, no sort control.
    expect($html)->not->toContain('<table')
        ->and($html)->not->toContain('<thead')
        ->and($html)->not->toContain('data-testid="table-card-select"')
        ->and($html)->not->toContain('data-testid="table-per-page"')
        ->and($html)->not->toContain('data-testid="table-mobile-sort"');
});

it('shows what happened, not just what it was called', function () {
    Auth::setUser(NmUser::find(1));
    nmPayload(['title' => 'Invoice INV-2026-003', 'message' => 'Northwind Traders paid 4180.']);

    Livewire::test(ListNotifications::class)
        ->assertSee('Invoice INV-2026-003')
        // The line the notification was written to say, which the table version
        // had no column for at all.
        ->assertSee('Northwind Traders paid 4180.');
});

it('does not repeat the title as its own description', function () {
    Auth::setUser(NmUser::find(1));
    nmPayload(['title' => 'Export ready', 'message' => 'Export ready']);

    expect(substr_count(Livewire::test(ListNotifications::class)->html(), 'Export ready'))->toBe(1);
});

it('finds a notification by the words inside its payload', function () {
    // A search box that finds nothing is worse than no search box: the visible
    // text lives in a JSON payload, so searching a `title` column searches a
    // column that does not exist.
    Auth::setUser(NmUser::find(1));
    nmPayload(['title' => 'Invoice paid', 'message' => 'Northwind Traders']);
    nmPayload(['title' => 'Export ready', 'message' => 'Unrelated']);

    Livewire::test(ListNotifications::class)
        ->set('search', 'Northwind')
        ->assertSee('Invoice paid')
        ->assertDontSee('Export ready')
        // And says something different when the search is what emptied it.
        ->set('search', 'zzz-nothing')
        ->assertSee('Nothing matches that');
});

it('says unread three ways, because colour alone is not one of them', function () {
    Auth::setUser(NmUser::find(1));
    nmPayload(['title' => 'Unread one']);

    $html = Livewire::test(ListNotifications::class)->html();

    // Tone, an edge, and a word only a screen reader hears.
    expect($html)->toContain('bg-primary-50/70')
        ->and($html)->toContain('border-l-primary-500')
        ->and($html)->toContain('sr-only');
});

it('files the list under the day each notification landed on', function () {
    Auth::setUser(NmUser::find(1));
    nmPayload(['title' => 'From today']);

    $old = nmPayload(['title' => 'From last week']);
    $old->forceFill(['created_at' => now()->subWeek()])->save();

    expect(Livewire::test(ListNotifications::class)->html())
        ->toContain('Today')
        ->toContain('Earlier');
});

it('keeps mark-all quiet, and hides it when there is nothing to mark', function () {
    // The canon gives a screen one primary action — the one it exists for. A
    // solid button on "mark everything read" makes the loudest thing on an inbox
    // the one nobody came for.
    Auth::setUser(NmUser::find(1));
    nmPayload(['title' => 'Unread']);

    $component = Livewire::test(ListNotifications::class);

    $component->assertSeeHtml('data-testid="notification-mark-all"');
    expect($component->html())->not->toContain('bg-primary-600');

    $component->call('markAllAsRead')->assertDontSeeHtml('data-testid="notification-mark-all"');
});

it('tints the icon tile by what kind of notification it is', function () {
    Auth::setUser(NmUser::find(1));
    nmPayload(['type' => 'error', 'title' => 'Payment declined']);

    expect(Livewire::test(ListNotifications::class)->html())->toContain('bg-red-100');
});

it('puts the row verbs behind one quiet trigger', function () {
    Auth::setUser(NmUser::find(1));
    nmPayload(['title' => 'One']);
    nmPayload(['title' => 'Two']);

    $html = Livewire::test(ListNotifications::class)->html();

    // One trigger per row, and the destructive verb inside it rather than
    // standing in the row where a mis-click reaches it.
    expect(substr_count($html, 'data-testid="notification-menu"'))->toBe(2)
        ->and(substr_count($html, 'data-testid="notification-delete"'))->toBe(2);
});

it('marks one read, unread and gone, and only the viewer own', function () {
    Auth::setUser(NmUser::find(1));
    $mine = nmPayload(['title' => 'Mine']);
    $theirs = nmNotification('2');

    Livewire::test(ListNotifications::class)
        ->call('markAsRead', $mine->id)
        ->call('markAsUnread', $theirs->id)
        ->call('delete', $theirs->id);

    // Every verb goes through the scoped find(): an id arriving from a Livewire
    // call is user input.
    expect(DatabaseNotification::query()->find($mine->id)->isRead())->toBeTrue()
        ->and(DatabaseNotification::query()->find($theirs->id))->not->toBeNull();

    Livewire::test(ListNotifications::class)->call('delete', $mine->id);

    expect(DatabaseNotification::query()->find($mine->id))->toBeNull();
});

it('follows a notification to what it is about, marking it read on the way', function () {
    Auth::setUser(NmUser::find(1));
    $n = nmPayload(['title' => 'Invoice paid', 'url' => '/invoices/42']);

    Livewire::test(ListNotifications::class)
        ->call('open', $n->id)
        ->assertRedirect('/invoices/42');

    expect(DatabaseNotification::query()->find($n->id)->isRead())->toBeTrue();
});
