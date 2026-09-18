<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use NyonCode\WireCore\Audit\AuditEntry;
use NyonCode\WireCore\Audit\Concerns\HasAuditable;

/*
 * From a model save to the audit screen, with credentials in the row.
 *
 * `AuditLoggerTest` pins the filter. This one follows a secret the whole way —
 * saved through Eloquent, picked up by the trait, written by the logger, served
 * by the module's pages over their routes — because the audit screen is where
 * a secret in the trail stops being a row in a table and becomes text on a page
 * for anybody who can open the log. Every assertion that matters is therefore
 * about the HTML a browser would receive.
 */

class CjAccount extends Model
{
    use HasAuditable;

    protected $table = 'cj_accounts';

    protected $guarded = [];

    public $timestamps = false;
}

/** A model that asks, explicitly, for its secrets to be audited. */
class CjGreedyAccount extends CjAccount
{
    protected function getAuditInclude(): array
    {
        return ['plan', 'api_token', 'two_factor_secret'];
    }
}

const CJ_SECRETS = [
    'password' => 'hunter2-hashed',
    'remember_token' => 'remember-me-value',
    'api_token' => 'live-token-value',
    'stripe_secret' => 'sk_live_value',
    'two_factor_secret' => 'SECRET-SEED',
    'two_factor_recovery_codes' => '["rc-aaa","rc-bbb"]',
    'api_key' => 'key-value',
];

beforeEach(function () {
    Schema::create('audit_logs', function (Blueprint $table) {
        $table->id();
        $table->string('event');
        $table->string('auditable_type');
        $table->string('auditable_id')->nullable();
        $table->string('user_id')->nullable();
        $table->json('old_values')->nullable();
        $table->json('new_values')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamp('created_at')->useCurrent();
    });

    Schema::create('cj_accounts', function (Blueprint $table) {
        $table->id();
        $table->string('plan');
        $table->string('billing_iban')->nullable();

        foreach (array_keys(CJ_SECRETS) as $column) {
            $table->text($column)->nullable();
        }
    });

    config()->set('wire-core.audit.enabled', true);

    View::addLocation(__DIR__.'/../../Fixtures/views');
    config()->set('livewire.component_layout', 'plain-layout');

    Route::middleware('web')->group(fn () => Route::wireResources(only: ['audit-log']));
});

/** Everything the log's two pages send a browser, for every entry there is. */
function cjEverythingOnScreen(): string
{
    $html = test()->get(route('wire.audit-log.index'))->assertOk()->getContent();

    foreach (AuditEntry::query()->pluck('id') as $id) {
        $html .= test()->get(route('wire.audit-log.view', $id))->assertOk()->getContent();
    }

    return $html;
}

function cjAssertNoSecretIn(string $html): void
{
    foreach (CJ_SECRETS as $column => $value) {
        expect($html)->not->toContain($value);
    }

    // The recovery codes are JSON, so they would reach the page escaped.
    expect($html)->not->toContain('rc-aaa');
}

it('keeps every credential off the screen through a record s whole life', function () {
    $account = CjAccount::query()->create(['plan' => 'starter', ...CJ_SECRETS]);

    $account->update([
        'plan' => 'business',
        'api_token' => 'rotated-token-value',
        'two_factor_secret' => 'ROTATED-SEED',
    ]);

    $account->delete();

    expect(AuditEntry::query()->pluck('event')->all())->toBe(['created', 'updated', 'deleted']);

    $html = cjEverythingOnScreen();

    cjAssertNoSecretIn($html);

    expect($html)->not->toContain('rotated-token-value')
        ->not->toContain('ROTATED-SEED')
        // And the change that is not a secret is still there to read: a trail
        // that hid everything would pass every line above.
        ->toContain('business');
});

it('keeps them off even when the published config was emptied', function () {
    // The case the floor exists for: a config file published years ago, or
    // edited by someone tidying it, no longer lists `password` at all.
    config()->set('wire-core.audit.exclude_columns', []);

    CjAccount::query()->create(['plan' => 'starter', ...CJ_SECRETS]);

    cjAssertNoSecretIn(cjEverythingOnScreen());
});

it('keeps them off even for a model that asked to audit them by name', function () {
    // A model's include list narrows what the trail records. It is not a way to
    // opt a credential back in: the logger's floor is applied after it.
    $account = CjGreedyAccount::query()->create(['plan' => 'starter', ...CJ_SECRETS]);
    $account->update(['plan' => 'business', 'api_token' => 'rotated-token-value']);

    $html = cjEverythingOnScreen();

    cjAssertNoSecretIn($html);
    expect($html)->not->toContain('rotated-token-value')->toContain('business');
});

it('writes no entry at all when the only thing that changed was a secret', function () {
    // A rotation of a token is not nothing — but an entry whose before and
    // after are both empty says nothing either, and a row that says nothing on
    // the audit screen reads as a bug in the screen.
    $account = CjAccount::query()->create(['plan' => 'starter', ...CJ_SECRETS]);

    $account->update(['api_token' => 'rotated-token-value', 'two_factor_secret' => 'ROTATED-SEED']);

    expect(AuditEntry::query()->where('event', 'updated')->count())->toBe(0);
});

it('takes a wildcard of the application s own on top of the floor', function () {
    config()->set('wire-core.audit.exclude_columns', ['billing_*']);

    CjAccount::query()->create(['plan' => 'starter', 'billing_iban' => 'CZ65 0800 0000 1920 0014 5399', ...CJ_SECRETS]);

    $html = cjEverythingOnScreen();

    cjAssertNoSecretIn($html);
    expect($html)->not->toContain('CZ65 0800')->toContain('starter');
});
