<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireModuleSettings\Events\SettingsSaved;
use NyonCode\WireModuleSettings\Models\Setting;
use NyonCode\WireModuleSettings\Support\Settings;

/*
 * Storage: the cache, the write, and the two ways each of them used to be wrong.
 */

function ssTable(): void
{
    Schema::create('wire_settings', function (Blueprint $table) {
        $table->id();
        $table->string('group')->default('general');
        $table->string('key');
        $table->json('value')->nullable();
        $table->timestamps();
        $table->unique(['group', 'key']);
    });
}

/** A row written behind the model's back, so nothing invalidates anything. */
function ssRawWrite(string $key, mixed $value, string $group = 'branding'): void
{
    DB::table('wire_settings')->insert([
        'group' => $group,
        'key' => $key,
        'value' => json_encode($value),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(fn () => ssTable());

it('does not remember that the table was missing', function () {
    // The bug this replaces: `rememberForever` stored whatever the callback
    // returned, so the empty array a pre-migration read produced *was* the
    // cache — and a fresh install whose deploy touched a setting before
    // `migrate` came up empty and stayed empty until something wrote a value.
    Schema::drop('wire_settings');

    expect(Settings::get('company_name', 'Acme', 'branding'))->toBe('Acme');

    ssTable();
    ssRawWrite('company_name', 'Nyon');

    expect(Settings::get('company_name', 'Acme', 'branding'))->toBe('Nyon');
});

it('drops the cache when a row is written past the support class', function () {
    // A seeder, a data migration, a console command, tinker. Invalidation lives
    // on the model because the model is what every write goes through and the
    // support class is not.
    Settings::set('company_name', 'Acme', 'branding');

    Setting::query()->where('group', 'branding')->where('key', 'company_name')->first()
        ?->update(['value' => 'Nyon']);

    expect(Settings::get('company_name', null, 'branding'))->toBe('Nyon');
});

it('drops the cache when a row is deleted past the support class', function () {
    Settings::set('company_name', 'Acme', 'branding');

    Setting::query()->where('group', 'branding')->first()?->delete();

    expect(Settings::get('company_name', null, 'branding'))->toBeNull();
});

it('writes a whole group or none of it', function () {
    // The screen edits one group at a time so it never has to say "your branding
    // saved but your mail did not"; a loop of six writes where the fourth fails
    // says exactly that about one group's own fields.
    Setting::saving(function (Setting $setting): void {
        if ($setting->getAttribute('key') === 'boom') {
            throw new RuntimeException('nope');
        }
    });

    expect(fn () => Settings::fill(['company_name' => 'Acme', 'boom' => true], 'branding'))
        ->toThrow(RuntimeException::class);

    expect(Settings::all('branding'))->toBe([]);
});

it('announces a write, so an application can rebuild what it configured', function () {
    Event::fake([SettingsSaved::class]);

    Settings::fill(['company_name' => 'Acme'], 'branding');

    Event::assertDispatched(
        SettingsSaved::class,
        fn (SettingsSaved $event): bool => $event->group === 'branding'
            && $event->values === ['company_name' => 'Acme'],
    );
});

it('says nothing and writes nothing for an empty write', function () {
    Event::fake([SettingsSaved::class]);

    Settings::fill([], 'branding');

    Event::assertNotDispatched(SettingsSaved::class);
    expect(Setting::query()->count())->toBe(0);
});

it('removes one stored value', function () {
    Settings::fill(['company_name' => 'Acme', 'support_email' => 'a@b.test'], 'branding');

    Settings::remove('company_name', 'branding');

    expect(Settings::all('branding'))->toBe(['support_email' => 'a@b.test']);
});

it('clears a whole group', function () {
    Settings::fill(['company_name' => 'Acme'], 'branding');
    Settings::fill(['from' => 'a@b.test'], 'mail');

    Settings::clear('branding');

    expect(Settings::all('branding'))->toBe([])
        ->and(Settings::all('mail'))->toBe(['from' => 'a@b.test']);
});

it('caches in the store the application named', function () {
    // Settings are read on nearly every request, and on an application whose
    // default store is `database` the read this cache exists to avoid is simply
    // replaced by a different query.
    config()->set('cache.stores.settings_test', ['driver' => 'array']);
    config()->set('wire-module-settings.cache.store', 'settings_test');

    Settings::set('company_name', 'Acme', 'branding');
    Settings::all('branding');

    expect(Cache::store('settings_test')->get('wire-settings.branding'))
        ->toBe(['company_name' => 'Acme']);
});

it('reads the table on every call when caching is turned off', function () {
    config()->set('wire-module-settings.cache.enabled', false);

    Settings::all('branding');
    ssRawWrite('company_name', 'Nyon');

    expect(Settings::get('company_name', null, 'branding'))->toBe('Nyon')
        ->and(Cache::get('wire-settings.branding'))->toBeNull();
});
