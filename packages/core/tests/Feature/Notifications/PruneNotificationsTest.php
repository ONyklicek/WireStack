<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use NyonCode\WireCore\Notifications\DatabaseNotification;

/*
 * Retention, so a table designed to stop mattering does not grow for the life of
 * the application.
 */
function storeNotification(string $message, int $daysAgo, bool $read = false): DatabaseNotification
{
    return DatabaseNotification::query()->create([
        'id' => (string) Str::ulid(),
        'type' => 'test',
        'notifiable_type' => 'App\\Models\\User',
        'notifiable_id' => '1',
        'data' => ['message' => $message],
        'read_at' => $read ? now()->subDays($daysAgo) : null,
        'created_at' => now()->subDays($daysAgo),
        'updated_at' => now()->subDays($daysAgo),
    ]);
}

beforeEach(function () {
    Schema::create('wire_notifications', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('type');
        $t->string('notifiable_type');
        $t->string('notifiable_id');
        $t->json('data');
        $t->timestamp('read_at')->nullable();
        $t->timestamps();
    });
});

afterEach(fn () => Schema::dropIfExists('wire_notifications'));

it('says so rather than guessing when no period is configured', function () {
    // Deleting rows because nobody said not to is not a default a framework gets
    // to have.
    storeNotification('old', 400);

    $this->artisan('wire-core:notifications-prune')
        ->expectsOutputToContain('No retention period configured')
        ->assertExitCode(2);

    expect(DatabaseNotification::query()->count())->toBe(1);
});

it('prunes past the configured period and keeps the rest', function () {
    config()->set('wire-core.notifications.database.retention_days', 90);

    storeNotification('ancient', 120);
    storeNotification('recent', 10);

    $this->artisan('wire-core:notifications-prune')
        ->expectsOutputToContain('Pruned 1 notification.')
        ->assertExitCode(0);

    expect(DatabaseNotification::query()->pluck('data')->pluck('message')->all())->toBe(['recent']);
});

it('can clear what has been read sooner than the rest', function () {
    // "Read" and "never looked at" are different claims, so they get different
    // windows: a read notification from last month is of interest to nobody, an
    // unread one from last month still is.
    config()->set('wire-core.notifications.database.retention_days', 365);
    config()->set('wire-core.notifications.database.read_retention_days', 30);

    storeNotification('read and old', 60, read: true);
    storeNotification('unread and old', 60);

    $this->artisan('wire-core:notifications-prune')->assertExitCode(0);

    expect(DatabaseNotification::query()->pluck('data')->pluck('message')->all())->toBe(['unread and old']);
});

it('counts a row once however many windows would have caught it', function () {
    config()->set('wire-core.notifications.database.retention_days', 30);
    config()->set('wire-core.notifications.database.read_retention_days', 10);

    storeNotification('read and ancient', 60, read: true);

    $this->artisan('wire-core:notifications-prune')
        ->expectsOutputToContain('Pruned 1 notification.')
        ->assertExitCode(0);
});

it('takes a period on the command line, for a one-off', function () {
    storeNotification('old', 60);
    storeNotification('newer', 5);

    $this->artisan('wire-core:notifications-prune', ['--days' => 30])
        ->expectsOutputToContain('Pruned 1 notification.')
        ->assertExitCode(0);

    expect(DatabaseNotification::query()->count())->toBe(1);
});

it('says nothing was there to prune in the plural', function () {
    config()->set('wire-core.notifications.database.retention_days', 30);

    $this->artisan('wire-core:notifications-prune')
        ->expectsOutputToContain('Pruned 0 notifications.')
        ->assertExitCode(0);
});
