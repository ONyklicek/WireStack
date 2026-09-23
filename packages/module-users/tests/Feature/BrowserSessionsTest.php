<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireModuleUsers\Livewire\BrowserSessionManagement;
use NyonCode\WireModuleUsers\Services\BrowserSessionStore;
use NyonCode\WireModuleUsers\Tests\Fixtures\User;
use NyonCode\WireModuleUsers\ValueObjects\UserAgent;

/*
 * The browser-sessions card: where this account is signed in, and a way to end
 * every session but the one asking.
 */

const MAC_CHROME = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';
const IPHONE_SAFARI = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

beforeEach(function () {
    config()->set('wire-module-users.roles', false);
    config()->set('session.driver', 'database');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    Schema::create('sessions', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->foreignId('user_id')->nullable()->index();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->longText('payload');
        $table->integer('last_activity')->index();
    });
});

function sessionRow(string $id, ?int $userId, string $agent, int $ago = 0): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => '127.0.0.1',
        'user_agent' => $agent,
        'payload' => '',
        'last_activity' => now()->subMinutes($ago)->getTimestamp(),
    ]);
}

it('lists this person\'s sessions, newest first, and marks the current one', function () {
    $me = User::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('secret')]);
    $them = User::query()->create(['name' => 'Bruno', 'email' => 'b@example.com', 'password' => Hash::make('secret')]);

    sessionRow('phone', $me->id, IPHONE_SAFARI, ago: 30);
    sessionRow('laptop', $me->id, MAC_CHROME);
    sessionRow('theirs', $them->id, MAC_CHROME);

    $sessions = app(BrowserSessionStore::class)->forUser($me, 'laptop');

    expect($sessions->pluck('id')->all())->toBe(['laptop', 'phone'])
        ->and($sessions[0]->current)->toBeTrue()
        ->and($sessions[1]->current)->toBeFalse()
        ->and($sessions[1]->agent->platform)->toBe('iOS')
        ->and($sessions[1]->agent->browser)->toBe('Safari')
        ->and($sessions[1]->agent->desktop)->toBeFalse();
});

it('draws the list on the card', function () {
    $me = User::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('secret')]);
    $this->be($me);

    sessionRow('phone', $me->id, IPHONE_SAFARI, ago: 30);

    Livewire::test(BrowserSessionManagement::class)
        ->assertSeeHtml('data-testid="browser-sessions-list"')
        ->assertSee('iOS')
        ->assertDontSeeHtml('data-testid="browser-sessions-unlisted"');
});

it('says the list needs the database driver instead of drawing an empty one', function () {
    config()->set('session.driver', 'array');

    $me = User::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('secret')]);
    $this->be($me);

    expect(app(BrowserSessionStore::class)->forUser($me, 'x'))->toBeEmpty()
        ->and(app(BrowserSessionStore::class)->forgetOthers($me, 'x'))->toBe(0);

    Livewire::test(BrowserSessionManagement::class)
        ->assertSeeHtml('data-testid="browser-sessions-unlisted"')
        ->assertSeeHtml('data-testid="browser-sessions-open"');
});

it('ends every other session of this account, and keeps this one signed in', function () {
    $me = User::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('secret')]);
    $them = User::query()->create(['name' => 'Bruno', 'email' => 'b@example.com', 'password' => Hash::make('secret')]);
    $this->be($me);

    sessionRow(session()->getId(), $me->id, MAC_CHROME);
    sessionRow('phone', $me->id, IPHONE_SAFARI);
    sessionRow('theirs', $them->id, MAC_CHROME);

    $hash = $me->password;

    Livewire::test(BrowserSessionManagement::class)
        ->call('confirm')
        ->assertSet('confirming', true)
        ->set('data.password', 'secret')
        ->call('logoutOtherSessions')
        ->assertHasNoErrors()
        ->assertSet('confirming', false)
        ->assertSet('data.password', null);

    // Canonicalizing, because one of the two ids is `Str::random(40)`: sorting
    // the rows and comparing them to a literal asserted that a random string
    // sorts before `theirs`, which it does roughly nine times in ten. Which
    // rows are left is the fact here; the order they come back in is not.
    expect(DB::table('sessions')->pluck('id')->all())
        ->toEqualCanonicalizing([session()->getId(), 'theirs'])
        // The rehash is what ends a session on any other driver.
        ->and($me->refresh()->password)->not->toBe($hash)
        ->and(Hash::check('secret', $me->password))->toBeTrue()
        ->and(session('password_hash_'.Auth::getDefaultDriver()))->toBe($me->getAuthPassword());
});

it('ends nothing without the right password', function () {
    $me = User::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('secret')]);
    $this->be($me);

    sessionRow('phone', $me->id, IPHONE_SAFARI);

    Livewire::test(BrowserSessionManagement::class)
        ->call('confirm')
        ->set('data.password', 'not-it')
        ->call('logoutOtherSessions')
        ->assertHasErrors('data.password');

    expect(DB::table('sessions')->count())->toBe(1);
});

it('ends nothing for nobody', function () {
    sessionRow('phone', 1, IPHONE_SAFARI);

    Livewire::test(BrowserSessionManagement::class)
        ->call('logoutOtherSessions')
        ->call('cancel')
        ->assertSet('confirming', false);

    expect(DB::table('sessions')->count())->toBe(1);
});

it('reads a user agent to the depth the card needs', function (?string $header, ?string $platform, ?string $browser, bool $desktop) {
    $agent = UserAgent::parse($header);

    expect($agent->platform)->toBe($platform)
        ->and($agent->browser)->toBe($browser)
        ->and($agent->desktop)->toBe($desktop);
})->with([
    'chrome on mac' => [MAC_CHROME, 'macOS', 'Chrome', true],
    'safari on iphone' => [IPHONE_SAFARI, 'iOS', 'Safari', false],
    'edge on windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36 Edg/126.0', 'Windows', 'Edge', true],
    'firefox on linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0', 'Linux', 'Firefox', true],
    'opera on android' => ['Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Mobile Safari/537.36 OPR/82.0', 'Android', 'Opera', false],
    'chromebook' => ['Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36', 'ChromeOS', 'Chrome', true],
    'nothing' => [null, null, null, true],
]);
